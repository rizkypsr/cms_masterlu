<?php

namespace App\Http\Controllers;

use App\Models\AiRate;
use App\Models\DepositLedger;
use App\Models\DepositTopup;
use App\Models\DepositTopupInfo;
use App\Models\Pengguna;
use App\Models\SubscriptionPlan;
use App\Support\HtmlSanitizer;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Saldo Deposit admin screens.
 *
 * Hard rules from DEPOSIT_CMS_ADMIN, enforced throughout this controller:
 *  1. Balance and ledger are written together in one transaction — never apart.
 *  2. Money columns are milli-rupiah (Rp1 = 1000); admins type rupiah.
 *  3. Timestamps must be UTC. config('app.timezone') is UTC, so Laravel's now()
 *     equals the DB's UTC_TIMESTAMP() — using now() keeps rows aligned with the
 *     API's, without raw SQL.
 *  4. Status transitions are conditional (`where status = pending`) and the
 *     affected-row count is checked, so a double click can't credit twice.
 *  5. deposit_ledger is append-only; ai_rate is insert-only.
 */
class DepositController extends Controller
{
    /** Guard against a mistyped extra zero — no other process would catch it. */
    private const MAX_TOPUP_RP = 10_000_000;

    /** Ceiling on one bulk correction, so a stray "select all" stays bounded. */
    private const MAX_BULK_USERS = 1000;

