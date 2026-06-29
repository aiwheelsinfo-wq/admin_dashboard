CREATE TABLE IF NOT EXISTS `city_boundaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `city_name` VARCHAR(100) NOT NULL UNIQUE,
  `min_lat` DECIMAL(10,8) NOT NULL,
  `max_lat` DECIMAL(10,8) NOT NULL,
  `min_lng` DECIMAL(11,8) NOT NULL,
  `max_lng` DECIMAL(11,8) NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `city_boundaries` (`city_name`, `min_lat`, `max_lat`, `min_lng`, `max_lng`, `status`) VALUES
('Pune', 18.41000000, 18.65000000, 73.72000000, 73.98000000, 'active'),
('Mumbai', 18.89000000, 19.30000000, 72.75000000, 73.20000000, 'active'),
('Nashik', 19.90000000, 20.10000000, 73.70000000, 73.88000000, 'active'),
('Nagpur', 21.05000000, 21.22000000, 79.00000000, 79.18000000, 'active'),
('Aurangabad', 19.82000000, 19.95000000, 75.25000000, 75.42000000, 'active'),
('Kolhapur', 16.65000000, 16.75000000, 74.20000000, 74.28000000, 'active'),
('Solapur', 17.62000000, 17.72000000, 75.85000000, 75.95000000, 'active')
ON DUPLICATE KEY UPDATE 
  `min_lat` = VALUES(`min_lat`), 
  `max_lat` = VALUES(`max_lat`), 
  `min_lng` = VALUES(`min_lng`), 
  `max_lng` = VALUES(`max_lng`),
  `status` = VALUES(`status`);
