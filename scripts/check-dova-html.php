<?php

$html = @file_get_contents('http://127.0.0.1:8000/');

if ($html === false) {
    echo "FAIL: could not fetch homepage\n";
    exit(1);
}

$hasCopilot = str_contains($html, 'dovaCopilot');
$copilotEnabled = (bool) preg_match('/dovaCopilot[^}]*"enabled"\s*:\s*true/', $html);

echo 'dovaCopilot in page: '.($hasCopilot ? 'YES' : 'NO')."\n";
echo 'dova enabled in props: '.($copilotEnabled ? 'YES' : 'NO')."\n";

$orb = @file_get_contents('http://127.0.0.1:8000/brand/dova/orb.svg');
echo 'orb.svg reachable: '.($orb !== false && str_contains($orb, '<svg') ? 'YES' : 'NO')."\n";

if (preg_match('/data-page="([^"]+)"/', $html, $m)) {
    $json = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    $page = json_decode($json, true);
    $copilot = $page['props']['dovaCopilot'] ?? null;
    echo 'parsed enabled: '.(($copilot['enabled'] ?? false) ? 'YES' : 'NO')."\n";
    echo 'parsed portal: '.($copilot['portal'] ?? 'n/a')."\n";
    echo 'sample questions: '.count($copilot['sampleQuestions'] ?? [])."\n";
}

exit($hasCopilot && $copilotEnabled ? 0 : 1);
