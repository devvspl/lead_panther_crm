<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class AssignOrganizationLeads implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public function __construct(public int $organizationId) { $this->afterCommit(); }
    public function handle(\App\Services\LeadDistributionService $service): void { $service->processOrganizationAssignments($this->organizationId); }
}
