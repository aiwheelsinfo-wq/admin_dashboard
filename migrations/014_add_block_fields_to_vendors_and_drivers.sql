-- Migration 014: Add block_reason and blocked_at columns to drivers and vendors tables

ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS block_reason VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS blocked_at DATETIME DEFAULT NULL;

ALTER TABLE vendors
    ADD COLUMN IF NOT EXISTS block_reason VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS blocked_at DATETIME DEFAULT NULL;
