<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicDocsTest extends TestCase
{
    public function test_docs_page_is_publicly_accessible_without_authentication(): void
    {
        $response = $this->get('/docs');
        $response->assertOk();
        $response->assertSee('Lead Panther CRM Setup &amp; Operations Manual', false);
        $response->assertSee('Multi-Source Webhook &amp; API Integration', false);
        $response->assertSee('Portal User Guide: How to Operate by Role', false);
        $response->assertSee('90-Day Deduplication Rules', false);
    }

    public function test_docs_aliases_resolve_properly(): void
    {
        $this->get('/documentation')->assertOk();
        $this->get('/developer/docs')->assertOk();
    }
}
