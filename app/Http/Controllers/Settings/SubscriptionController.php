<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Display the subscription management page: plan catalog + assigned users.
     */
    public function show(Request $request): Response
    {
        $plans = SubscriptionPlan::query()
            ->ordered()
            ->withCount(['users as active_users_count' => function ($query): void {
                $query->where(function ($q): void {
                    $q->whereNull('plan_expires_at')->orWhere('plan_expires_at', '>', now());
                });
            }])
            ->get();

        $search = (string) $request->string('search');

        $users = Pengguna::query()
            ->with('plan:id,name,label,daily_limit')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('nama', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($search === '', function ($query): void {
                $query->whereNotNull('plan_id');
            })
            ->orderByRaw('plan_expires_at IS NULL, plan_expires_at ASC')
            ->limit(50)
            ->get(['id', 'nama', 'email', 'username', 'plan_id', 'plan_started_at', 'plan_expires_at'])
            ->map(function (Pengguna $user): array {
                return [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'username' => $user->username,
                    'plan' => $user->plan ? [
                        'id' => $user->plan->id,
                        'name' => $user->plan->name,
                        'label' => $user->plan->label,
                        'daily_limit' => $user->plan->daily_limit,
                    ] : null,
                    'plan_started_at' => $user->plan_started_at?->toIso8601String(),
                    'plan_expires_at' => $user->plan_expires_at?->toIso8601String(),
                    'state' => $user->hasActivePlan() ? 'active' : ($user->plan_id ? 'expired' : 'free'),
                ];
            });

        return Inertia::render('settings/Subscription', [
            'plans' => $plans,
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Create a new subscription plan tier.
     */
    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request);

        SubscriptionPlan::create($validated);

        return back()->with('success', 'Plan berhasil ditambahkan');
    }

    /**
     * Update an existing subscription plan tier.
     */
    public function updatePlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $this->validatePlan($request, $plan);

        $plan->update($validated);

        return back()->with('success', 'Plan berhasil diupdate');
    }

    /**
     * Toggle a plan's active state (retire / restore without deleting history).
     */
    public function togglePlan(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->name === 'free') {
            return back()->with('error', 'Plan "free" tidak boleh dinonaktifkan');
        }

        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('success', 'Status plan berhasil diubah');
    }

    /**
     * Assign a plan to a user, setting started/expires timestamps from duration_days.
     */
    public function assignUser(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plan,id',
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        $pengguna->update([
            'plan_id' => $plan->id,
            'plan_started_at' => now(),
            'plan_expires_at' => $plan->duration_days ? now()->addDays($plan->duration_days) : null,
        ]);

        return back()->with('success', 'Plan berhasil diberikan ke pengguna');
    }

    /**
     * Extend the current plan by its duration_days (renewal).
     */
    public function extendUser(Pengguna $pengguna): RedirectResponse
    {
        if ($pengguna->plan_id === null) {
            return back()->with('error', 'Pengguna belum punya plan');
        }

        $days = $pengguna->plan?->duration_days ?? 30;
        $base = $pengguna->hasActivePlan() && $pengguna->plan_expires_at
            ? $pengguna->plan_expires_at
            : now();

        $pengguna->update([
            'plan_expires_at' => $base->copy()->addDays($days),
        ]);

        return back()->with('success', 'Plan pengguna berhasil diperpanjang');
    }

    /**
     * Revoke a user's plan, dropping them back to Free immediately.
     */
    public function revokeUser(Pengguna $pengguna): RedirectResponse
    {
        $pengguna->update([
            'plan_id' => null,
            'plan_started_at' => null,
            'plan_expires_at' => null,
        ]);

        return back()->with('success', 'Plan pengguna berhasil dicabut');
    }

    /**
     * Shared validation rules for creating/updating a plan.
     *
     * @return array<string, mixed>
     */
    private function validatePlan(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $nameRule = 'required|string|max:50|unique:subscription_plan,name';
        if ($plan !== null) {
            $nameRule .= ','.$plan->id;
        }

        return $request->validate([
            'name' => $nameRule,
            'label' => 'required|string|max:100',
            'price' => 'required|integer|min:0',
            'daily_limit' => 'required|integer|min:0',
            'duration_days' => 'nullable|integer|min:1',
            'seq' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);
    }
}
