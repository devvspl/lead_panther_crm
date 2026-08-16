<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\WebhookLog;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Project;
use App\Models\LeadSource;
use App\Models\LeadMetadata;
use App\Events\LeadCreated;
use App\Support\LeadMappers\MetaLeadMapper;
use App\Support\LeadMappers\GoogleLeadMapper;
use App\Support\LeadMappers\PortalLeadMapper;
use App\Support\LeadMappers\OwnedPortalLeadMapper;
use Throwable;

class ProcessInboundLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public WebhookLog $webhookLog;

    public function __construct(WebhookLog $webhookLog)
    {
        $this->webhookLog = $webhookLog;
    }

    public function handle(): void
    {
        try {
            $account = $this->webhookLog->portalAccount;
            $type = strtolower($account?->type ?: 'meta');
            $raw = is_array($this->webhookLog->payload) 
                ? $this->webhookLog->payload 
                : (json_decode($this->webhookLog->payload, true) ?: []);

            // 1. Normalize Payload via Source Mapper
            $mapped = match ($type) {
                'google' => (new GoogleLeadMapper())->map($raw),
                'portal' => (new PortalLeadMapper())->map($raw),
                'owned' => (new OwnedPortalLeadMapper())->map($raw),
                default => (new MetaLeadMapper())->map($raw),
            };

            // Check form mapping for project and campaign routing
            $formId = $mapped['form'] ?? null;
            $mapping = null;
            if ($account && $formId) {
                $mapping = \App\Models\LeadFormMapping::where('portal_account_id', $account->id)
                    ->where('form_id', $formId)
                    ->first();
            }

            // Determine Client & Project & LeadSource
            $client = Client::first();
            $clientId = $client?->id ?: 1;
            $project = $mapping?->project ?: Project::where('client_id', $clientId)->first();
            $projectId = $project?->id ?: 1;
            $clientId = $project?->client_id ?: $clientId;
            $campaignId = $mapping?->campaign_id;
            $leadSource = LeadSource::firstOrCreate(['name' => $mapped['source'] ?? $type]);

            $mobile = $mapped['mobile'] ?? '9999999999';
            $email = $mapped['email'] ?? 'unknown@example.com';

            // 2. 90-Day Duplicate Check
            $existingLead = Lead::where('client_id', $clientId)
                ->where('created_at', '>=', now()->subDays(90))
                ->where(function ($q) use ($mobile, $email) {
                    $q->where('mobile', $mobile);
                    if ($email && $email !== 'unknown@example.com') {
                        $q->orWhere('email', $email);
                    }
                })
                ->first();

            if ($existingLead) {
                // Insert duplicate metadata for audit traceability without creating duplicate Lead or consuming credits
                LeadMetadata::create([
                    'lead_id' => $existingLead->id,
                    'key' => 'duplicate_utm_source',
                    'value' => $mapped['raw_utm']['utm_source'] ?? $type,
                ]);

                LeadMetadata::create([
                    'lead_id' => $existingLead->id,
                    'key' => 'duplicate_raw_json',
                    'value' => json_encode($raw),
                ]);

                $this->webhookLog->update([
                    'processed' => true,
                    'error_message' => 'Duplicate lead detected (same mobile/email within 90 days). Logged metadata.',
                ]);
                return;
            }

            // 3. Create New Lead Master Record
            $lead = Lead::create([
                'lead_code' => Lead::generateUniqueLeadCode(),
                'client_id' => $clientId,
                'project_id' => $projectId,
                'campaign_id' => $campaignId,
                'lead_source_id' => $leadSource->id,
                'name' => $mapped['name'] ?? 'Inbound Lead',
                'mobile' => $mobile,
                'email' => $email,
                'city' => $mapped['city'] ?? 'Mumbai',
                'budget' => $mapped['budget'] ?? '₹75.0L',
                'property_type' => $mapped['property_type'] ?? '2 BHK',
                'requirement' => $mapped['requirement'] ?? 'Inbound lead captured via ' . ucfirst($type),
                'status' => 'new',
                'current_stage' => 'new',
                'assigned_to' => null,
            ]);

            // Save raw UTM / Attribution metadata
            LeadMetadata::create([
                'lead_id' => $lead->id,
                'key' => 'utm_source',
                'value' => $mapped['raw_utm']['utm_source'] ?? $type,
            ]);

            LeadMetadata::create([
                'lead_id' => $lead->id,
                'key' => 'raw_json',
                'value' => json_encode($raw),
            ]);

            $this->webhookLog->update([
                'processed' => true,
                'error_message' => null,
            ]);

            // Dispatch LeadCreated event to trigger Credit Reservation
            event(new LeadCreated($lead));
        } catch (Throwable $e) {
            $this->webhookLog->update([
                'error_message' => 'Processing exception: ' . $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
