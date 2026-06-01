CREATE DATABASE IF NOT EXISTS payment_gateway_testing
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON payment_gateway_testing.* TO 'payment_gateway'@'%';
FLUSH PRIVILEGES;
