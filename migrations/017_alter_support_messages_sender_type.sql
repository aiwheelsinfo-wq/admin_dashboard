-- Migration 017: Allow 'customer' in sender_type and user_type for support_messages
ALTER TABLE support_messages MODIFY COLUMN sender_type ENUM('vendor', 'customer', 'admin') NOT NULL DEFAULT 'vendor';
ALTER TABLE support_messages MODIFY COLUMN user_type ENUM('vendor', 'customer') DEFAULT 'vendor';
