<?php

namespace Tests\Feature\Storage;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class DocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Account Manager']);
    }

    public function test_authorized_user_can_download_document_via_signed_url(): void
    {
        Storage::fake('documents');

        $org = Organization::create(['name' => 'Org Storage 1', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Storage 1']);

        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('Account Manager');

        $filePath = "{$client->id}/101/proposal_2026.pdf";
        Storage::disk('documents')->put($filePath, 'PDF proposal content sample text');

        $signedUrl = URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes(30),
            ['path' => $filePath]
        );

        $response = $this->actingAs($user)->get($signedUrl);
        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=proposal_2026.pdf');
    }

    public function test_unauthorized_other_tenant_user_blocked_from_downloading_document(): void
    {
        Storage::fake('documents');

        $org1 = Organization::create(['name' => 'Org Storage A', 'type' => 'builder']);
        $client1 = Client::create(['organization_id' => $org1->id, 'name' => 'Client Storage A']);

        $org2 = Organization::create(['name' => 'Org Storage B', 'type' => 'builder']);
        $client2 = Client::create(['organization_id' => $org2->id, 'name' => 'Client Storage B']);

        $userFromOrg2 = User::factory()->create(['organization_id' => $org2->id]);
        $userFromOrg2->assignRole('Account Manager');

        $filePath = "{$client1->id}/202/invoice_555.pdf";
        Storage::disk('documents')->put($filePath, 'Confidential invoice content for Tenant 1');

        $signedUrl = URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes(30),
            ['path' => $filePath]
        );

        // User from Tenant 2 hitting Tenant 1's signed URL must get 403 Forbidden!
        $response = $this->actingAs($userFromOrg2)->get($signedUrl);
        $response->assertStatus(403);
    }
}
