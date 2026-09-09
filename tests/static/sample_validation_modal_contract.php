<?php
$root = dirname(__DIR__, 2);
$files = [
    "$root/resources/views/gerai/scan.blade.php" => [
        'sampling-validation-modal',
        'aria-modal="true"',
        'Validasi Stok Fisik',
        'sample-validation-actions',
        'sample-qty-difference',
        '>Kembali<',
    ],
    "$root/resources/js/sampling.js" => [
        'let validationPending = false',
        'busy || validationPending',
        "document.body.classList.add('sampling-validation-open')",
        "document.body.classList.remove('sampling-validation-open')",
        'barcodeInput.disabled = true',
        'updateDifference',
    ],
    "$root/resources/css/sampling.css" => [
        '.sampling-validation-modal',
        'position:fixed',
        '.sampling-modal-panel',
        'body.sampling-validation-open',
    ],
];

foreach ($files as $file => $needles) {
    $content = @file_get_contents($file) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            fwrite(STDERR, "FAIL {$file} missing {$needle}\n");
            exit(1);
        }
    }
}

echo "PASS sample_validation_modal_contract\n";
