-- Migration 018: Add vendor wallet balance, transaction ledger, and local taxi wallet threshold

-- 1. Add wallet_balance column to drivers table
ALTER TABLE drivers ADD COLUMN wallet_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00;

-- 2. Add wallet_balance column to vendors table
ALTER TABLE vendors ADD COLUMN wallet_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00;

-- 3. Add min_wallet_balance column to local_taxi_global_settings table
ALTER TABLE local_taxi_global_settings ADD COLUMN min_wallet_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00;

-- 4. Create vendor_wallet_transactions ledger table
CREATE TABLE IF NOT EXISTS vendor_wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_phone VARCHAR(20) NOT NULL,
    booking_id INT DEFAULT NULL,
    transaction_type ENUM('trip_commission_deduct', 'wallet_recharge', 'admin_adjustment', 'refund') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    balance_before DECIMAL(10, 2) NOT NULL,
    balance_after DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    reference_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vendor_phone (vendor_phone),
    INDEX idx_created_at (created_at),
    INDEX idx_booking_id (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
