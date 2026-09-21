USE DB_AppHub;
GO

/* ========================================================================== */
/* A. FIX EXISTING INVOICE CHECK SCHEMA                                       */
/* ========================================================================== */

IF OBJECT_ID('dbo.warehouse_check_scan_errors', 'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.warehouse_check_scan_errors', 'resolved_uom_code') IS NULL
    BEGIN
        ALTER TABLE dbo.warehouse_check_scan_errors
            ADD resolved_uom_code NVARCHAR(50) NULL;
    END;

    IF COL_LENGTH('dbo.warehouse_check_scan_errors', 'error_message') IS NULL
    BEGIN
        ALTER TABLE dbo.warehouse_check_scan_errors
            ADD error_message NVARCHAR(500) NULL;
    END;

    IF COL_LENGTH('dbo.warehouse_check_scan_errors', 'message') IS NOT NULL
    BEGIN
        EXEC('UPDATE dbo.warehouse_check_scan_errors
              SET error_message = COALESCE(error_message, message)
              WHERE error_message IS NULL;');
    END;
END;
GO

-- Service existing memang memperbolehkan NO RATIO -> NULL -> Supervisor Override.
-- Pastikan schema lama tidak menolak NULL tersebut.
IF OBJECT_ID('dbo.warehouse_check_details', 'U') IS NOT NULL
BEGIN
    ALTER TABLE dbo.warehouse_check_details
        ALTER COLUMN invoice_uom_ratio DECIMAL(18,4) NULL;

    ALTER TABLE dbo.warehouse_check_details
        ALTER COLUMN expected_base_qty DECIMAL(18,4) NULL;
END;
GO

/* ========================================================================== */
/* B. GOOD TRANSFER CHECK TABLES                                               */
/* ========================================================================== */

IF OBJECT_ID('dbo.good_transfer_check_headers', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.good_transfer_check_headers (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        company NVARCHAR(10) NOT NULL,
        source_database NVARCHAR(100) NOT NULL,
        mutation_id BIGINT NOT NULL,
        mutation_number NVARCHAR(100) NOT NULL,
        mutation_date DATE NULL,
        source_warehouse_name NVARCHAR(255) NULL,
        destination_warehouse_name NVARCHAR(255) NULL,
        picker_user_id BIGINT NOT NULL,
        checker_user_id BIGINT NOT NULL,
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_gtch_status DEFAULT 'DRAFT',
        started_at DATETIME2 NOT NULL,
        completed_at DATETIME2 NULL,
        source_updated_at DATETIME2 NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_gtch_picker
            FOREIGN KEY (picker_user_id) REFERENCES dbo.warehouse_users(id),
        CONSTRAINT FK_gtch_checker
            FOREIGN KEY (checker_user_id) REFERENCES dbo.warehouse_users(id),
        CONSTRAINT CK_gtch_status
            CHECK (status IN ('DRAFT','FINALIZING','COMPLETED'))
    );

    CREATE INDEX IX_gtch_company_mutation
        ON dbo.good_transfer_check_headers(company, mutation_number);

    CREATE INDEX IX_gtch_status_started
        ON dbo.good_transfer_check_headers(status, started_at);
END;
GO

IF OBJECT_ID('dbo.good_transfer_check_details', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.good_transfer_check_details (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        check_header_id BIGINT NOT NULL,
        source_detail_id BIGINT NOT NULL,
        item_code NVARCHAR(100) NOT NULL,
        item_name NVARCHAR(255) NULL,
        uom_code NVARCHAR(50) NULL,
        expected_qty DECIMAL(18,4) NOT NULL,
        transfer_uom_ratio DECIMAL(18,4) NULL,
        expected_base_qty DECIMAL(18,4) NULL,
        scanned_base_qty DECIMAL(18,4) NOT NULL
            CONSTRAINT DF_gtcd_scanned DEFAULT 0,
        status NVARCHAR(20) NOT NULL
            CONSTRAINT DF_gtcd_status DEFAULT 'PENDING',
        completed_at DATETIME2 NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_gtcd_header
            FOREIGN KEY (check_header_id)
            REFERENCES dbo.good_transfer_check_headers(id) ON DELETE CASCADE,
        CONSTRAINT UQ_gtcd_source
            UNIQUE(check_header_id, source_detail_id),
        CONSTRAINT CK_gtcd_status
            CHECK (status IN ('PENDING','OK','OVERRIDE'))
    );

    CREATE INDEX IX_gtcd_header_item_status
        ON dbo.good_transfer_check_details(check_header_id, item_code, status);
END;
GO

IF OBJECT_ID('dbo.good_transfer_check_scan_logs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.good_transfer_check_scan_logs (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        check_header_id BIGINT NOT NULL,
        check_detail_id BIGINT NOT NULL,
        barcode NVARCHAR(200) NOT NULL,
        item_code NVARCHAR(100) NOT NULL,
        uom_code NVARCHAR(50) NULL,
        ratio_qty DECIMAL(18,4) NOT NULL,
        checker_user_id BIGINT NOT NULL,
        scanned_at DATETIME2 NOT NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_gtcsl_header
            FOREIGN KEY (check_header_id)
            REFERENCES dbo.good_transfer_check_headers(id) ON DELETE CASCADE,
        CONSTRAINT FK_gtcsl_detail
            FOREIGN KEY (check_detail_id)
            REFERENCES dbo.good_transfer_check_details(id),
        CONSTRAINT FK_gtcsl_checker
            FOREIGN KEY (checker_user_id)
            REFERENCES dbo.warehouse_users(id)
    );

    CREATE INDEX IX_gtcsl_header_scanned
        ON dbo.good_transfer_check_scan_logs(check_header_id, scanned_at);
END;
GO

IF OBJECT_ID('dbo.good_transfer_check_scan_errors', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.good_transfer_check_scan_errors (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        check_header_id BIGINT NOT NULL,
        barcode NVARCHAR(200) NULL,
        resolved_item_code NVARCHAR(100) NULL,
        resolved_uom_code NVARCHAR(50) NULL,
        error_type NVARCHAR(50) NOT NULL,
        error_message NVARCHAR(500) NULL,
        picker_user_id BIGINT NOT NULL,
        checker_user_id BIGINT NOT NULL,
        scanned_at DATETIME2 NOT NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_gtcse_header
            FOREIGN KEY (check_header_id)
            REFERENCES dbo.good_transfer_check_headers(id) ON DELETE CASCADE,
        CONSTRAINT FK_gtcse_picker
            FOREIGN KEY (picker_user_id)
            REFERENCES dbo.warehouse_users(id),
        CONSTRAINT FK_gtcse_checker
            FOREIGN KEY (checker_user_id)
            REFERENCES dbo.warehouse_users(id)
    );

    CREATE INDEX IX_gtcse_header_type
        ON dbo.good_transfer_check_scan_errors(check_header_id, error_type);

    CREATE INDEX IX_gtcse_picker_scanned
        ON dbo.good_transfer_check_scan_errors(picker_user_id, scanned_at);
END;
GO

IF OBJECT_ID('dbo.good_transfer_check_overrides', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.good_transfer_check_overrides (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        check_header_id BIGINT NOT NULL,
        check_detail_id BIGINT NOT NULL,
        supervisor_user_id BIGINT NOT NULL,
        checker_user_id BIGINT NOT NULL,
        otp_id BIGINT NOT NULL,
        reason_code NVARCHAR(50) NOT NULL,
        reason_note NVARCHAR(500) NULL,
        overridden_at DATETIME2 NOT NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_gtco_header
            FOREIGN KEY (check_header_id)
            REFERENCES dbo.good_transfer_check_headers(id) ON DELETE CASCADE,
        CONSTRAINT FK_gtco_detail
            FOREIGN KEY (check_detail_id)
            REFERENCES dbo.good_transfer_check_details(id),
        CONSTRAINT FK_gtco_supervisor
            FOREIGN KEY (supervisor_user_id)
            REFERENCES dbo.warehouse_users(id),
        CONSTRAINT FK_gtco_checker
            FOREIGN KEY (checker_user_id)
            REFERENCES dbo.warehouse_users(id),
        CONSTRAINT FK_gtco_otp
            FOREIGN KEY (otp_id)
            REFERENCES dbo.supervisor_otps(id)
    );

    CREATE UNIQUE INDEX UX_gtco_detail
        ON dbo.good_transfer_check_overrides(check_detail_id);

    CREATE INDEX IX_gtco_header_time
        ON dbo.good_transfer_check_overrides(check_header_id, overridden_at);
END;
GO

PRINT 'Patch 03_good_transfer_and_scan_fix.sql selesai.';
GO
