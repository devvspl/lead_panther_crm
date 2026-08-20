<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Builder\BuilderDashboardController;
use App\Http\Controllers\ChannelPartner\PartnerDashboardController;
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Livewire\Leads\LeadKanban;
use App\Livewire\Credits\CreditWallet;
use App\Livewire\Admin\AdminCredits;
use App\Livewire\Admin\WebhookLogs;
use App\Livewire\Admin\AuditLogBrowser;
use App\Livewire\Admin\OrganizationManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\Replacement\ReplacementQueue;
use App\Livewire\Replacement\ClientReplacementHistory;
use App\Livewire\Reports\ReportsContainer;
use App\Livewire\Distribution\DistributionRuleForm;
use App\Livewire\Distribution\TeamAvailability;
use App\Livewire\AccountManager\AccountManagerLeads;
use App\Livewire\Team\PartnerTeamManager;
use App\Livewire\Team\BuilderTeamManager;
use App\Livewire\Settings\OrganizationProfile;
use App\Livewire\Settings\IntegrationsManager;
use App\Livewire\Settings\UserInvite;

// Signed Public Proposal Viewer Route
Route::get('/proposals/view/{proposal}', [\App\Http\Controllers\ProposalViewerController::class, 'view'])->name('proposals.public_view')->middleware('signed');

// Public Landing & Legal Pages
Route::view('/', 'pages.landing')->name('landing');
Route::view('/privacy-policy', 'pages.privacy-policy')->name('privacy-policy');

// Pending Approval Route
Route::view('pending-approval', 'pages.auth.pending-approval')->middleware(['auth'])->name('auth.pending-approval');

// Authenticated Core Dashboard
Route::view('dashboard', 'pages.dashboard')->middleware(['auth', 'verified'])->name('dashboard');

// Impersonation Stop Route
Route::get('/impersonate/stop', [ImpersonationController::class, 'stop'])->middleware(['auth'])->name('impersonate.stop');

// Lead Kanban Routes
Route::get('/leads', LeadKanban::class)->middleware(['auth'])->name('leads.index');

Route::get('/leads/kanban', LeadKanban::class)->middleware(['auth'])->name('leads.kanban');

Route::get('/leads/upload', \App\Livewire\Leads\BulkLeadUpload::class)->middleware(['auth'])->name('leads.upload');

Route::get('/leads/download-template', function () {
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\LeadImportTemplateExport(), 'Lead_Import_Template.xlsx');
})->middleware(['auth'])->name('leads.download-template');

Route::get('/leads/download-errors/{batch}', function (\App\Models\UploadBatch $batch) {
    $errors = $batch->error_log ?? [];
    $filename = "upload_errors_batch_{$batch->id}.csv";

    return response()->streamDownload(function () use ($errors) {
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Row Number', 'Raw Data', 'Failure Reason']);

        foreach ($errors as $err) {
            fputcsv($output, [$err['row'] ?? '', $err['data'] ?? '', $err['reason'] ?? '']);
        }

        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth'])->name('leads.download-errors');

Route::get('/leads/upload-history', \App\Livewire\Leads\UploadHistory::class)->middleware(['auth'])->name('leads.upload-history');

// Client Credit Wallet Route
Route::get('/credits', CreditWallet::class)->middleware(['auth'])->name('credits.wallet');

// Distribution Rules & Team Availability Routes
Route::get('/projects/{project}/distribution', DistributionRuleForm::class)->middleware(['auth'])->name('distribution.project');

Route::get('/distribution/availability', TeamAvailability::class)->middleware(['auth'])->name('distribution.availability');

// Account Manager Dedicated Leads View
Route::get('/account-manager/leads', AccountManagerLeads::class)->middleware(['auth'])->name('account_manager.leads');

// Replacement Queue & Client History Routes
Route::get('/replacements', ReplacementQueue::class)->middleware(['auth'])->name('replacements.queue');

Route::get('/client/replacements', ClientReplacementHistory::class)->middleware(['auth'])->name('replacements.client');

// Reports Hub Route
Route::get('/reports', ReportsContainer::class)->middleware(['auth'])->name('reports.index');

// Settings Routes
Route::get('/settings/organization', OrganizationProfile::class)->middleware(['auth'])->name('settings.organization');

Route::get('/settings/integrations', IntegrationsManager::class)->middleware(['auth', 'role:Super Admin|super-admin|Builder|builder'])->name('settings.integrations');

Route::get('/settings/users', UserInvite::class)->middleware(['auth'])->name('settings.users');

// Document Download Signed Route
Route::get('/documents/download/{path}', [\App\Http\Controllers\DocumentDownloadController::class, 'download'])
    ->where('path', '.*')->middleware(['auth'])->name('documents.download');

// User Profile Route
Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

// Role-based Route Groups (Spatie Laravel-Permission)
Route::middleware(['auth', 'role:Super Admin|super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/credits', AdminCredits::class)->name('credits');
    Route::get('/webhook-logs', WebhookLogs::class)->name('webhook-logs');
    Route::get('/audit-logs', AuditLogBrowser::class)->name('audit-logs');
    Route::get('/failed-jobs', \App\Livewire\Admin\FailedJobsBrowser::class)->name('failed-jobs');
    Route::get('/recharge-requests', \App\Livewire\Admin\RechargeApprovalQueue::class)->name('recharge-requests');
    Route::get('/backups', \App\Livewire\Admin\BackupStatus::class)->name('backups');
    Route::get('/organizations', OrganizationManager::class)->name('organizations');
    Route::get('/users', UserManager::class)->name('users');
    Route::get('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');
    Route::get('/notification-templates', \App\Livewire\Admin\NotificationTemplates::class)->name('notification-templates');
    Route::get('/git-sync', \App\Livewire\Admin\GitSync::class)->name('git-sync');

    if (app()->environment(['local', 'staging', 'testing'])) {
        Route::get('/dev-tools', \App\Livewire\Admin\DevTools::class)->name('dev-tools');
    }
});

Route::middleware(['auth', 'role:Super Admin|super-admin|Builder|builder'])->prefix('builder')->name('builder.')->group(function () {
    Route::get('/dashboard', [BuilderDashboardController::class, 'index'])->name('dashboard');
    Route::get('/team', BuilderTeamManager::class)->name('team');
});

Route::middleware(['auth', 'role:Super Admin|super-admin|Channel Partner|channel-partner'])->prefix('partner')->name('partner.')->group(function () {
    Route::get('/dashboard', [PartnerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/team', PartnerTeamManager::class)->name('team');
});

Route::middleware(['auth', 'role:Super Admin|super-admin|Sales Executive|sales-executive'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/dashboard', [SalesDashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:Super Admin|super-admin|Account Manager|account-manager|Client|client'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
    Route::get('/leads', AccountManagerLeads::class)->name('leads');
});

// Theme Customization Offcanvas Routes
Route::middleware(['auth'])->prefix('settings/theme')->name('settings.theme.')->group(function () {
    Route::post('/', [\App\Http\Controllers\ThemeSettingsController::class, 'update'])->name('update');
    Route::post('/reset', [\App\Http\Controllers\ThemeSettingsController::class, 'reset'])->name('reset');
});

require __DIR__.'/auth.php';
