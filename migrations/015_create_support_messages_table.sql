-- Migration 015: Create support_messages table for In-App Partner Helpdesk

CREATE TABLE IF NOT EXISTS support_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_phone VARCHAR(20) NOT NULL,
    sender_type ENUM('vendor', 'admin') NOT NULL,
    sender_name VARCHAR(100) DEFAULT NULL,
    message TEXT NOT NULL,
    attachment_url VARCHAR(500) DEFAULT NULL,
    attachment_type VARCHAR(50) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vendor_phone (vendor_phone),
    INDEX idx_created_at (created_at),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
