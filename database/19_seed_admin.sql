-- ---------------------------------------------------------------------------
-- 19 - bootstrap admin
--
-- Default login:  admin@gmail.com / admin123   (change it after first login)
-- The password below is a bcrypt hash of "admin123". If the email already
-- exists nothing is changed. Safe to run more than once.
-- ---------------------------------------------------------------------------

INSERT INTO users (name, email, phone, password_hash, role, status)
VALUES ('System Administrator', 'admin@gmail.com', '',
        '$2y$12$Jhg9wJIZi6wxWesWqjFHvOFjR3fbfKm/xHcoTGNghfg1fpzRHj8qm',
        'admin', 'active')
ON DUPLICATE KEY UPDATE email = email;
