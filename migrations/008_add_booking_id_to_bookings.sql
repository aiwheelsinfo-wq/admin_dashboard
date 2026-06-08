-- Migration 008: Add booking_id column to bookings table for Partner API
ALTER TABLE bookings ADD COLUMN booking_id VARCHAR(100) DEFAULT NULL AFTER id;
ALTER TABLE bookings ADD INDEX idx_bookings_booking_id (booking_id);
