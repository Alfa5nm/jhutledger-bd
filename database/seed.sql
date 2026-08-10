USE jhutledger_db;

START TRANSACTION;

INSERT INTO users
    (user_id, name, email, phone, password_hash, street, city, district, postal_code, user_status, created_at)
VALUES
    (1, 'Nusrat Jahan', 'admin@jhutledger.local', '01710000001', '$2y$10$rm89z7g2tckTTPIbeWApyu7Ro8AEFuhBV935W1i9.ACE6mrE5ffGG', '12 University Road', 'Dhaka', 'Dhaka', '1205', 'Active', NOW() - INTERVAL 30 DAY),
    (2, 'Rahman Textile Supply', 'supplier@jhutledger.local', '01710000002', '$2y$10$rm89z7g2tckTTPIbeWApyu7Ro8AEFuhBV935W1i9.ACE6mrE5ffGG', '45 BSCIC Road', 'Narayanganj', 'Narayanganj', '1400', 'Active', NOW() - INTERVAL 25 DAY),
    (3, 'Meghna Garments Ltd', 'b2b@jhutledger.local', '01710000003', '$2y$10$rm89z7g2tckTTPIbeWApyu7Ro8AEFuhBV935W1i9.ACE6mrE5ffGG', '88 Industrial Avenue', 'Gazipur', 'Gazipur', '1700', 'Active', NOW() - INTERVAL 20 DAY),
    (4, 'Farzana Akter', 'b2c@jhutledger.local', '01710000004', '$2y$10$rm89z7g2tckTTPIbeWApyu7Ro8AEFuhBV935W1i9.ACE6mrE5ffGG', '21 Station Road', 'Cumilla', 'Cumilla', '3500', 'Active', NOW() - INTERVAL 15 DAY);

INSERT INTO b2b_buyer (user_id) VALUES (1), (3);
INSERT INTO supplier (user_id) VALUES (2);
INSERT INTO b2c_buyer (user_id) VALUES (4);

INSERT INTO textile_batch
    (batch_id, supplier_id, material_type, composition, color, gsm, `condition`, total_quantity,
     available_quantity, average_cost, storage_location, entry_date, unit_of_measure, status)
VALUES
    (1, 2, 'Cotton Knit', '100% Cotton', 'Navy Blue', 180.00, 'Surplus', 1000.00, 780.00, 90.00, 'Warehouse A-12', CURDATE() - INTERVAL 45 DAY, 'kg', 'Active'),
    (2, 2, 'Denim', '98% Cotton, 2% Elastane', 'Indigo', 320.00, 'New', 500.00, 500.00, 130.00, 'Warehouse B-04', CURDATE() - INTERVAL 10 DAY, 'kg', 'Active');

INSERT INTO listing (listing_id, batch_id, listed_quantity, status, created_at) VALUES
    (1, 1, 300.00, 'Active', NOW() - INTERVAL 40 DAY),
    (2, 1, 180.00, 'Active', NOW() - INTERVAL 35 DAY),
    (3, 2, 300.00, 'Active', NOW() - INTERVAL 8 DAY);

INSERT INTO b2b_listing (listing_id, minimum_quantity, bulk_unit_price) VALUES
    (1, 100.00, 150.00);

INSERT INTO b2c_listing (listing_id, bundle_size, fixed_unit_price) VALUES
    (2, 10.00, 200.00),
    (3, 5.00, 260.00);

INSERT INTO quotation
    (quotation_id, buyer_id, listing_id, requested_quantity, proposed_price, counter_price, final_price, status, expiry_date)
VALUES
    (1, 3, 1, 200.00, 140.00, 145.00, 145.00, 'Accepted', CURDATE() + INTERVAL 7 DAY);

INSERT INTO orders
    (order_id, buyer_id, quotation_id, order_type, order_date, order_status, total_amount)
VALUES
    (1, 3, 1, 'B2B', NOW() - INTERVAL 2 DAY, 'Confirmed', 29000.00),
    (2, 4, NULL, 'B2C', NOW() - INTERVAL 1 DAY, 'Confirmed', 4000.00);

INSERT INTO order_item
    (order_id, line_no, listing_id, quantity, selling_price, average_cost_snapshot, gross_profit)
VALUES
    (1, 1, 1, 200.00, 145.00, 90.00, 11000.00),
    (2, 1, 2, 20.00, 200.00, 90.00, 2200.00);

INSERT INTO payment
    (payment_id, order_id, amount, payment_method, payment_status, payment_date)
VALUES
    (1, 1, 29000.00, 'Bank Transfer', 'Paid', NOW() - INTERVAL 1 DAY),
    (2, 2, 4000.00, 'Mobile Banking', 'Pending', NULL);

INSERT INTO stock_transaction
    (transaction_id, batch_id, order_id, quantity, transaction_type, transaction_date, remarks)
VALUES
    (1, 1, NULL, 1000.00, 'STOCK_ADDED', NOW() - INTERVAL 45 DAY, 'Opening inventory'),
    (2, 2, NULL, 500.00, 'STOCK_ADDED', NOW() - INTERVAL 10 DAY, 'Opening inventory'),
    (3, 1, 1, 200.00, 'RESERVED', NOW() - INTERVAL 2 DAY, 'Reserved for B2B order #1'),
    (4, 1, 2, 20.00, 'RESERVED', NOW() - INTERVAL 1 DAY, 'Reserved for B2C order #2');

COMMIT;
