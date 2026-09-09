-- Migration 016: Add user_type, user_phone, and user_name to support_messages table

ALTER TABLE support_messages 
ADD COLUMN IF NOT EXISTS user_type ENUM('vendor', 'customer') DEFAULT 'vendor' AFTER id,
ADD COLUMN IF NOT EXISTS user_phone VARCHAR(20) DEFAULT NULL AFTER user_type,
ADD COLUMN IF NOT EXISTS user_name VARCHAR(100) DEFAULT NULL AFTER user_phone;

-- Backfill user_phone from vendor_phone for existing messages
UPDATE support_messages 
SET user_type = 'vendor', user_phone = vendor_phone 
WHERE user_phone IS NULL OR user_phone = '';

-- Add index on user_type and user_phone
ALTER TABLE support_messages ADD INDEX IF NOT EXISTS idx_user_type_phone (user_type, user_phone);
