<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Grants and revokes `pengguna.is_admin`, the flag the chat/deposit API checks
 * before opening its admin endpoints.
 *
 * These are app accounts, not the CMS staff logins in `users`. The two tables
 * are linked only by matching email, which is how "your own account" is
 * recognised here.
 */
class AdminAccessController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $adminsOnly = $request->boolean('admins_only');

        $ownEmail = $this->currentEmail();

        $users = Pengguna::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($adminsOnly, fn ($q) => $q->where('is_admin', true))
            ->orderByDesc('is_admin')
            ->orderBy('nama')
            ->orderBy('id')
            ->paginate(20, ['id', 'nama', 'email', 'username', 'is_admin'])
            ->withQueryString()
            ->through(fn (Pengguna $p): array => [
                'id' => $p->id,
                'nama' => $p->nama,
                'email' => $p->email,
                'username' => $p->username,
                'is_admin' => $p->is_admin,
                // Drives the disabled state; the server enforces it regardless.
                'is_self' => $this->isSelf($p, $ownEmail),
            ]);

        return Inertia::render('settings/AdminAccess', [
            'users' => $users,
            'filters' => ['search' => $search, 'admins_only' => $adminsOnly],
            'adminCount' => Pengguna::where('is_admin', true)->count(),
        ]);
    }

    public function toggle(Request $request, Pengguna $pengguna): RedirectResponse
    {
        // Nobody edits their own access: revoking it by accident would lock the
        // account out of the admin API with no way back from this screen.
        if ($this->isSelf($pengguna, $this->currentEmail())) {
            return back()->with('error', 'Tidak bisa mengubah akses admin untuk akun Anda sendiri.');
        }

        $granting = ! $pengguna->is_admin;

        // Losing the last admin would leave the admin API unreachable for
        // everyone, and this screen cannot grant it back.
        if (! $granting && Pengguna::where('is_admin', true)->count() <= 1) {
            return back()->with('error', 'Ini admin terakhir. Angkat admin lain dulu sebelum mencabut yang ini.');
        }

        $pengguna->update(['is_admin' => $granting]);

        $name = $pengguna->nama ?: $pengguna->email;

        return back()->with(
            'success',
            $granting
                ? "{$name} sekarang admin."
                : "Akses admin {$name} dicabut."
        );
    }

    /**
     * Email of the CMS account currently signed in.
     */
    private function currentEmail(): ?string
    {
        return auth()->user()?->email;
    }

    /**
     * `users` and `pengguna` are separate tables with separate ids, so the same
     * person is only identifiable across them by email.
     */
    private function isSelf(Pengguna $pengguna, ?string $ownEmail): bool
    {
        if ($ownEmail === null || $pengguna->email === null) {
            return false;
        }

        return mb_strtolower(trim($pengguna->email)) === mb_strtolower(trim($ownEmail));
    }
}
