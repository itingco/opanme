-- Cek master barcode lokal
SELECT column_name, data_type, character_maximum_length
FROM information_schema.columns
WHERE table_name = 'item_barcodes'
ORDER BY ordinal_position;

-- Barcode wajib unique
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename IN ('item_barcodes', 'uom_ratios')
ORDER BY tablename, indexname;

-- Cek duplicate barcode (hasil seharusnya 0 row)
SELECT barcode, COUNT(*) AS jumlah
FROM item_barcodes
GROUP BY barcode
HAVING COUNT(*) > 1;
