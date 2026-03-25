INSERT INTO stores (id, name, location) VALUES (1, 'Kafeteria Central', 'Downtown');
INSERT INTO users (id, username, email, password_hash, role, store_id) VALUES (1, 'staff1', 'staff1@example.com', 'hash', 'store_staff', 1);
INSERT INTO campaigns (id, user_id, title, description, start_date, end_date, is_active) VALUES (1, 1, 'Free Coffee', 'Get a free coffee on us.', '2023-01-01', '2030-12-31', 1);

-- Coupon 1: Idle (Ready to activate)
INSERT INTO coupons (id, uuid, campaign_id, status) VALUES (1, '11111111-1111-1111-1111-111111111111', 1, 'idle');

-- Coupon 2: Activated (Ready to confirm)
INSERT INTO coupons (id, uuid, campaign_id, status, activated_at) VALUES (2, '22222222-2222-2222-2222-222222222222', 1, 'activated', '2024-03-20 10:00:00');

-- Coupon 3: Confirmed (Already redeemed)
INSERT INTO coupons (id, uuid, campaign_id, status, activated_at, confirmed_at) VALUES (3, '33333333-3333-3333-3333-333333333333', 1, 'confirmed', '2024-03-19 10:00:00', '2024-03-19 10:05:00');
