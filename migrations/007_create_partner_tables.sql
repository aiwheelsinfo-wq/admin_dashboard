-- Migration 007: Create Partner API Management Tables

-- Table 1: partners — stores partner/agency details + API credentials
CREATE TABLE IF NOT EXISTS partners (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    partner_name          VARCHAR(255) NOT NULL,
    company_name          VARCHAR(255) NOT NULL,
    contact_person        VARCHAR(255) NOT NULL,
    mobile_number         VARCHAR(20)  NOT NULL,
    email                 VARCHAR(255) NOT NULL UNIQUE,
    api_key               VARCHAR(80)  NOT NULL UNIQUE,
    secret_key            VARCHAR(80)  NOT NULL,
    status                ENUM('active','blocked') NOT NULL DEFAULT 'active',
    rate_limit_per_minute INT NOT NULL DEFAULT 60,
    rate_limit_per_day    INT NOT NULL DEFAULT 10000,
    notes                 TEXT         DEFAULT NULL,
    created_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_status  (status)
);

-- Table 2: partner_api_logs — every request logged here
CREATE TABLE IF NOT EXISTS partner_api_logs (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    partner_id    INT          NOT NULL,
    api_name      VARCHAR(100) NOT NULL,
    method        VARCHAR(10)  NOT NULL DEFAULT 'POST',
    request_data  TEXT         DEFAULT NULL,
    response_data TEXT         DEFAULT NULL,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    status        ENUM('success','error','blocked','rate_limited') NOT NULL DEFAULT 'success',
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_partner_id (partner_id),
    INDEX idx_api_name   (api_name),
    INDEX idx_status      (status),
    INDEX idx_created_at  (created_at)
);

-- Table 3: partner_api_limits — sliding window rate limit counters
CREATE TABLE IF NOT EXISTS partner_api_limits (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    partner_id    INT         NOT NULL UNIQUE,
    minute_key    VARCHAR(20) NOT NULL DEFAULT '',
    day_key       VARCHAR(10) NOT NULL DEFAULT '',
    minute_count  INT         NOT NULL DEFAULT 0,
    day_count     INT         NOT NULL DEFAULT 0,
    updated_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partner_limit (partner_id)
);

-- Table 4: partner_bookings — links partner requests to real booking IDs
CREATE TABLE IF NOT EXISTS partner_bookings (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    partner_id          INT          NOT NULL,
    booking_id          VARCHAR(100) NOT NULL,
    partner_booking_ref VARCHAR(100) DEFAULT NULL,
    trip_type           VARCHAR(50)  DEFAULT NULL,
    status              VARCHAR(50)  DEFAULT 'pending',
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_partner_booking (partner_id),
    INDEX idx_booking_id      (booking_id)
);