    /**
     * Daily work screen: the topup verification queue.
     */
    public function topups(Request $request): Response
    {
        $status = (string) $request->string('status', 'pending');
        if (! in_array($status, ['pending', 'paid', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $topups = DepositTopup::query()
            ->with(['pengguna:id,nama,email,username,deposit_balance_mrp', 'approver:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            // Oldest first while working the queue; newest first when reviewing history.
            ->orderBy('created_at', $status === 'pending' ? 'asc' : 'desc')
            ->orderBy('id', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DepositTopup $t): array => [
                'id' => $t->id,
                'pengguna' => $t->pengguna ? [
                    'id' => $t->pengguna->id,
                    'nama' => $t->pengguna->nama,
                    'email' => $t->pengguna->email,
                    'username' => $t->pengguna->username,
                    'balance_rp' => Money::toRupiah($t->pengguna->deposit_balance_mrp),
                ] : null,
                'amount_rp' => Money::toRupiah($t->amount_mrp),
                'credited_rp' => $t->credited_mrp === null ? null : Money::toRupiah($t->credited_mrp),
                'status' => $t->status,
                'payment_note' => $t->payment_note,
                'rejected_reason' => $t->rejected_reason,
                'approved_by' => $t->approver?->name,
                'paid_at' => $t->paid_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Deposit/Topup', [
            'topups' => $topups,
            'filters' => ['status' => $status],
            'counts' => DepositTopup::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'maxTopupRp' => self::MAX_TOPUP_RP,
        ]);
    }

    /**
     * Approve a transfer and credit the balance — the only normal path by which
     * a user's balance grows.
     */
    public function approveTopup(Request $request, DepositTopup $depositTopup): RedirectResponse
    {
        $validated = $request->validate([
            'credited_rp' => 'nullable|numeric|min:1|max:'.self::MAX_TOPUP_RP,
            'payment_note' => 'nullable|string|max:1000',
        ]);

        $creditedMrp = isset($validated['credited_rp'])
            ? Money::toMrp($validated['credited_rp'])
            : $depositTopup->amount_mrp;

        $note = trim((string) ($validated['payment_note'] ?? '')) ?: null;

        try {
            $credited = DB::transaction(function () use ($depositTopup, $creditedMrp, $note): bool {
                // Conditional claim. 0 rows = another admin (or a double click)
                // already handled it, so the balance must not move.
                $claimed = DepositTopup::where('id', $depositTopup->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'paid',
                        'credited_mrp' => $creditedMrp,
                        'approved_by' => auth()->id(),
                        'paid_at' => now(),
                        'payment_note' => $note,
                    ]);

                if ($claimed === 0) {
                    return false;
                }

                $pengguna = Pengguna::lockForUpdate()->findOrFail($depositTopup->pengguna_id);
                $pengguna->deposit_balance_mrp = $pengguna->deposit_balance_mrp + $creditedMrp;
                $pengguna->save();

                // ref_type/ref_id carry a unique index, so a topup is impossible
                // to credit twice even if this ran again.
                DepositLedger::create([
                    'pengguna_id' => $pengguna->id,
                    'delta_mrp' => $creditedMrp,
                    'type' => 'topup',
                    'balance_after_mrp' => $pengguna->deposit_balance_mrp,
                    'ref_type' => 'deposit_topup',
                    'ref_id' => $depositTopup->id,
                    'note' => $note,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                ]);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            // Second safety net: uq_deposit_ledger_ref on (ref_type, ref_id).
            return back()->with('error', 'Topup ini sudah pernah dikreditkan. Saldo tidak berubah.');
        }

        if (! $credited) {
            return back()->with('error', 'Permintaan ini sudah diproses admin lain. Saldo tidak berubah.');
        }

        return back()->with('success', 'Topup disetujui, saldo dikreditkan.');
    }

    /**
     * Reject a topup. Never touches the balance.
     */
    public function rejectTopup(Request $request, DepositTopup $depositTopup): RedirectResponse
    {
        $validated = $request->validate([
            'rejected_reason' => 'required|string|max:1000',
        ]);

        $affected = DepositTopup::where('id', $depositTopup->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'rejected_reason' => trim($validated['rejected_reason']),
                'approved_by' => auth()->id(),
            ]);

        if ($affected === 0) {
            return back()->with('error', 'Permintaan ini sudah diproses admin lain.');
        }

        return back()->with('success', 'Topup ditolak.');
    }

    /**
     * User balances, with manual correction entry point.
     */
    public function balances(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $users = $this->filteredUserQuery($search, $request->boolean('with_balance'))
            ->orderByDesc('deposit_balance_mrp')
            ->orderBy('id')
            ->paginate(20, ['id', 'nama', 'email', 'username', 'deposit_balance_mrp'])
            ->withQueryString()
            ->through(fn (Pengguna $p): array => [
                'id' => $p->id,
                'nama' => $p->nama,
                'email' => $p->email,
                'username' => $p->username,
                'balance_rp' => Money::toRupiah($p->deposit_balance_mrp),
            ]);

        return Inertia::render('Deposit/Saldo', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'with_balance' => $request->boolean('with_balance'),
            ],
            'maxTopupRp' => self::MAX_TOPUP_RP,
            'maxBulkUsers' => self::MAX_BULK_USERS,
        ]);
    }

    /**
     * Shared by the balance list and bulk "select all", so the set an admin
     * sees is exactly the set that gets credited.
     */
    private function filteredUserQuery(string $search, bool $withBalance): \Illuminate\Database\Eloquent\Builder
    {
        return Pengguna::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($withBalance, fn ($q) => $q->where('deposit_balance_mrp', '!=', 0));
    }

    /**
     * Manual balance correction (adjust) or a grant (bonus).
     */
    public function adjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pengguna_id' => 'required|integer|exists:pengguna,id',
            'delta_rp' => 'required|numeric',
            'type' => 'required|in:adjust,bonus',
            // Shown verbatim to the user in their transaction history, so it is
            // not a formality — an unexplained balance change is complaint #1.
            'note' => 'required|string|max:1000',
        ]);

        $deltaMrp = Money::toMrp($validated['delta_rp']);

        if ($deltaMrp === 0) {
            return back()->with('error', 'Nominal koreksi tidak boleh nol.');
        }

        if (abs($deltaMrp) > Money::toMrp(self::MAX_TOPUP_RP)) {
            return back()->with('error', 'Nominal melebihi batas Rp'.number_format(self::MAX_TOPUP_RP, 0, ',', '.').' per transaksi.');
        }

