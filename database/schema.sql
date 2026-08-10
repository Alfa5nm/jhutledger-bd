CREATE DATABASE IF NOT EXISTS jhutledger_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE jhutledger_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS stock_transaction;
DROP TABLE IF EXISTS payment;
DROP TABLE IF EXISTS order_item;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS quotation;
DROP TABLE IF EXISTS b2c_listing;
DROP TABLE IF EXISTS b2b_listing;
DROP TABLE IF EXISTS listing;
DROP TABLE IF EXISTS textile_batch;
DROP TABLE IF EXISTS b2c_buyer;
DROP TABLE IF EXISTS b2b_buyer;
DROP TABLE IF EXISTS supplier;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    street VARCHAR(180) NOT NULL,
    city VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    user_status ENUM('Active', 'Inactive', 'Pending') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_status (user_status),
    INDEX idx_users_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE supplier (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    CONSTRAINT fk_supplier_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE b2b_buyer (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    CONSTRAINT fk_b2b_buyer_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE b2c_buyer (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    CONSTRAINT fk_b2c_buyer_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE textile_batch (
    batch_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NOT NULL,
    material_type VARCHAR(100) NOT NULL,
    composition VARCHAR(180) NOT NULL,
    color VARCHAR(80) NOT NULL,
    gsm DECIMAL(8,2) NOT NULL,
    `condition` ENUM('New', 'Surplus', 'Dead Stock', 'Recycled') NOT NULL,
    total_quantity DECIMAL(12,2) NOT NULL,
    available_quantity DECIMAL(12,2) NOT NULL,
    average_cost DECIMAL(12,2) NOT NULL,
    storage_location VARCHAR(180) NOT NULL,
    entry_date DATE NOT NULL,
    unit_of_measure VARCHAR(30) NOT NULL,
    status ENUM('Active', 'Closed', 'Inactive') NOT NULL DEFAULT 'Active',
    CONSTRAINT fk_batch_supplier FOREIGN KEY (supplier_id)
        REFERENCES supplier(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_batch_gsm CHECK (gsm > 0),
    CONSTRAINT chk_batch_total CHECK (total_quantity >= 0),
    CONSTRAINT chk_batch_available CHECK (available_quantity >= 0 AND available_quantity <= total_quantity),
    CONSTRAINT chk_batch_cost CHECK (average_cost >= 0),
    INDEX idx_batch_supplier_status (supplier_id, status),
    INDEX idx_batch_material (material_type),
    INDEX idx_batch_entry_date (entry_date)
) ENGINE=InnoDB;

CREATE TABLE listing (
    listing_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    listed_quantity DECIMAL(12,2) NOT NULL,
    status ENUM('Active', 'Closed', 'Inactive', 'Sold Out') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_listing_batch FOREIGN KEY (batch_id)
        REFERENCES textile_batch(batch_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_listing_quantity CHECK (listed_quantity > 0),
    INDEX idx_listing_batch_status (batch_id, status),
    INDEX idx_listing_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE b2b_listing (
    listing_id BIGINT UNSIGNED PRIMARY KEY,
    minimum_quantity DECIMAL(12,2) NOT NULL,
    bulk_unit_price DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_b2b_listing_listing FOREIGN KEY (listing_id)
        REFERENCES listing(listing_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_b2b_minimum CHECK (minimum_quantity > 0),
    CONSTRAINT chk_b2b_price CHECK (bulk_unit_price >= 0)
) ENGINE=InnoDB;

CREATE TABLE b2c_listing (
    listing_id BIGINT UNSIGNED PRIMARY KEY,
    bundle_size DECIMAL(12,2) NOT NULL,
    fixed_unit_price DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_b2c_listing_listing FOREIGN KEY (listing_id)
        REFERENCES listing(listing_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_b2c_bundle CHECK (bundle_size > 0),
    CONSTRAINT chk_b2c_price CHECK (fixed_unit_price >= 0)
) ENGINE=InnoDB;

CREATE TABLE quotation (
    quotation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    requested_quantity DECIMAL(12,2) NOT NULL,
    proposed_price DECIMAL(12,2) NOT NULL,
    counter_price DECIMAL(12,2) NULL,
    final_price DECIMAL(12,2) NULL,
    status ENUM('Pending', 'Countered', 'Accepted', 'Rejected', 'Expired', 'Cancelled') NOT NULL DEFAULT 'Pending',
    expiry_date DATE NOT NULL,
    CONSTRAINT fk_quotation_buyer FOREIGN KEY (buyer_id)
        REFERENCES b2b_buyer(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_quotation_listing FOREIGN KEY (listing_id)
        REFERENCES b2b_listing(listing_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_quotation_quantity CHECK (requested_quantity > 0),
    CONSTRAINT chk_quotation_proposed CHECK (proposed_price >= 0),
    CONSTRAINT chk_quotation_counter CHECK (counter_price IS NULL OR counter_price >= 0),
    CONSTRAINT chk_quotation_final CHECK (final_price IS NULL OR final_price >= 0),
    INDEX idx_quotation_buyer_status (buyer_id, status),
    INDEX idx_quotation_listing_status (listing_id, status),
    INDEX idx_quotation_expiry (expiry_date)
) ENGINE=InnoDB;

CREATE TABLE orders (
    order_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id BIGINT UNSIGNED NOT NULL,
    quotation_id BIGINT UNSIGNED NULL UNIQUE,
    order_type ENUM('B2B', 'B2C') NOT NULL,
    order_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    order_status ENUM('Pending', 'Confirmed', 'Processing', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_buyer FOREIGN KEY (buyer_id)
        REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_order_quotation FOREIGN KEY (quotation_id)
        REFERENCES quotation(quotation_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_order_amount CHECK (total_amount >= 0),
    INDEX idx_order_buyer_status (buyer_id, order_status),
    INDEX idx_order_date (order_date)
) ENGINE=InnoDB;

CREATE TABLE order_item (
    order_id BIGINT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    selling_price DECIMAL(12,2) NOT NULL,
    average_cost_snapshot DECIMAL(12,2) NOT NULL,
    gross_profit DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (order_id, line_no),
    CONSTRAINT fk_order_item_order FOREIGN KEY (order_id)
        REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_order_item_listing FOREIGN KEY (listing_id)
        REFERENCES listing(listing_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_order_item_line CHECK (line_no > 0),
    CONSTRAINT chk_order_item_quantity CHECK (quantity > 0),
    CONSTRAINT chk_order_item_price CHECK (selling_price >= 0),
    CONSTRAINT chk_order_item_cost CHECK (average_cost_snapshot >= 0),
    INDEX idx_order_item_listing (listing_id)
) ENGINE=InnoDB;

CREATE TABLE payment (
    payment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('Cash', 'Bank Transfer', 'Mobile Banking', 'Card') NOT NULL,
    payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
    payment_date DATETIME NULL,
    CONSTRAINT fk_payment_order FOREIGN KEY (order_id)
        REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_payment_amount CHECK (amount >= 0),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB;

CREATE TABLE stock_transaction (
    transaction_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    quantity DECIMAL(12,2) NOT NULL,
    transaction_type ENUM(
        'STOCK_ADDED', 'RESERVED', 'RESERVATION_RELEASED', 'SOLD',
        'RETURNED', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT'
    ) NOT NULL,
    transaction_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    remarks VARCHAR(255) NULL,
    CONSTRAINT fk_stock_batch FOREIGN KEY (batch_id)
        REFERENCES textile_batch(batch_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stock_order FOREIGN KEY (order_id)
        REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_stock_quantity CHECK (quantity > 0),
    INDEX idx_stock_batch_date (batch_id, transaction_date),
    INDEX idx_stock_order (order_id),
    INDEX idx_stock_type (transaction_type)
) ENGINE=InnoDB;

