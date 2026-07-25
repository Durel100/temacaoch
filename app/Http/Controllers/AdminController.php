<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function dashboard()
    {
        $now   = now();
        $today = $now->copy()->startOfDay();

        // ── Utilisateurs ─────────────────────────────────────────────────────
        $totalUsers = User::where('is_admin', false)->count();

        $activeUsers7d = User::where('is_admin', false)
            ->where('updated_at', '>=', $now->copy()->subDays(7))
            ->count();

        $activeUsers30d = User::where('is_admin', false)
            ->where('updated_at', '>=', $now->copy()->subDays(30))
            ->count();

        $newThisMonth = User::where('is_admin', false)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at',  $now->year)
            ->count();

        $onboardingCompleted = User::where('is_admin', false)
            ->whereNotNull('onboarding_completed_at')
            ->count();

        $onboardingRate = $totalUsers > 0
            ? round(($onboardingCompleted / $totalUsers) * 100)
            : 0;

        // ── Inscriptions par jour (30 derniers jours) ─────────────────────────
        $signupsByDay = [];
        for ($i = 29; $i >= 0; $i--) {
            $date  = $now->copy()->subDays($i)->startOfDay();
            $count = User::where('is_admin', false)
                ->whereDate('created_at', $date->toDateString())
                ->count();

            $signupsByDay[] = [
                'date'  => $date->toDateString(),
                'label' => $date->format('d/m'),
                'count' => $count,
            ];
        }

        // ── Données globales ──────────────────────────────────────────────────
        $totalTransactions = Transaction::count();

        $transactionsThisMonth = Transaction::whereMonth('transacted_at', $now->month)
            ->whereYear('transacted_at', $now->year)
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users'          => $totalUsers,
                'active_7d'            => $activeUsers7d,
                'active_30d'           => $activeUsers30d,
                'new_this_month'       => $newThisMonth,
                'onboarding_completed' => $onboardingCompleted,
                'onboarding_rate'      => $onboardingRate,
                'total_transactions'   => $totalTransactions,
                'transactions_month'   => $transactionsThisMonth,
            ],
            'signupsByDay' => $signupsByDay,
        ]);
    }

    public function users(Request $request)
    {
        $status = $request->get('status', 'all');
        $search = $request->get('search', '');

        $query = User::where('is_admin', false)
            ->withCount(['transactions', 'debts', 'tontineGroups']);

        // Filtre recherche
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name',  'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtre statut
        match ($status) {
            'active'     => $query->where('updated_at', '>=', now()->subDays(7)),
            'inactive'   => $query->where('updated_at', '<',  now()->subDays(30)),
            'onboarding' => $query->whereNull('onboarding_completed_at'),
            default      => null,
        };

        $users = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($u) => [
                'id'                      => $u->id,
                'name'                    => $u->name,
                'email'                   => $u->email,
                'locale'                  => $u->locale,
                'created_at'              => $u->created_at->toDateString(),
                'last_active'             => $u->updated_at->diffForHumans(),
                'onboarding_completed'    => !is_null($u->onboarding_completed_at),
                'transactions_count'      => $u->transactions_count,
                'debts_count'             => $u->debts_count,
                'tontine_groups_count'    => $u->tontine_groups_count,
                'is_active'               => $u->updated_at->gte(now()->subDays(7)),
            ]);

        return Inertia::render('Admin/Users', [
            'users'   => $users,
            'filters' => ['status' => $status, 'search' => $search],
            'total'   => User::where('is_admin', false)->count(),
        ]);
    }

    public function toggleAdmin(User $user)
    {
        // Sécurité : ne pas se désactiver soi-même
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tu ne peux pas modifier ton propre statut.');
        }

        $user->update(['is_admin' => !$user->is_admin]);
        return back()->with('success', 'Statut admin modifié.');
    }

    public function destroyUser(\App\Models\User $user)
    {
        // Ne pas supprimer un admin
        if ($user->is_admin) {
            return back()->with('error', 'Impossible de supprimer un administrateur.');
        }

        $user->delete();

        return back()->with('success', 'Compte supprimé.');
    }
}