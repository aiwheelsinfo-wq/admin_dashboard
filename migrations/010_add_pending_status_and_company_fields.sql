-- Migration 010: Add company owner, GST, nullable keys, and pending status
ALTER TABLE partners 
    ADD COLUMN company_owner_name VARCHAR(255) DEFAULT NULL,
    ADD COLUMN gst_number VARCHAR(50) DEFAULT NULL,
    MODIFY COLUMN api_key VARCHAR(80) DEFAULT NULL,
    MODIFY COLUMN secret_key VARCHAR(80) DEFAULT NULL,
    MODIFY COLUMN status ENUM('pending', 'active', 'blocked') NOT NULL DEFAULT 'pending';
