-- ============================================
-- schema.sql — Database Schema
-- The Rolling Dice - Board Game Cafe
-- INF1005 Web Systems and Technologies
-- ============================================
-- Run this script as the MySQL root user on your GCP LAMP VM:
--   mysql -u root -p < schema.sql
-- ============================================

CREATE DATABASE IF NOT EXISTS rolling_dice_db;
USE rolling_dice_db;


-- Members table (User accounts)

CREATE TABLE IF NOT EXISTS members (
    member_id   INT AUTO_INCREMENT PRIMARY KEY,
    fname       VARCHAR(45),
    lname       VARCHAR(45) NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone       VARCHAR(20),
    is_admin    TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Games table (Board game library)

CREATE TABLE IF NOT EXISTS games (
    game_id       INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(100) NOT NULL,
    description   TEXT,
    min_players   INT NOT NULL DEFAULT 1,
    max_players   INT NOT NULL DEFAULT 4,
    genre         VARCHAR(50),
    difficulty    ENUM('Easy', 'Medium', 'Hard') NOT NULL DEFAULT 'Medium',
    image_url     VARCHAR(255),
    price_per_hour DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    quantity      INT NOT NULL DEFAULT 3,
    stripe_price_id VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Menu Items table (Food & drinks)

CREATE TABLE IF NOT EXISTS menu_items (
    item_id     INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    price       DECIMAL(6,2) NOT NULL,
    category    ENUM('Food', 'Drinks', 'Desserts') NOT NULL,
    image_url   VARCHAR(255),
    available   TINYINT(1) NOT NULL DEFAULT 1,
    stripe_price_id VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Bookings table (Table reservations)

CREATE TABLE IF NOT EXISTS bookings (
    booking_id   INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NOT NULL,
    booking_date DATE NOT NULL,
    time_slot    VARCHAR(20) NOT NULL,
    party_size   INT NOT NULL DEFAULT 2,
    game_id      INT,                                                         -- NULL = no game selected
    rental_hours INT NOT NULL DEFAULT 2,
    notes        TEXT,
    status       ENUM('Confirmed', 'Cancelled', 'Completed') NOT NULL DEFAULT 'Confirmed',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Orders table (Food & drink orders)

CREATE TABLE IF NOT EXISTS orders (
    order_id     INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NOT NULL,
    order_date   DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    status       ENUM('Pending', 'Preparing', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Order Items table (Line items per order)

CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL,
    item_id       INT NOT NULL,
    quantity      INT NOT NULL DEFAULT 1,
    subtotal      DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments table (Stripe payment records)

CREATE TABLE IF NOT EXISTS payments (
    payment_id       INT AUTO_INCREMENT PRIMARY KEY,
    member_id        INT NOT NULL,
    stripe_session_id VARCHAR(255),
    amount           DECIMAL(8,2) NOT NULL,
    currency         VARCHAR(10) NOT NULL DEFAULT 'sgd',
    payment_type     ENUM('booking', 'order') NOT NULL,
    reference_id     INT NOT NULL,                                              -- booking_id or order_id depending on payment_type
    status           ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Reviews table (Game reviews)

CREATE TABLE IF NOT EXISTS reviews (
    review_id   INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NOT NULL,
    game_id     INT NOT NULL,
    rating      INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment     TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (member_id, game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Wishlists table (Favourite games)

CREATE TABLE IF NOT EXISTS wishlists (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NOT NULL,
    game_id     INT NOT NULL,
    added_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (member_id, game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- Waitlist table (Booking waitlist)

CREATE TABLE IF NOT EXISTS waitlist (
    waitlist_id       INT AUTO_INCREMENT PRIMARY KEY,
    member_id         INT NOT NULL,
    booking_date      DATE NOT NULL,
    time_slot         VARCHAR(20) NOT NULL,
    party_size        INT NOT NULL DEFAULT 2,
    game_id           INT DEFAULT NULL,
    notes             TEXT,
    status            ENUM('Pending', 'Notified', 'Claimed', 'Expired', 'Cancelled') NOT NULL DEFAULT 'Pending',
    claim_token       VARCHAR(64)  DEFAULT NULL,                                -- NULL = not yet notified
    notified_at       DATETIME     DEFAULT NULL,                                -- When the claim email was sent
    claim_expires_at  DATETIME     DEFAULT NULL,                                -- 1 hour after notified_at
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (game_id)   REFERENCES games(game_id)     ON DELETE SET NULL,
    UNIQUE KEY unique_claim_token (claim_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ========================================
-- Password Resets table
-- ========================================
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id    INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(100) NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================
-- Sample Data
-- ============================================

-- Clear existing sample data to prevent duplicates on reimport
DELETE FROM order_items;
DELETE FROM orders;
DELETE FROM wishlists;
DELETE FROM reviews;
DELETE FROM payments;
DELETE FROM bookings;
DELETE FROM waitlist;
DELETE FROM games;
DELETE FROM menu_items;

-- Sample Games
INSERT INTO games (title, description, min_players, max_players, genre, difficulty, image_url, price_per_hour, quantity, stripe_price_id) VALUES
('Catan', 'Trade, build, and settle the island of Catan in this classic strategy game.', 3, 4, 'Strategy', 'Medium', 'images/catan.jpg', 5.00, 3, 'price_1TAjpXFYrHzcVFcr7hdPeNnz'),
('Codenames', 'Give one-word clues to help your team guess the right words.', 4, 8, 'Party', 'Easy', 'images/codenames.jpg', 5.00, 3, 'price_1TAjpXFYrHzcVFcr7hdPeNnz'),
('Pandemic', 'Work together to stop global outbreaks and save humanity.', 2, 4, 'Co-op', 'Hard', 'images/pandemic.jpg', 5.00, 3, 'price_1TAjpXFYrHzcVFcr7hdPeNnz'),
('Ticket to Ride', 'Collect cards and claim railway routes across the map.', 2, 5, 'Family', 'Easy', 'images/ticket-to-ride.jpg', 5.00, 3, 'price_1TAjpXFYrHzcVFcr7hdPeNnz'),
('Azul', 'Draft beautiful tiles and decorate the walls of your palace.', 2, 4, 'Abstract', 'Medium', 'images/azul.jpg', 5.00, 3, 'price_1TAjpXFYrHzcVFcr7hdPeNnz'),
('Wingspan', 'Attract birds to your wildlife preserves in this engine-building game.', 1, 5, 'Strategy', 'Medium', 'images/wingspan.jpg', 5.00, 3, 'price_1TAjpXFYrHzcVFcr7hdPeNnz');

-- Sample Menu Items
INSERT INTO menu_items (name, description, price, category, image_url, stripe_price_id) VALUES
('Classic Nachos', 'Crispy tortilla chips topped with melted cheese, jalapeños, and salsa.', 12.90, 'Food', 'images/nachos.jpg', 'price_1TAjZqFYrHzcVFcrbIiPDF4C'),
('Truffle Fries', 'Shoestring fries tossed in truffle oil and parmesan.', 10.90, 'Food', 'images/truffle-fries.jpg', 'price_1TAjb4FYrHzcVFcrobb28dnD'),
('Margherita Pizza', 'Wood-fired pizza with fresh mozzarella, basil, and tomato sauce.', 16.90, 'Food', 'images/pizza.jpg', 'price_1TAjaEFYrHzcVFcrCthK8paz'),
('Iced Matcha Latte', 'Premium matcha blended with oat milk over ice.', 7.50, 'Drinks', 'images/matcha.jpg', 'price_1TAjcyFYrHzcVFcrF4lUk2A4'),
('Craft Root Beer', 'House-brewed root beer with vanilla and spices.', 6.00, 'Drinks', 'images/rootbeer.jpg', 'price_1TAjcJFYrHzcVFcrZoHbD2CU'),
('Espresso', 'Double shot of single-origin espresso.', 5.00, 'Drinks', 'images/espresso.jpg', 'price_1TAjcYFYrHzcVFcrgZ1LROI4'),
('Warm Brownie Sundae', 'Fudge brownie with vanilla ice cream and chocolate sauce.', 11.90, 'Desserts', 'images/brownie.jpg', 'price_1TAjdnFYrHzcVFcr9cxqUjP7'),
('Churros', 'Golden churros dusted with cinnamon sugar, served with dipping sauce.', 8.90, 'Desserts', 'images/churros.jpg', 'price_1TAjdFFYrHzcVFcrklzmtT60');


-- Matchmaking Posts table

CREATE TABLE IF NOT EXISTS matchmaking_posts (
    post_id      INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NOT NULL,
    booking_id   INT DEFAULT NULL,
    title        VARCHAR(80) NOT NULL,
    body         TEXT,
    game_name    VARCHAR(100) NOT NULL,
    game_type    ENUM('Strategy','Party','Cooperative','Deck-Building','Role-Playing','Trivia','Word') NOT NULL,
    skill_level  ENUM('Beginner','Intermediate','Advanced') NOT NULL,
    play_style   ENUM('Casual','Competitive','Story-driven') DEFAULT NULL,
    spots_total  INT NOT NULL DEFAULT 1,
    spots_filled INT NOT NULL DEFAULT 0,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    pref_gender  ENUM('Any','Male','Female','Non-binary') NOT NULL DEFAULT 'Any',
    pref_skill   ENUM('Any','Beginner','Intermediate','Advanced','Intermediate+') NOT NULL DEFAULT 'Any',
    pref_age     VARCHAR(20) DEFAULT NULL,
    is_urgent    TINYINT(1) NOT NULL DEFAULT 0,
    status       ENUM('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id)  REFERENCES members(member_id)  ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Matchmaking Interests table

CREATE TABLE IF NOT EXISTS matchmaking_interests (
    interest_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    member_id   INT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id)   REFERENCES matchmaking_posts(post_id)   ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id)           ON DELETE CASCADE,
    UNIQUE KEY unique_interest (post_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Matchmaking Joins table

CREATE TABLE IF NOT EXISTS matchmaking_joins (
    join_id    INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT NOT NULL,
    member_id  INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id)   REFERENCES matchmaking_posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id)         ON DELETE CASCADE,
    UNIQUE KEY unique_join (post_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Matchmaking Sessions table

CREATE TABLE IF NOT EXISTS matchmaking_sessions (
    session_id  INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NOT NULL UNIQUE,
    last_active DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Database User (for PHP application)
-- Change the password below before running!
-- ============================================
CREATE USER IF NOT EXISTS 'rolling_dice_user'@'localhost' IDENTIFIED BY 'Student@s1t';
GRANT ALL PRIVILEGES ON rolling_dice_db.* TO 'rolling_dice_user'@'localhost';
FLUSH PRIVILEGES;

-- ============================================
-- Email Verification & 2FA Columns (run on live DB)
-- ============================================
-- ALTER TABLE members ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin;
-- ALTER TABLE members ADD COLUMN verification_token VARCHAR(64) DEFAULT NULL AFTER email_verified;
-- ALTER TABLE members ADD COLUMN verification_expires DATETIME DEFAULT NULL AFTER verification_token;
-- ALTER TABLE members ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL AFTER verification_expires;  -- NULL = 2FA not configured
-- ALTER TABLE members ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret;

-- ============================================
-- Matchmaking booking_id column (run on existing DB if matchmaking_posts already exists)
-- ============================================
-- ALTER TABLE matchmaking_posts ADD COLUMN booking_id INT DEFAULT NULL AFTER member_id;
-- ALTER TABLE matchmaking_posts ADD CONSTRAINT fk_mp_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE SET NULL;