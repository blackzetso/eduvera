<?php

namespace Tests\Unit\Dova;

use App\Services\Dova\DovaKnowledgeIndexBuilder;
use App\Services\Dova\DovaKnowledgeSyncService;
use App\Services\Website\WebsiteContentService;
use App\Support\Website\WebsiteDefaultsRepository;
use Mockery;
use Tests\TestCase;

class DovaKnowledgeCenterTest extends TestCase
{
    public function test_index_builder_extracts_school_name_from_defaults(): void
    {
        $builder = new DovaKnowledgeIndexBuilder;
        $records = $builder->build($builder->rawContentFromDefaults());

        $names = collect($records)
            ->where('source_slug', 'school_info')
            ->where('record_key', 'name')
            ->pluck('content')
            ->all();

        $defaultsName = WebsiteDefaultsRepository::load()['schoolInfo']['name'] ?? '';
        $this->assertNotEmpty($names);
        $this->assertContains("School name: {$defaultsName}", $names);
    }

    public function test_config_defines_required_knowledge_sources(): void
    {
        $slugs = array_keys(config('dova-knowledge.sources', []));

        foreach (['school_info', 'admissions', 'faq', 'news', 'events', 'policies'] as $required) {
            $this->assertContains($required, $slugs);
        }
    }

    public function test_sync_groups_include_everything_targets(): void
    {
        $groups = config('dova-knowledge.sync_groups', []);

        $this->assertArrayHasKey('cms', $groups);
        $this->assertArrayHasKey('website', $groups);
        $this->assertArrayHasKey('faq', $groups);
        $this->assertArrayHasKey('school_info', $groups);
        $this->assertArrayHasKey('admissions', $groups);
    }
}
