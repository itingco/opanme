<?php

$servicePath = dirname(__DIR__, 2).'/app/Services/NonSystemQtyService.php';
if (! is_file($servicePath)) {
    fwrite(STDERR, "FAILED: NonSystemQtyService belum ada.\n");
    exit(1);
}
require_once $servicePath;

$service = new App\Services\NonSystemQtyService();

$cases = [
    ['qty' => 2, 'ratio' => 20, 'expected' => '40.0000'],
    ['qty' => 1.5, 'ratio' => 12, 'expected' => '18.0000'],
    ['qty' => 0, 'ratio' => 1, 'expected' => '0.0000'],
];

foreach ($cases as $case) {
    $actual = $service->toSmallest($case['qty'], $case['ratio']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, "FAILED: {$case['qty']} x {$case['ratio']} expected {$case['expected']}, got {$actual}.\n");
        exit(1);
    }
}

$thrown = false;
try {
    $service->toSmallest(1, 0);
} catch (InvalidArgumentException) {
    $thrown = true;
}
if (! $thrown) {
    fwrite(STDERR, "FAILED: ratio 0 harus ditolak.\n");
    exit(1);
}

echo "Non-System qty conversion passed.\n";
