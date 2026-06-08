-- Migration 005: Create vendors table if not exists

CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    driver_address TEXT DEFAULT NULL,
    pin_code VARCHAR(20) DEFAULT NULL,
    license_no VARCHAR(100) DEFAULT NULL,
    license_doe DATE DEFAULT NULL,
    license_type VARCHAR(50) DEFAULT NULL,
    adhaar_card_no VARCHAR(20) DEFAULT NULL,
    pan_card_no VARCHAR(20) DEFAULT NULL,
    photo VARCHAR(10) DEFAULT 'NO',
    driver_city VARCHAR(100) DEFAULT NULL,
    agency_name VARCHAR(255) DEFAULT NULL,
    second_number VARCHAR(20) DEFAULT NULL,
    status VARCHAR(50) DEFAULT NULL,
    userType VARCHAR(50) DEFAULT 'Vendor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
