<?php

use App\Http\Controllers\Admin\AgentProfileController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CommissionPayoutController;
use App\Http\Controllers\Admin\ClientMessageController;
use App\Http\Controllers\Admin\CommissionReviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MarketController;
use App\Http\Controllers\Admin\PhoneNumberController;
use App\Http\Controllers\Admin\PlanCommissionController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProspectController;
use App\Http\Controllers\Admin\ProspectDeletionController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TerritoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserDeletionController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\WebTestController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Flujo web_test: prueba del agente de voz desde el navegador (público; el UUID del cliente es el acceso).
Route::get('web_test/{organization:uuid}', [WebTestController::class, 'show'])->name('web_test.show');
Route::post('web_test/{organization:uuid}/call', [WebTestController::class, 'start'])
    ->middleware('throttle:6,1')
    ->name('web_test.start');
Route::get('web_test/calls/{retellCall:uuid}/status', [WebTestController::class, 'status'])
    ->middleware('throttle:90,1')
    ->name('web_test.status');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('markets', MarketController::class)
            ->parameters(['markets' => 'market:uuid']);

        Route::get('markets/{market:uuid}/plans/create', [PlanController::class, 'create'])->name('markets.plans.create');
        Route::post('markets/{market:uuid}/plans', [PlanController::class, 'store'])->name('markets.plans.store');
        Route::get('plans/{plan:uuid}/edit', [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('plans/{plan:uuid}', [PlanController::class, 'update'])->name('plans.update');
        Route::put('plans/{plan:uuid}/commissions', [PlanCommissionController::class, 'update'])->name('plans.commissions.update');

        Route::get('commissions', [CommissionPayoutController::class, 'index'])->name('commissions.index');
        Route::get('commissions/payouts', [CommissionPayoutController::class, 'history'])->name('commissions.payouts');
        Route::post('commissions/payouts', [CommissionPayoutController::class, 'store'])->name('commissions.pay');
        Route::post('commissions/payouts/{payout:uuid}/void', [CommissionPayoutController::class, 'void'])->name('commissions.void');
        Route::delete('plans/{plan:uuid}', [PlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('prospects', [ProspectController::class, 'index'])->name('prospects.index');
        Route::get('prospects/{prospect:uuid}', [ProspectController::class, 'show'])->name('prospects.show');
        Route::get('prospects/{prospect:uuid}/delete', [ProspectDeletionController::class, 'confirm'])->name('prospects.delete');
        Route::delete('prospects/{prospect:uuid}', [ProspectDeletionController::class, 'destroy'])
            ->middleware('throttle:5,1')
            ->name('prospects.destroy');
        Route::get('prospects/{prospect:uuid}/messages', [ClientMessageController::class, 'index'])->name('prospects.messages.index');
        Route::post('prospects/{prospect:uuid}/commissions/review', [CommissionReviewController::class, 'store'])->name('prospects.commissions.review');

        Route::get('organizations/{organization:uuid}/edit', [ClientController::class, 'edit'])->name('organizations.edit');
        Route::put('organizations/{organization:uuid}', [ClientController::class, 'update'])->name('organizations.update');

        Route::get('organizations/{organization:uuid}/agent', [AgentProfileController::class, 'edit'])->name('organizations.agent.edit');
        Route::put('organizations/{organization:uuid}/agent', [AgentProfileController::class, 'update'])->name('organizations.agent.update');

        Route::get('organizations/{organization:uuid}/phones', [PhoneNumberController::class, 'index'])->name('organizations.phones.index');
        Route::post('organizations/{organization:uuid}/phones', [PhoneNumberController::class, 'store'])->name('organizations.phones.store');
        Route::patch('phones/{phone:uuid}/toggle', [PhoneNumberController::class, 'toggle'])->name('phones.toggle');
        Route::delete('phones/{phone:uuid}', [PhoneNumberController::class, 'destroy'])->name('phones.destroy');

        Route::get('subscriptions/{subscription:uuid}/edit', [SubscriptionController::class, 'edit'])->name('subscriptions.edit');
        Route::put('subscriptions/{subscription:uuid}', [SubscriptionController::class, 'update'])->name('subscriptions.update');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user:uuid}', [UserController::class, 'show'])->name('users.show');
        Route::get('users/{user:uuid}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user:uuid}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user:uuid}/roles', [UserRoleController::class, 'store'])->name('users.roles.store');
        Route::get('users/{user:uuid}/delete', [UserDeletionController::class, 'confirm'])->name('users.delete');
        Route::delete('users/{user:uuid}', [UserDeletionController::class, 'destroy'])
            ->middleware('throttle:5,1')
            ->name('users.destroy');
        Route::post('users/{user:uuid}/password', [UserPasswordController::class, 'send'])
            ->middleware('throttle:10,1')
            ->name('users.password.send');
        Route::post('memberships/{membership:uuid}/retire', [UserRoleController::class, 'retire'])->name('memberships.retire');

        Route::get('markets/{market:uuid}/territories/create', [TerritoryController::class, 'create'])->name('markets.territories.create');
        Route::post('markets/{market:uuid}/territories', [TerritoryController::class, 'store'])->name('markets.territories.store');
        Route::get('territories/{territory:uuid}', [TerritoryController::class, 'show'])->name('territories.show');
        Route::get('territories/{territory:uuid}/edit', [TerritoryController::class, 'edit'])->name('territories.edit');
        Route::put('territories/{territory:uuid}', [TerritoryController::class, 'update'])->name('territories.update');
        Route::delete('territories/{territory:uuid}', [TerritoryController::class, 'destroy'])->name('territories.destroy');

        Route::get('territories/{territory:uuid}/teams/create', [TeamController::class, 'create'])->name('territories.teams.create');
        Route::post('territories/{territory:uuid}/teams', [TeamController::class, 'store'])->name('territories.teams.store');
        Route::get('teams/{team:uuid}', [TeamController::class, 'show'])->name('teams.show');
        Route::get('teams/{team:uuid}/edit', [TeamController::class, 'edit'])->name('teams.edit');
        Route::put('teams/{team:uuid}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('teams/{team:uuid}', [TeamController::class, 'destroy'])->name('teams.destroy');
    });