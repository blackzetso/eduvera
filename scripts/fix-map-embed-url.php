<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Website\WebsiteSetting;
use App\Services\Website\WebsiteContentService;
use App\Support\Website\WebsiteMapEmbed;

$schoolInfo = WebsiteSetting::getValue('school_info', []);
$schoolInfo = WebsiteMapEmbed::normalizeSchoolInfo($schoolInfo);
WebsiteSetting::putValue('school_info', $schoolInfo);
app(WebsiteContentService::class)->clearCache();

echo ($schoolInfo['contact']['mapEmbedUrl'] ?? 'none').PHP_EOL;
