<?php

namespace Tests\Feature\Settings;

use App\Events\LeadCreated;
use App\Jobs\{FetchMetaCampaignLeads, ProcessInboundLeadJob};
use App\Livewire\Settings\MetaAdAccounts;
use App\Models\{Campaign, Client, Lead, LeadFormMapping, LeadMetadata, LeadSource, MetaAdAccount, MetaAdRecord, PortalAccount, Project, User, WebhookLog};
use App\Services\{MetaCampaignLeadService, MetaGraphException};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Event, Http, Queue};
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MetaCampaignLeadFetchTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        Queue::fake();
        Event::fake([LeadCreated::class]);
        $client = Client::create(['name' => 'Destination', 'status' => 'active']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project']);
        $source = LeadSource::firstOrCreate(['name' => 'meta']);
        $crm = Campaign::create(['project_id' => $project->id, 'lead_source_id' => $source->id, 'name' => 'CRM Campaign']);
        $page = PortalAccount::create(['name' => 'Page', 'type' => 'meta', 'status' => 'active']);
        $page->setCredential('page_id', 'page1');
        $page->setCredential('access_token', 'page-secret');
        $account = MetaAdAccount::create(['portal_account_id' => $page->id, 'name' => 'Ads', 'ad_account_id' => '123', 'access_token' => 'ads-secret', 'status' => 'connected']);
        $account->pages()->attach($page);
        $remote = $account->records()->create(['kind' => 'campaign', 'remote_id' => 'campaign1', 'campaign_id' => 'campaign1', 'ad_account_id' => '123', 'name' => 'Running campaign', 'status' => 'ACTIVE', 'data' => [], 'synced_at' => now(), 'crm_campaign_id' => $crm->id]);
        return compact('client', 'project', 'crm', 'page', 'account', 'remote');
    }

    private function lead(string $id, string $campaign = 'campaign1', string $mobile = '9811111111'): array
    {
        return ['id' => $id, 'campaign_id' => $campaign, 'form_id' => 'form1', 'ad_id' => 'ad1', 'field_data' => [
            ['name' => 'full_name', 'values' => ['Test Lead']], ['name' => 'phone_number', 'values' => [$mobile]],
        ]];
    }

    private function fakeHierarchy(?\Closure $leads = null): void
    {
        Http::fake(function ($request) use ($leads) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/campaign1/adsets')) {
                if (!empty($request['after'])) return Http::response(['data' => []]);
                return Http::response(['data' => [['id' => 'set1', 'campaign_id' => 'campaign1', 'account_id' => '123']],
                    'paging' => ['next' => 'https://graph.facebook.com/ignored?after=SET_NEXT', 'cursors' => ['after' => 'SET_NEXT']]]);
            }
            if (str_ends_with($path, '/set1/ads')) return Http::response(['data' => [[
                'id' => 'ad1', 'account_id' => '123', 'campaign_id' => 'campaign1', 'adset_id' => 'set1',
                'creative' => ['id' => 'creative1', 'object_story_spec' => ['page_id' => 'page1', 'link_data' => ['child_attachments' => [
                    ['call_to_action' => ['value' => ['lead_gen_form_id' => 'form1']]],
                ]]]],
            ]]]);
            if (str_ends_with($path, '/form1/leads')) {
                $this->assertSame('Bearer page-secret', $request->header('Authorization')[0]);
                return $leads ? $leads($request) : Http::response(['data' => [$this->lead('lead1')]]);
            }
            $this->fail('Unexpected API request: '.$path);
        });
    }

    private function drain(MetaAdRecord $record): void
    {
        for ($i = 0; $i < 25; $i++) {
            if (!app(MetaCampaignLeadService::class)->tick($record->fresh())) return;
        }
        $this->fail('Campaign import did not finish in bounded batches.');
    }

    public function test_campaign_import_uses_only_associated_forms_filters_shared_form_leads_and_paginates(): void
    {
        extract($this->context());
        $other = Client::create(['name' => 'Legacy form destination']);
        $mapping = LeadFormMapping::create(['portal_account_id' => $page->id, 'form_id' => 'form1', 'form_name' => 'Existing form', 'client_id' => $other->id, 'status' => 'ACTIVE']);
        $this->fakeHierarchy(function ($request) {
            if (!empty($request['after'])) return Http::response(['data' => [$this->lead('lead2', 'campaign1', '9822222222')]]);
            return Http::response(['data' => [$this->lead('lead1'), $this->lead('lead1'), $this->lead('wrong', 'otherCampaign'), ['id' => 'organic', 'form_id' => 'form1']],
                'paging' => ['next' => 'https://graph.facebook.com/ignored?after=LEAD_NEXT', 'cursors' => ['after' => 'LEAD_NEXT']]]);
        });
        $service = app(MetaCampaignLeadService::class);
        $service->start($remote);
        Queue::assertPushed(FetchMetaCampaignLeads::class, fn ($job) => $job->recordId === $remote->id);
        $this->drain($remote);
        $this->assertSame(2, Lead::count());
        $this->assertSame(2, Lead::where('client_id', $client->id)->where('campaign_id', $crm->id)->count());
        $this->assertSame($other->id, $mapping->fresh()->client_id);
        $this->assertSame('completed', $remote->fresh()->lead_fetch_state['status']);
        $this->assertSame(2, $remote->fresh()->lead_fetch_state['skipped']);
        $this->assertSame(2, LeadMetadata::where('key', 'meta_leadgen_id')->count());
        Event::assertDispatchedTimes(LeadCreated::class, 2);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/leadgen_forms'));
        // Re-fetching history reuses the same global Meta ID deduplication as webhooks.
        $service->start($remote->fresh());
        $this->drain($remote);
        $this->assertSame(2, Lead::count());
    }

    public function test_rate_limited_history_resumes_saved_cursor_without_duplicate_leads(): void
    {
        extract($this->context());
        $fail = true;
        $this->fakeHierarchy(function ($request) use (&$fail) {
            if (!empty($request['after'])) {
                if ($fail) return Http::response(['error' => ['code' => 4]], 429, ['Retry-After' => 300]);
                return Http::response(['data' => [$this->lead('lead2', 'campaign1', '9822222222')]]);
            }
            return Http::response(['data' => [$this->lead('lead1')], 'paging' => ['next' => 'https://graph.facebook.com/x?after=NEXT', 'cursors' => ['after' => 'NEXT']]]);
        });
        $service = app(MetaCampaignLeadService::class);
        $service->start($remote);
        try { $this->drain($remote); $this->fail('Expected a rate limit.'); }
        catch (MetaGraphException $e) { $this->assertTrue($e->retryable); }
        $this->assertSame('NEXT', $remote->fresh()->lead_fetch_state['after']);
        $this->assertSame(1, Lead::count());
        $fail = false;
        $service->start($remote->fresh());
        $this->drain($remote);
        $this->assertSame(2, Lead::count());
        $this->assertSame(2, $remote->fresh()->lead_fetch_state['created']);
    }

    public function test_future_webhook_uses_campaign_mapping_without_changing_shared_form_mapping(): void
    {
        extract($this->context());
        $other = Client::create(['name' => 'Other']);
        LeadFormMapping::create(['portal_account_id' => $page->id, 'form_id' => 'form1', 'form_name' => 'Shared form', 'client_id' => $other->id]);
        Http::fake(['*' => Http::response($this->lead('future1'))]);
        $log = WebhookLog::create(['portal_account_id' => $page->id, 'received_at' => now(), 'payload' => json_encode(['entry' => [['id' => 'page1', 'changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => 'future1', 'form_id' => 'form1', 'ad_id' => 'ad1']]]]]])]);
        (new ProcessInboundLeadJob($log))->handle();
        $this->assertDatabaseHas('leads', ['client_id' => $client->id, 'campaign_id' => $crm->id]);
        (new ProcessInboundLeadJob($log->fresh()))->handle();
        $this->assertSame(1, Lead::count());
    }

    public function test_mapping_rejects_project_from_another_client(): void
    {
        extract($this->context());
        $other = Client::create(['name' => 'Other']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MetaCampaignLeadService::class)->saveMapping($remote, $other->id, $project->id, $crm->id);
    }

    public function test_unconnected_creative_page_fails_without_importing_any_leads(): void
    {
        extract($this->context());
        $page->setCredential('page_id', 'unrelatedPage');
        $this->fakeHierarchy();
        app(MetaCampaignLeadService::class)->start($remote);
        try { $this->drain($remote); $this->fail('Expected unconnected Page rejection.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Page', $e->getMessage()); }
        $this->assertSame(0, Lead::count());
        $this->assertSame('failed', $remote->fresh()->lead_fetch_state['status']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/form1/leads'));
    }

    public function test_failed_webhook_lead_retrieval_does_not_create_placeholder_lead(): void
    {
        extract($this->context());
        Http::fake(['*' => Http::response(['error' => ['code' => 200]], 403)]);
        $log = WebhookLog::create(['portal_account_id' => $page->id, 'received_at' => now(), 'payload' => json_encode(['leadgen_id' => 'lead1', 'form_id' => 'form1'])]);
        try { (new ProcessInboundLeadJob($log))->handle(); $this->fail('Expected permission failure.'); }
        catch (MetaGraphException) { $this->assertSame(0, Lead::count()); }
        $this->assertFalse((bool) $log->fresh()->processed);
    }

    public function test_mapping_modal_scopes_dropdowns_and_campaigns_default_to_active(): void
    {
        extract($this->context());
        Role::findOrCreate('Super Admin');
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        Livewire::actingAs($admin)->test(MetaAdAccounts::class, ['integrationId' => $page->id])
            ->call('showRecords', $account->id)->assertSet('statusFilter', 'ACTIVE')
            ->assertSee('Fetch Leads')->call('openCampaignMapping', $remote->id)
            ->assertSet('mappingClientId', (string) $client->id)->assertSet('mappingProjectId', (string) $project->id)
            ->call('saveCampaignMapping')->assertHasNoErrors();
    }
}
