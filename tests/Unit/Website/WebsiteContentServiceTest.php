<?php

namespace Tests\Unit\Website;

use App\Services\Website\WebsiteContentService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WebsiteContentServiceTest extends TestCase
{
    public function test_for_landing_always_builds_payload_instead_of_empty_array(): void
    {
        Cache::flush();

        $service = $this->createPartialMock(WebsiteContentService::class, ['buildFromDatabase']);
        $service->method('buildFromDatabase')
            ->with(false)
            ->willReturn([
                'schoolInfo' => ['hero' => ['pill' => 'من قاعدة البيانات']],
            ]);

        $this->app->instance(WebsiteContentService::class, $service);

        $payload = app(WebsiteContentService::class)->forLanding();

        $this->assertSame('من قاعدة البيانات', $payload['schoolInfo']['hero']['pill']);
    }
}
