USE DB_AppHub;
GO

/* ========================================================================== */
/* ADMIN QTY OVERRIDE - INVOICE                                                */
/* ========================================================================== */

IF OBJECT_ID('dbo.warehouse_check_overrides', 'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.warehouse_check_overrides', 'admin_user_id') IS NULL
    BEGIN
        ALTER TABLE dbo.warehouse_check_overrides
            ADD admin_user_id BIGINT NULL;
    END;

    IF COL_LENGTH('dbo.warehouse_check_overrides', 'override_qty') IS NULL
    BEGIN
        ALTER TABLE dbo.warehouse_check_overrides
            ADD override_qty DECIMAL(18,4) NULL;
    END;

    IF COL_LENGTH('dbo.warehouse_check_overrides', 'override_base_qty') IS NULL
    BEGIN
        ALTER TABLE dbo.warehouse_check_overrides
            ADD override_base_qty DECIMAL(18,4) NULL;
    END;

    IF NOT EXISTS (
        SELECT 1
        FROM sys.foreign_keys
        WHERE name = 'FK_wco_admin'
          AND parent_object_id = OBJECT_ID('dbo.warehouse_check_overrides')
    )
    BEGIN
        ALTER TABLE dbo.warehouse_check_overrides
            ADD CONSTRAINT FK_wco_admin
            FOREIGN KEY (admin_user_id)
            REFERENCES dbo.warehouse_users(id);
    END;
END;
GO

/* ========================================================================== */
/* ADMIN QTY OVERRIDE - GOOD TRANSFER                                          */
/* ========================================================================== */

IF OBJECT_ID('dbo.good_transfer_check_overrides', 'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.good_transfer_check_overrides', 'admin_user_id') IS NULL
    BEGIN
        ALTER TABLE dbo.good_transfer_check_overrides
            ADD admin_user_id BIGINT NULL;
    END;

    IF COL_LENGTH('dbo.good_transfer_check_overrides', 'override_qty') IS NULL
    BEGIN
        ALTER TABLE dbo.good_transfer_check_overrides
            ADD override_qty DECIMAL(18,4) NULL;
    END;

    IF COL_LENGTH('dbo.good_transfer_check_overrides', 'override_base_qty') IS NULL
    BEGIN
        ALTER TABLE dbo.good_transfer_check_overrides
            ADD override_base_qty DECIMAL(18,4) NULL;
    END;

    IF NOT EXISTS (
        SELECT 1
        FROM sys.foreign_keys
        WHERE name = 'FK_gtco_admin'
          AND parent_object_id = OBJECT_ID('dbo.good_transfer_check_overrides')
    )
    BEGIN
        ALTER TABLE dbo.good_transfer_check_overrides
            ADD CONSTRAINT FK_gtco_admin
            FOREIGN KEY (admin_user_id)
            REFERENCES dbo.warehouse_users(id);
    END;
END;
GO

PRINT 'Patch 05_admin_qty_override.sql selesai.';
GO
