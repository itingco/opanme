/* Test query Good Transfer + UOM + CheckedBy/CheckedDateTime */
SELECT TOP 10
    MUT.MutationID,
    MUT.MutationNumber,
    MUT.MutationDate,
    ASAL.Name AS SourceWarehouse,
    TUJUAN.Name AS DestinationWarehouse,
    ITEM.ItemCode,
    ITEM.ItemName,
    UOM.UOMCode,
    DET.Quantity,
    MUT.CheckedBy,
    MUT.CheckedDateTime
FROM IC_Mutations MUT
LEFT JOIN IC_MutationDetails DET
    ON DET.MutationID = MUT.MutationID
LEFT JOIN IC_Warehouses ASAL
    ON ASAL.WarehouseID = MUT.SourceWarehouseID
LEFT JOIN IC_Warehouses TUJUAN
    ON TUJUAN.WarehouseID = MUT.DestinationWarehouseID
LEFT JOIN IC_Items ITEM
    ON ITEM.ItemID = DET.ItemID
LEFT JOIN IC_UOM UOM
    ON UOM.UOMID =
        CASE DET.UOMLevel
            WHEN 1 THEN ITEM.UOMID1
            WHEN 2 THEN ITEM.UOMID2
            WHEN 3 THEN ITEM.UOMID3
            WHEN 4 THEN ITEM.UOMID4
        END
ORDER BY MUT.MutationDate DESC, MUT.MutationID DESC;
