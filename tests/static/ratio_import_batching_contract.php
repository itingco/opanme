<?php

$path = __DIR__.'/../../app/Services/RatioImportService.php';
$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "FAIL: RatioImportService.php cannot be read\n");
    exit(1);
}

foreach ([
    'lookup array chunk' => 'array_chunk($itemCodes, self::LOOKUP_CHUNK_SIZE)',
    'upsert array chunk' => 'array_chunk(array_values($payloadByKey), self::UPSERT_CHUNK_SIZE)',
] as $label => $needle) {
    if (! str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: missing {$label}\n");
        exit(1);
    }
}

if (! preg_match('/private const LOOKUP_CHUNK_SIZE = (\d+);/', $source, $lookupMatch)) {
    fwrite(STDERR, "FAIL: lookup chunk size constant missing\n");
    exit(1);
}

if (! preg_match('/private const UPSERT_CHUNK_SIZE = (\d+);/', $source, $upsertMatch)) {
    fwrite(STDERR, "FAIL: upsert chunk size constant missing\n");
    exit(1);
}

$lookupChunk = (int) $lookupMatch[1];
$upsertChunk = (int) $upsertMatch[1];

// SQL Server supports at most 2100 bound parameters per statement.
if ($lookupChunk > 2000) {
    fwrite(STDERR, "FAIL: lookup chunk is too close to/exceeds SQL Server's 2100 parameter limit\n");
    exit(1);
}

// ItemRatio upsert writes ItemCode, UOM, Ratio = 3 bindings/row.
if (($upsertChunk * 3) > 2000) {
    fwrite(STDERR, "FAIL: upsert chunk can exceed safe SQL Server parameter budget\n");
    exit(1);
}

if (str_contains($source, "'created_at' => now()") || str_contains($source, "'updated_at' => now()")) {
    fwrite(STDERR, "FAIL: DB_AppHub ItemRatio has no timestamp columns; payload should stay lean\n");
    exit(1);
}

if (! str_contains($source, "['ratio']")) {
    fwrite(STDERR, "FAIL: upsert update columns should only contain ratio\n");
    exit(1);
}

echo "ratio import batching contract: PASS (lookup={$lookupChunk}, upsert={$upsertChunk}, max_upsert_bindings=".($upsertChunk * 3).")\n";
