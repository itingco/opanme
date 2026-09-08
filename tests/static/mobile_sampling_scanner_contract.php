<?php

$root = dirname(__DIR__, 2);
$view = file_get_contents($root.'/resources/views/gerai/scan.blade.php');
$css = file_get_contents($root.'/resources/css/sampling.css');
$js = file_get_contents($root.'/resources/js/sampling.js');

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(str_contains($view, 'class="scan-context sampling-context"'), 'Gerai scan should use checker-style scan context');
$expect(str_contains($view, 'class="scan-feedback sampling-scan-feedback idle"'), 'Gerai scan should use checker-style feedback');
$expect(str_contains($view, 'class="scan-guide"'), 'Gerai camera should have checker-style scan guide');
$expect(str_contains($view, 'class="camera-start"'), 'Gerai camera button should use checker-style camera-start');
$expect(str_contains($view, 'class="manual-scan sampling-manual-scan"'), 'Gerai manual scan should use checker-style layout');
$expect(str_contains($view, 'class="change-location sampling-change-location"'), 'Gerai location should be collapsible like checker');
$expect(str_contains($view, 'sampling-history-details'), 'Sampling history should be collapsible');
$expect(str_contains($css, '@media(max-width:780px)'), 'Sampling CSS should have mobile breakpoint aligned with checker');
$expect(str_contains($css, '.sampling-scanner-page{max-width:720px'), 'Sampling scanner page should use checker-like width');
$expect(str_contains($css, '.sampling-result-card'), 'Sampling result should be a focused result card');
$expect(str_contains($js, "matchMedia('(max-width: 780px)')"), 'History should auto-collapse on mobile');
$expect(str_contains($js, 'scan-feedback sampling-scan-feedback'), 'Feedback JS should preserve checker-style feedback classes');

echo "mobile sampling scanner contract: PASS\n";