        DB::transaction(function () use ($validated, $deltaMrp): void {
            $pengguna = Pengguna::lockForUpdate()->findOrFail($validated['pengguna_id']);
            $pengguna->deposit_balance_mrp = $pengguna->deposit_balance_mrp + $deltaMrp;
            $pengguna->save();

            // ref_type/ref_id stay NULL for adjust/bonus on purpose: the unique
            // index allows repeated NULLs, so corrections can happen many times.
            DepositLedger::create([
                'pengguna_id' => $pengguna->id,
                'delta_mrp' => $deltaMrp,
                'type' => $validated['type'],
                'balance_after_mrp' => $pengguna->deposit_balance_mrp,
                'note' => trim($validated['note']),
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Saldo berhasil dikoreksi.');
    }

    /**
     * Same correction applied to many users at once — typically an opening
     * balance at launch. Every user still gets their own ledger row carrying
     * their own balance_after_mrp, and the whole batch is one transaction, so
     * a failure part-way through leaves no half-credited accounts.
     */
    public function bulkAdjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:pengguna,id',
            'select_all' => 'boolean',
            'search' => 'nullable|string',
            'with_balance' => 'boolean',
            'delta_rp' => 'required|numeric',
            'type' => 'required|in:adjust,bonus',
            'note' => 'required|string|max:1000',
        ]);

        $deltaMrp = Money::toMrp($validated['delta_rp']);

        if ($deltaMrp === 0) {
            return back()->with('error', 'Nominal koreksi tidak boleh nol.');
        }

        if (abs($deltaMrp) > Money::toMrp(self::MAX_TOPUP_RP)) {
            return back()->with('error', 'Nominal melebihi batas Rp'.number_format(self::MAX_TOPUP_RP, 0, ',', '.').' per transaksi.');
        }

        // "Select all" resolves server-side from the same filters the list used,
        // so the client never has to ship thousands of ids.
        $targets = $request->boolean('select_all')
            ? $this->filteredUserQuery($validated['search'] ?? '', $request->boolean('with_balance'))->pluck('id')
            : collect($validated['user_ids'] ?? []);

        $targets = $targets->unique()->values();

        if ($targets->isEmpty()) {
            return back()->with('error', 'Tidak ada pengguna terpilih.');
        }

        if ($targets->count() > self::MAX_BULK_USERS) {
            return back()->with('error', 'Maksimal '.self::MAX_BULK_USERS.' pengguna per koreksi massal.');
        }

        $note = trim($validated['note']);
        $type = $validated['type'];

        DB::transaction(function () use ($targets, $deltaMrp, $type, $note): void {
            foreach ($targets as $penggunaId) {
                $pengguna = Pengguna::lockForUpdate()->find($penggunaId);

                if ($pengguna === null) {
                    continue;
                }

                $pengguna->deposit_balance_mrp = $pengguna->deposit_balance_mrp + $deltaMrp;
                $pengguna->save();

                DepositLedger::create([
                    'pengguna_id' => $pengguna->id,
                    'delta_mrp' => $deltaMrp,
                    'type' => $type,
                    'balance_after_mrp' => $pengguna->deposit_balance_mrp,
                    'note' => $note,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
        });

        $totalRp = Money::toRupiah($deltaMrp * $targets->count());

        return back()->with(
            'success',
            'Koreksi diterapkan ke '.$targets->count().' pengguna (total Rp'.number_format($totalRp, 0, ',', '.').').'
        );
    }

    /**
     * One user's ledger + topup history. Opens the user's own question text,
     * so this is for handling complaints only.
     */
    public function user(Request $request, Pengguna $pengguna): Response
    {
        $ledger = $pengguna->depositLedger()
            ->with('author:id,name')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (DepositLedger $l): array => [
                'id' => $l->id,
                'delta_rp' => Money::toRupiah($l->delta_mrp),
                'type' => $l->type,
                'balance_after_rp' => Money::toRupiah($l->balance_after_mrp),
                'ref_type' => $l->ref_type,
                'ref_id' => $l->ref_id,
                'model' => $l->model,
                'input_tokens' => $l->input_tokens,
                'cached_input_tokens' => $l->cached_input_tokens,
                'output_tokens' => $l->output_tokens,
                'note' => $l->note,
                'created_by' => $l->author?->name,
                'created_at' => $l->created_at?->toIso8601String(),
            ]);

        $topups = $pengguna->depositTopups()
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (DepositTopup $t): array => [
                'id' => $t->id,
                'amount_rp' => Money::toRupiah($t->amount_mrp),
                'credited_rp' => $t->credited_mrp === null ? null : Money::toRupiah($t->credited_mrp),
                'status' => $t->status,
                'payment_note' => $t->payment_note,
                'rejected_reason' => $t->rejected_reason,
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        // Ledger is authoritative; the cached balance must equal its sum.
        $ledgerSum = (int) $pengguna->depositLedger()->sum('delta_mrp');

        return Inertia::render('Deposit/User', [
            'pengguna' => [
                'id' => $pengguna->id,
                'nama' => $pengguna->nama,
                'email' => $pengguna->email,
                'username' => $pengguna->username,
                'balance_rp' => Money::toRupiah($pengguna->deposit_balance_mrp),
                'ledger_rp' => Money::toRupiah($ledgerSum),
                'drift' => $pengguna->deposit_balance_mrp !== $ledgerSum,
            ],
            'ledger' => $ledger,
            'topups' => $topups,
            'maxTopupRp' => self::MAX_TOPUP_RP,
        ]);
    }

    /**
     * AI token rates. Insert-only history — retired rows stay visible.
     */
    public function rates(): Response
    {
        $rates = AiRate::query()
            ->orderByDesc('is_active')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (AiRate $r): array => [
                'id' => $r->id,
                'model' => $r->model,
                'input_usd_per_1m' => $r->input_usd_per_1m,
                'cached_input_usd_per_1m' => $r->cached_input_usd_per_1m,
                'output_usd_per_1m' => $r->output_usd_per_1m,
                'embed_usd_per_1m' => $r->embed_usd_per_1m,
                'usd_to_idr' => $r->usd_to_idr,
                // Precomputed so the table can show what a row actually charges.
                'input_mrp_per_1k' => $r->mrpPer1k('input_usd_per_1m'),
                'cached_input_mrp_per_1k' => $r->mrpPer1k('cached_input_usd_per_1m'),
                'output_mrp_per_1k' => $r->mrpPer1k('output_usd_per_1m'),
                'embed_mrp_per_1k' => $r->mrpPer1k('embed_usd_per_1m'),
                'is_free_tier' => $r->is_free_tier,
                'is_active' => $r->is_active,
                'note' => $r->note,
                'effective_from' => $r->effective_from?->toIso8601String(),
            ]);

        return Inertia::render('Deposit/Tarif', [
            'rates' => $rates,
            'models' => AiRate::query()->distinct()->orderBy('model')->pluck('model'),
            // Sensible starting point for a new row: the rate most recently used.
            'lastKurs' => (int) (AiRate::query()->orderByDesc('id')->value('usd_to_idr') ?? 18000),
        ]);
    }

    /**
     * Add a new rate row. Never edits an old one: past ledger rows point at the
     * rate that billed them. The previous active row for the model is retired.
     */
    public function storeRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'model' => 'required|string|max:64',
            // Copied straight off the provider's pricing page, USD per 1M tokens.
            'input_usd_per_1m' => 'required|numeric|min:0|max:999999',
            'cached_input_usd_per_1m' => 'required|numeric|min:0|max:999999',
            'output_usd_per_1m' => 'required|numeric|min:0|max:999999',
            'embed_usd_per_1m' => 'nullable|numeric|min:0|max:999999',
            'usd_to_idr' => 'required|integer|min:1',
            'is_free_tier' => 'boolean',
            // Required: the only trace of why a rate changed, and rate history
            // is never deleted.
            'note' => 'required|string|max:1000',
        ]);

