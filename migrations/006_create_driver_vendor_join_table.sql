-- Migration 006: Create driver_vendor_join_Table if not exists

CREATE TABLE IF NOT EXISTS driver_vendor_join_Table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id VARCHAR(20) NOT NULL,
    vendor_id VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_driver_vendor (driver_id, vendor_id)
);
