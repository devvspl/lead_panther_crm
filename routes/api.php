<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Webhooks\MetaWebhookController;
use App\Http\Controllers\Api\Webhooks\GoogleWebhookController;
use App\Http\Controllers\Api\Webhooks\PortalWebhookController;
use App\Http\Controllers\Api\Webhooks\OwnedPortalWebhookController;
use App\Http\Controllers\Api\Webhooks\ManualLeadController;
use App\Http\Controllers\Api\LeadApiController;

Route::prefix('webhooks')->middleware('throttle:60,1')->group(function () {
    Route::match(['GET', 'POST'], '/meta/{portal_account}', [MetaWebhookController::class, 'handle'])->name('webhooks.meta');
    Route::post('/google/{portal_account}', [GoogleWebhookController::class, 'handle'])->name('webhooks.google');
    Route::post('/portal/{portal_account}', [PortalWebhookController::class, 'handle'])->name('webhooks.portal');
    Route::post('/owned/{portal_account}', [OwnedPortalWebhookController::class, 'handle'])->name('webhooks.owned');
});

Route::post('/leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');

Route::middleware('auth')->get('/leads/{lead}', [LeadApiController::class, 'show'])->name('api.leads.show');
