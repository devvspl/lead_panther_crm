<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\{Organization, InternalProcessingStatus};
use App\Services\LeadDistributionService;
use Livewire\Attributes\Locked;

class InternalProcessingSettings extends Component
{
    #[Locked] public int $organizationId;
    public bool $enabled = false;
    public ?int $teamId = null;
    public array $memberIds = [];
    public array $statusCodes = [];
    public array $forwardStatuses = [];
    public string $assignmentMethod = '';
    public bool $autoForward = false;
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $dateRange = 'all';

    private function guard(): void { abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403); }
    public function mount(int $organizationId): void
    {
        $this->guard(); $this->organizationId = $organizationId;
        $org = Organization::findOrFail($organizationId); $config = $org->internal_processing_config ?? [];
        $this->enabled = $org->internal_processing_enabled; $this->teamId = $org->internal_processing_team_id;
        $this->memberIds = $config['member_ids'] ?? [];
        $this->statusCodes = $config['status_codes'] ?? InternalProcessingStatus::orderBy('sort_order')->pluck('code')->all();
        $this->forwardStatuses = $config['forward_statuses'] ?? InternalProcessingStatus::where('default_forward', true)->pluck('code')->all();
        $this->assignmentMethod = $config['assignment_method'] ?? ''; $this->autoForward = $config['auto_forward'] ?? false;
    }
    public function updatedTeamId(): void { $this->memberIds = []; }
    public function saveSettings(): void
    {
        $this->guard();
        try {
            app(LeadDistributionService::class)->configureInternalProcessing(Organization::findOrFail($this->organizationId), [
                'enabled' => $this->enabled, 'team_id' => $this->teamId, 'member_ids' => $this->memberIds,
                'status_codes' => $this->statusCodes, 'forward_statuses' => $this->forwardStatuses, 'assignment_method' => $this->assignmentMethod, 'auto_forward' => $this->autoForward], auth()->user());
            $this->dispatch('toast', type: 'success', message: 'Internal processing settings saved for new leads.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->validator->errors()->first()); throw $e;
        }
    }
    public function render()
    {
        $this->guard();
        $this->validate(['dateFrom' => 'nullable|date', 'dateTo' => 'nullable|date'.($this->dateFrom ? '|after_or_equal:dateFrom' : '')]);
        $service = app(LeadDistributionService::class); $org = Organization::findOrFail($this->organizationId);
        $team = $this->teamId ? $service->internalTeams()->find($this->teamId) : null;
        $tz = $org->delivery_timezone ?: config('app.timezone');
        $today = \Carbon\Carbon::now($tz);
        [$from, $to] = match ($this->dateRange) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'yesterday' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay()],
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last7' => [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'lastMonth' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => [$this->dateFrom ? \Carbon\Carbon::parse($this->dateFrom, $tz)->startOfDay() : null, $this->dateTo ? \Carbon\Carbon::parse($this->dateTo, $tz)->endOfDay() : null],
            default => [null, null],
        };
        return view('livewire.admin.internal-processing-settings', ['teams' => $service->internalTeams()->orderBy('name')->get(),
            'members' => $team ? $service->internalTeamMembers($team)->orderBy('name')->get() : collect(), 'statuses' => InternalProcessingStatus::orderBy('sort_order')->get(),
            'stats' => $service->internalStatistics($org, $from?->setTimezone(config('app.timezone'))->toDateTimeString(), $to?->setTimezone(config('app.timezone'))->toDateTimeString())]);
    }
}