        $model = trim($validated['model']);

        DB::transaction(function () use ($validated, $model): void {
            AiRate::where('model', $model)->where('is_active', true)->update(['is_active' => false]);

            AiRate::create([
                'model' => $model,
                'input_usd_per_1m' => $validated['input_usd_per_1m'],
                'cached_input_usd_per_1m' => $validated['cached_input_usd_per_1m'],
                'output_usd_per_1m' => $validated['output_usd_per_1m'],
                'embed_usd_per_1m' => $validated['embed_usd_per_1m'] ?? 0.02,
                'usd_to_idr' => $validated['usd_to_idr'],
                'is_free_tier' => $validated['is_free_tier'] ?? false,
                'effective_from' => now(),
                'is_active' => true,
                'note' => trim($validated['note']),
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Tarif baru ditambahkan. Berlaku paling lambat 5 menit (ada cache tarif).');
    }

    /**
     * Payment instructions served to users when they request a topup.
     */
    public function paymentInfo(): Response
    {
        $rows = DepositTopupInfo::query()
            ->with('editor:id,name')
            ->ordered()
            ->get()
            ->map(fn (DepositTopupInfo $i): array => [
                'id' => $i->id,
                'wa_number' => $i->wa_number,
                'wa_link' => $i->waLink(),
                'description' => $i->description,
                'is_active' => $i->is_active,
                'seq' => $i->seq,
                'updated_by' => $i->editor?->name,
                'updated_at' => $i->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Deposit/Pembayaran', [
            'infos' => $rows,
        ]);
    }

    public function storePaymentInfo(Request $request): RedirectResponse
    {
        $validated = $this->validatePaymentInfo($request);

        $this->savePaymentInfo(new DepositTopupInfo, $validated, $request->boolean('is_active'));

        return back()->with('success', 'Format pembayaran ditambahkan.');
    }

    public function updatePaymentInfo(Request $request, DepositTopupInfo $depositTopupInfo): RedirectResponse
    {
        $validated = $this->validatePaymentInfo($request);

        $this->savePaymentInfo($depositTopupInfo, $validated, $request->boolean('is_active'));

        return back()->with('success', 'Format pembayaran diperbarui.');
    }

    /**
     * Make one row the served copy; the rest stay as fallbacks.
     */
    public function activatePaymentInfo(DepositTopupInfo $depositTopupInfo): RedirectResponse
    {
        DB::transaction(function () use ($depositTopupInfo): void {
            DepositTopupInfo::where('id', '!=', $depositTopupInfo->id)->update(['is_active' => false]);
            $depositTopupInfo->update(['is_active' => true, 'updated_by' => auth()->id()]);
        });

        return back()->with('success', 'Format ini sekarang yang ditampilkan ke pengguna.');
    }

    public function destroyPaymentInfo(DepositTopupInfo $depositTopupInfo): RedirectResponse
    {
        if ($depositTopupInfo->is_active) {
            return back()->with('error', 'Format yang sedang aktif tidak bisa dihapus. Aktifkan format lain dulu.');
        }

        $depositTopupInfo->delete();

        return back()->with('success', 'Format pembayaran dihapus.');
    }

    /**
     * @return array{wa_number: string, description: string, seq: int}
     */
    private function validatePaymentInfo(Request $request): array
    {
        $validated = $request->validate([
            // Free-format on purpose; digits are extracted for the wa.me link.
            'wa_number' => 'required|string|max:32',
            'description' => 'required|string|max:20000',
            'seq' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $waDigits = preg_replace('/\D+/', '', $validated['wa_number']) ?? '';

        if (strlen($waDigits) < 8) {
            abort(422, 'Nomor WhatsApp tidak valid.');
        }

        return [
            'wa_number' => trim($validated['wa_number']),
            // Rendered as HTML in the user app, so it is sanitised on write —
            // never trust that every reader will escape it.
            'description' => HtmlSanitizer::clean($validated['description']),
            'seq' => $validated['seq'] ?? 0,
        ];
    }

    /**
     * @param  array{wa_number: string, description: string, seq: int}  $data
     */
    private function savePaymentInfo(DepositTopupInfo $info, array $data, bool $active): void
    {
        DB::transaction(function () use ($info, $data, $active): void {
            if ($active) {
                DepositTopupInfo::where('id', '!=', $info->id ?? 0)->update(['is_active' => false]);
            }

            $info->fill($data + [
                'is_active' => $active,
                'updated_by' => auth()->id(),
            ])->save();

            // Never leave users with no instructions: if nothing is active
            // after this save, the row just written becomes the served one.
            if (! DepositTopupInfo::where('is_active', true)->exists()) {
                $info->update(['is_active' => true]);
            }
        });
    }

    /**
     * Monthly money-in vs money-spent, data health check, and the daily free
     * quota control.
     */
    public function report(): Response
    {
        $months = DepositLedger::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->selectRaw("SUM(CASE WHEN type IN ('topup','bonus') THEN delta_mrp ELSE 0 END) as in_mrp")
            ->selectRaw("SUM(CASE WHEN type = 'spend' THEN -delta_mrp ELSE 0 END) as spent_mrp")
            ->selectRaw("SUM(CASE WHEN type = 'spend' THEN 1 ELSE 0 END) as questions")
            ->groupBy('month')
            ->orderByDesc('month')
            ->limit(24)
            ->get()
            ->map(fn ($r): array => [
                'month' => $r->month,
                'in_rp' => Money::toRupiah((int) $r->in_mrp),
                'spent_rp' => Money::toRupiah((int) $r->spent_mrp),
                'questions' => (int) $r->questions,
            ]);

        // Shadow mode (DEPOSIT_ENFORCE=false) records the cost that *would* have
        // been charged in note as `shadow:<mrp>` while delta_mrp stays 0.
        $shadowMrp = (int) DepositLedger::query()
            ->where('type', 'spend')
            ->where('note', 'like', 'shadow:%')
            ->sum(DB::raw('CAST(SUBSTRING(note, 8) AS UNSIGNED)'));

        // Reconciliation: cached balance vs ledger sum. Must always be empty.
        $drift = Pengguna::query()
            ->leftJoin('deposit_ledger', 'deposit_ledger.pengguna_id', '=', 'pengguna.id')
            ->groupBy('pengguna.id', 'pengguna.nama', 'pengguna.email', 'pengguna.deposit_balance_mrp')
            ->havingRaw('pengguna.deposit_balance_mrp <> COALESCE(SUM(deposit_ledger.delta_mrp), 0)')
            ->limit(50)
            ->get([
                'pengguna.id',
                'pengguna.nama',
                'pengguna.email',
                'pengguna.deposit_balance_mrp',
                DB::raw('COALESCE(SUM(deposit_ledger.delta_mrp), 0) as ledger_mrp'),
            ])
            ->map(fn ($p): array => [
                'id' => $p->id,
                'nama' => $p->nama,
                'email' => $p->email,
                'balance_rp' => Money::toRupiah((int) $p->deposit_balance_mrp),
                'ledger_rp' => Money::toRupiah((int) $p->ledger_mrp),
            ]);

        $freePlan = SubscriptionPlan::where('name', 'free')->first();

        return Inertia::render('Deposit/Laporan', [
            'months' => $months,
            'shadow_rp' => Money::toRupiah($shadowMrp),
            'drift' => $drift,
            'freeQuota' => $freePlan ? [
                'id' => $freePlan->id,
                'daily_limit' => $freePlan->daily_limit,
            ] : null,
            'totals' => [
                'balance_rp' => Money::toRupiah((int) Pengguna::sum('deposit_balance_mrp')),
                'pending_topups' => DepositTopup::pending()->count(),
            ],
        ]);
    }

    /**
     * Daily free-question quota, stored on the `free` subscription_plan row.
     * That row must keep its name — the chat API looks it up by name.
     */
    public function updateFreeQuota(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'daily_limit' => 'required|integer|min:0|max:100',
        ]);

        $plan = SubscriptionPlan::where('name', 'free')->first();

        if ($plan === null) {
            return back()->with('error', 'Baris plan "free" tidak ditemukan.');
        }

        $plan->update(['daily_limit' => $validated['daily_limit']]);

        return back()->with('success', 'Jatah gratis harian diperbarui. Berlaku paling lambat 1 menit.');
    }
}
