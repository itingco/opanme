USE [AS_INGCO]
GO
/****** Object:  StoredProcedure [dbo].[USP_Dashboard_SisaStokWarehouse]    Script Date: 8/28/2026 11:45:48 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
/*
    USP_Dashboard_SisaStokWarehouse
    --------------------------------
    Jalankan script ini pada AS_INGCO dan AS_SMI.

    Penting:
    - Kategori BARANG DAGANGAN tidak menggunakan CategoryID statis.
    - CategoryID berbeda antar database (contoh: INGCO=14, SMI=1).
    - Kategori dicari berdasarkan StockCategoryCode = 'BDG'.
    - Price Level KHUSUS juga dicari berdasarkan nama, bukan ID statis.
*/

ALTER   PROCEDURE [dbo].[USP_Dashboard_SisaStokWarehouse]
    @PerDate DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;

    IF @PerDate IS NULL
        SET @PerDate = CAST(GETDATE() AS DATE);

    DECLARE
        @BdgCategoryID INT,
        @PriceLevelID INT;

    /* =========================================================
       RESOLVE CATEGORY BARANG DAGANGAN SECARA DINAMIS
       ========================================================= */
    SELECT TOP (1)
        @BdgCategoryID = SC.StockCategoryID
    FROM IC_StockCategories SC WITH (NOLOCK)
    WHERE UPPER(LTRIM(RTRIM(ISNULL(SC.StockCategoryCode, '')))) = 'BDG'
    ORDER BY SC.StockCategoryID;

    IF @BdgCategoryID IS NULL
    BEGIN
        RAISERROR('Stock Category dengan code BDG tidak ditemukan.', 16, 1);
        RETURN;
    END;

    /* =========================================================
       RESOLVE PRICE LEVEL KHUSUS SECARA DINAMIS
       ========================================================= */
    SELECT TOP (1)
        @PriceLevelID = PL.PriceLevelID
    FROM IC_PriceLevels PL WITH (NOLOCK)
    WHERE UPPER(LTRIM(RTRIM(ISNULL(PL.PriceLevelName, '')))) = 'KHUSUS'
    ORDER BY PL.PriceLevelID;

    IF @PriceLevelID IS NULL
    BEGIN
        RAISERROR('Price Level KHUSUS tidak ditemukan.', 16, 1);
        RETURN;
    END;

    /* =========================================================
       SELECTED ITEMS
       Mengikuti BARANG DAGANGAN / BDG pada database aktif.
       Disabled item tetap disertakan apabila masih memiliki stok.
       ========================================================= */
    CREATE TABLE #SelectedItems
    (
        ItemID INT NOT NULL PRIMARY KEY
    );

    INSERT INTO #SelectedItems (ItemID)
    SELECT I.ItemID
    FROM IC_Items I WITH (NOLOCK)
    WHERE I.ItemType < 16
      AND I.CategoryID = @BdgCategoryID;

    /* =========================================================
       SEMUA WAREHOUSE
       Warehouse disabled tetap disertakan apabila masih ada stok,
       termasuk warehouse SALE lama yang masih mempunyai balance.
       ========================================================= */
    CREATE TABLE #SelectedWarehouses
    (
        WarehouseID INT NOT NULL PRIMARY KEY
    );

    INSERT INTO #SelectedWarehouses (WarehouseID)
    SELECT W.WarehouseID
    FROM IC_Warehouses W WITH (NOLOCK);

    /* =========================================================
       HITUNG BALANCE PER WAREHOUSE
       ========================================================= */
    CREATE TABLE #ItemBalancePerLocation
    (
        ItemID      INT,
        WarehouseID INT,
        UOMLevel    TINYINT,
        Balance     MONEY
    );

    CREATE INDEX IX_ItemBalancePerLocation
        ON #ItemBalancePerLocation (ItemID, WarehouseID, UOMLevel);

    EXEC dbo.USP_IC_Items_Calculate
        @AllItems = 0,
        @PerDate = @PerDate,
        @OutputKind = 'PerLocationBalanceReports',
        @OutputTable = '#ItemBalancePerLocation',
        @ExtraJoinCondition = 'JOIN #SelectedWarehouses SWH ON SWH.WarehouseID = $[OutletField]',
        @IncludeBroken = 1,
        @IncludeConsignmentOutlets = 1,
        @GoodStockOnly = NULL;

    /* =========================================================
       KONVERSI SELURUH BALANCE KE SMALLEST UOM
       ========================================================= */
    SELECT
        B.ItemID,
        B.WarehouseID,
        SUM(
            dbo.UDF_Common_ConvertToSmallestUOMEx(
                I.Packing2,
                I.Packing3,
                I.Packing4,
                B.Balance,
                B.UOMLevel
            )
        ) AS SmallestOnHand
    INTO #Stock
    FROM #ItemBalancePerLocation B
    INNER JOIN IC_Items I WITH (NOLOCK)
        ON I.ItemID = B.ItemID
    GROUP BY
        B.ItemID,
        B.WarehouseID;

    CREATE UNIQUE CLUSTERED INDEX IX_Stock
        ON #Stock (ItemID, WarehouseID);

    /* =========================================================
       OUTPUT RINGKAS UNTUK LARAVEL STOCK AGING
       ========================================================= */
    SELECT
        I.ItemID,
        I.ItemCode AS [Item Code],
        I.ItemName AS [Item Name],

        S.WarehouseID,
        W.WarehouseCode AS [Warehouse Code],
        W.Name AS [Warehouse Name],

        ISNULL(
            CASE
                WHEN LP.PurchaseDate IS NULL THEN 0
                WHEN DATEDIFF(DAY, LP.PurchaseDate, @PerDate) < 0 THEN 0
                ELSE DATEDIFF(DAY, LP.PurchaseDate, @PerDate)
            END,
            0
        ) AS Age,

        CAST(ISNULL(S.SmallestOnHand, 0) AS DECIMAL(28, 4))
            AS [Smallest On Hand],

        CAST(ISNULL(PX.Price1, 0) AS DECIMAL(28, 4))
            AS [Price KHUSUS-1],

        CAST(
            ISNULL(S.SmallestOnHand, 0) * ISNULL(PX.Price1, 0)
            AS DECIMAL(28, 2)
        ) AS [Sisa Stok],

        ISNULL(I.Discontinue, 0) AS Discontinue,

        CASE
            WHEN ISNULL(I.Discontinue, 0) = 1 THEN 'DISCONTINUE'
            ELSE 'ACTIVE'
        END AS StatusItem,

        CASE
            WHEN UPPER(ISNULL(W.WarehouseCode, '')) LIKE '%SALE%'
              OR UPPER(ISNULL(W.Name, '')) LIKE '%SALE%'
                THEN 1
            ELSE 0
        END AS Clearance,

        CASE
            WHEN UPPER(ISNULL(W.WarehouseCode, '')) LIKE '%SALE%'
              OR UPPER(ISNULL(W.Name, '')) LIKE '%SALE%'
                THEN 'CLEARANCE SALE'
            ELSE 'BUKAN CLEARANCE SALE'
        END AS StatusClearanceSale,

        DB_NAME() AS DB

    FROM #Stock S

    INNER JOIN IC_Items I WITH (NOLOCK)
        ON I.ItemID = S.ItemID

    INNER JOIN IC_Warehouses W WITH (NOLOCK)
        ON W.WarehouseID = S.WarehouseID

    OUTER APPLY
    (
        SELECT TOP (1)
            P.PurchaseDate,
            P.PurchaseNumber
        FROM AP_PurchaseDetails PD WITH (NOLOCK)
        INNER JOIN AP_Purchases P WITH (NOLOCK)
            ON P.PurchaseID = PD.PurchaseID
        WHERE PD.ItemID = I.ItemID
          AND P.Class = 0
          AND ISNULL(P.Void, 0) <> 1
          AND P.IsPurchase = 1
          AND ISNULL(PD.LineTotal, 0) <> 0
          AND P.PurchaseDate < DATEADD(DAY, 1, @PerDate)
        ORDER BY
            P.PurchaseDate DESC,
            P.PurchaseNumber DESC
    ) LP

    OUTER APPLY
    (
        SELECT TOP (1)
            IPL.Price1
        FROM IC_ItemPriceList IPL WITH (NOLOCK)
        WHERE IPL.ItemID = I.ItemID
          AND IPL.PriceLevelID = @PriceLevelID
    ) PX

    WHERE ISNULL(S.SmallestOnHand, 0) <> 0

    ORDER BY
        W.WarehouseCode,
        I.ItemCode;
END;
