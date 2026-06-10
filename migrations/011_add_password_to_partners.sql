-- Migration 011: Add password column to partners table for B2B dashboard login
ALTER TABLE partners 
    ADD COLUMN password VARCHAR(255) DEFAULT NULL;
