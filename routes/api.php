<?php

use App\Http\Controllers\Api\ProspectTransitionController;
use App\Http\Controllers\Api\ProspectController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ProspectAssignmentController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\RetellFunctionController;
use App\Http\Controllers\Api\RetellWebhookController;
use App\Http\Middleware\VerifyRetellSignature;
use App\Http\Controllers\Api\PaymentRefundController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\CommercialTreeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\ChangeRequestController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\TerritoryController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\SalesTeamController;
use App\Http\Controllers\Api\PersonInvitationController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\QuoteIssuanceController;
use App\Http\Controllers\Api\ClientSubscriptionController;

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/v1/webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::middleware(VerifyRetellSignature::class)->group(function () {
    Route::post('/v1/webhooks/retell', [RetellWebhookController::class, 'handle']);
    Route::post('/v1/retell/functions/registrar-pedido', [RetellFunctionController::class, 'registrarPedido']);
});
Route::post('/v1/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'resolve.membership'])->prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/avatar', [AuthController::class, 'updateAvatar']);
    

    Route::get('/plans', [PlanController::class, 'index']);

    Route::post('/prospects/{prospect:uuid}/transition', [ProspectTransitionController::class, 'store']);

    Route::apiResource('prospects', ProspectController::class)->only(['index', 'store', 'show', 'update'])->parameters(['prospects' => 'prospect:uuid']);

    Route::get('/prospects/{prospect:uuid}/activities', [ActivityController::class, 'index']);
    Route::post('/prospects/{prospect:uuid}/activities', [ActivityController::class, 'store']);
    Route::post('/prospects/{prospect:uuid}/issue-quote', [QuoteIssuanceController::class, 'store']);

    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::patch('/admin/users/{user:uuid}', [AdminUserController::class, 'update']);
    Route::post('/admin/users/{user:uuid}/resend-invitation', [AdminUserController::class, 'resendInvitation']);
    Route::post('/admin/users/{user:uuid}/reset-password', [AdminUserController::class, 'resetPassword']);
    Route::post('/admin/users/{user:uuid}/roles', [AdminUserController::class, 'addRole']);
    Route::delete('/admin/memberships/{membership:uuid}', [AdminUserController::class, 'destroyMembership']);
    Route::post('/opportunities/{opportunity:uuid}/resend-payment-link', [QuoteIssuanceController::class, 'resend']);
    Route::get('/opportunities/{opportunity:uuid}/checkout-url', [QuoteIssuanceController::class, 'checkoutUrl']);

    Route::get('/demos', [DemoController::class, 'index']);
    Route::post('/prospects/{prospect:uuid}/demos', [DemoController::class, 'store']);
    Route::patch('/demos/{demo:uuid}', [DemoController::class, 'update']);

    Route::get('/opportunities', [OpportunityController::class, 'index']);
    Route::post('/prospects/{prospect:uuid}/opportunities', [OpportunityController::class, 'store']);
    Route::post('/opportunities/{opportunity:uuid}/quotes', [QuoteController::class, 'store']);

    Route::post('/prospects/{prospect:uuid}/assign', [ProspectAssignmentController::class, 'store']);

    Route::post('/payments/{payment:uuid}/refund', [PaymentRefundController::class, 'store']);

    Route::post('/opportunities/{opportunity:uuid}/checkout', [CheckoutController::class, 'store']);

    Route::get('/dashboard/seller', [DashboardController::class, 'seller']);
    Route::get('/dashboard/supervisor', [DashboardController::class, 'supervisor']);
    Route::get('/dashboard/manager', [DashboardController::class, 'manager']);

    Route::get('/team/members', [TeamController::class, 'members']);
    Route::get('/team/members/{uuid}', [TeamController::class, 'member']);
    Route::get('/team/funnel', [TeamController::class, 'funnel']);

    Route::get('/tree', [CommercialTreeController::class, 'index']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{uuid}/read', [NotificationController::class, 'markRead']);
    Route::post('/devices', [DeviceTokenController::class, 'store']);
    Route::post('/change-requests', [ChangeRequestController::class, 'store']);

    Route::get('/commissions', [CommissionController::class, 'index']);
    Route::get('/sales', [SaleController::class, 'index']);

    Route::get('/territories', [TerritoryController::class, 'index']);
    Route::post('/territories', [TerritoryController::class, 'store']);
    Route::patch('/territories/{territory:uuid}', [TerritoryController::class, 'update']);
    Route::delete('/territories/{territory:uuid}', [TerritoryController::class, 'destroy']);
    Route::post('/territories/{territory:uuid}/assign-manager', [TerritoryController::class, 'assignManager']);
    Route::get('/territories/{territory:uuid}/supervisors', [TerritoryController::class, 'supervisors']);

    Route::get('/sales-teams', [SalesTeamController::class, 'index']);
    Route::post('/sales-teams', [SalesTeamController::class, 'store']);
    Route::patch('/sales-teams/{team:uuid}', [SalesTeamController::class, 'update']);
    Route::post('/sales-teams/{team:uuid}/assign-supervisor', [SalesTeamController::class, 'assignSupervisor']);
    Route::get('/sales-teams/{team:uuid}/sellers', [SalesTeamController::class, 'sellers']);

    Route::post('/people', [PersonInvitationController::class, 'store']);

    Route::get('/memberships/search', [MembershipController::class, 'search']);

    Route::apiResource('markets', MarketController::class)->only(['index', 'store', 'update'])->parameters(['markets' => 'market:uuid']);
    Route::get('/markets/{market:uuid}/managers', [MarketController::class, 'managers']);

    Route::get('/client/subscription', [ClientSubscriptionController::class, 'show']);
    
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});