<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\TontineController;
use App\Http\Controllers\FinancialGoalController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// ── Page d'accueil → login ───────────────────────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

// ── Routes authentifiées ────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // ── Route locale — sans middleware onboarding ────────────────────
    Route::patch('/locale', function (\Illuminate\Http\Request $request) {
        $request->validate(['locale' => 'required|in:fr,en']);
        $request->user()->update(['locale' => $request->locale]);
        return response()->json(['success' => true]);
    })->name('profile.locale.update');

    // ── Routes admin — classe middleware directe ───────────────────────
    Route::middleware(\App\Http\Middleware\EnsureIsAdmin::class)
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/',      [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/users', [AdminController::class, 'users'])->name('users');
            Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
        });

    // ── Toutes les autres routes avec middleware onboarding ───────────
    Route::middleware('onboarding')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Onboarding ──────────────────────────────────────────────
        Route::prefix('onboarding', 'verified')->name('onboarding.')->group(function () {
            Route::get('/personal-info',      [OnboardingController::class, 'showPersonalInfo'])->name('personal-info');
            Route::post('/personal-info',     [OnboardingController::class, 'storePersonalInfo'])->name('personal-info.store');
            Route::get('/income',             [OnboardingController::class, 'showIncome'])->name('income');
            Route::post('/income',            [OnboardingController::class, 'storeIncome'])->name('income.store');
            Route::get('/charges',            [OnboardingController::class, 'showCharges'])->name('charges');
            Route::post('/charges',           [OnboardingController::class, 'storeCharges'])->name('charges.store');
            Route::get('/current-situation',  [OnboardingController::class, 'showCurrentSituation'])->name('current-situation');
            Route::post('/current-situation', [OnboardingController::class, 'storeCurrentSituation'])->name('current-situation.store');
            Route::get('/habits',             [OnboardingController::class, 'showHabits'])->name('habits');
            Route::post('/habits',            [OnboardingController::class, 'storeHabits'])->name('habits.store');
            Route::get('/debts',              [OnboardingController::class, 'showDebts'])->name('debts');
            Route::post('/debts',             [OnboardingController::class, 'storeDebts'])->name('debts.store');
            Route::get('/goals',              [OnboardingController::class, 'showGoals'])->name('goals');
            Route::post('/goals',             [OnboardingController::class, 'storeGoals'])->name('goals.store');
            Route::get('/summary',            [OnboardingController::class, 'showSummary'])->name('summary');
        });

        // ── Profil ──────────────────────────────────────────────────
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/',             [ProfileController::class, 'edit'])->name('edit');
            Route::patch('/info',       [ProfileController::class, 'updateInfo'])->name('info.update');
            Route::patch('/password',   [ProfileController::class, 'updatePassword'])->name('password.update');
            Route::patch('/personal',   [ProfileController::class, 'updatePersonal'])->name('personal.update');
            Route::patch('/habits',     [ProfileController::class, 'updateHabits'])->name('habits.update');
            Route::patch('/income',     [ProfileController::class, 'updateIncome'])->name('income.update');
            Route::patch('/dependents', [ProfileController::class, 'updateDependents'])->name('dependents.update');
            Route::delete('/',          [ProfileController::class, 'destroy'])->name('destroy');
        });

        // ── Transactions ────────────────────────────────────────────
        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/',                  [TransactionController::class, 'index'])->name('index');
            Route::get('/create',            [TransactionController::class, 'create'])->name('create');
            Route::post('/',                 [TransactionController::class, 'store'])->name('store');
            Route::delete('/{transaction}',  [TransactionController::class, 'destroy'])->name('destroy');
        });

        // ── Finances ────────────────────────────────────────────────
        Route::prefix('finances')->name('finances.')->group(function () {
            Route::get('/', [FinanceController::class, 'index'])->name('index');
            Route::post('/debts',                    [FinanceController::class, 'storeDette'])->name('debts.store');
            Route::post('/debts/{debt}/repay',       [FinanceController::class, 'repayDette'])->name('debts.repay');
            Route::delete('/debts/{debt}',           [FinanceController::class, 'destroyDette'])->name('debts.destroy');
            Route::post('/charges',                  [FinanceController::class, 'storeCharge'])->name('charges.store');
            Route::patch('/charges/{charge}/toggle', [FinanceController::class, 'toggleCharge'])->name('charges.toggle');
            Route::delete('/charges/{charge}',       [FinanceController::class, 'destroyCharge'])->name('charges.destroy');
            Route::post('/charges/{charge}/pay', [FinanceController::class, 'payCharge'])->name('charges.pay');
            Route::get('/goals', [FinancialGoalController::class, 'index'])->name('goals.index');
        });

        // ── Objectifs ───────────────────────────────────────────────
        Route::prefix('goals')->name('goals.')->group(function () {
            Route::post('/',                 [FinancialGoalController::class, 'store'])->name('store');
            Route::post('/{goal}/progress',  [FinancialGoalController::class, 'addProgress'])->name('progress');
            Route::delete('/{goal}',         [FinancialGoalController::class, 'destroy'])->name('destroy');
            Route::get('/{goal}/estimate',   [FinancialGoalController::class, 'estimate'])->name('estimate');
        });

        // ── Coach IA ────────────────────────────────────────────────
        Route::prefix('coach')->name('coach.')->group(function () {
            Route::get('/',         [CoachController::class, 'index'])->name('index');
            Route::post('/send',    [CoachController::class, 'send'])->name('send');
            Route::delete('/clear', [CoachController::class, 'clear'])->name('clear');
        });

        // ── Tontines ────────────────────────────────────────────────
        Route::prefix('tontines')->name('tontines.')->group(function () {
            Route::get('/',                              [TontineController::class, 'index'])->name('index');
            Route::get('/create',                        [TontineController::class, 'create'])->name('create');
            Route::post('/',                             [TontineController::class, 'store'])->name('store');
            Route::get('/{tontine}',                     [TontineController::class, 'show'])->name('show');
            Route::post('/cycles/{cycle}/mark-paid',     [TontineController::class, 'markPaid'])->name('cycles.mark-paid');
            Route::post('/cycles/{cycle}/mark-received', [TontineController::class, 'markReceived'])->name('cycles.mark-received');
            Route::patch('/{tontine}/deactivate',        [TontineController::class, 'deactivate'])->name('deactivate');
            Route::patch('/{tontine}/reactivate', [TontineController::class, 'reactivate'])->name('reactivate');
            Route::delete('/{tontine}',           [TontineController::class, 'destroy'])->name('destroy');
        });

        // ── Stats ────────────────────────────────────────────────────
        Route::get('/stats', [StatsController::class, 'index'])->name('stats.index');

    }); // fin middleware onboarding

}); // fin auth

require __DIR__.'/auth.php';