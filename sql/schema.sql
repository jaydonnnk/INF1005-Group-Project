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
    price_per_hour DECIMAL(5,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Menu Items table (Food & drinks)
CREATE TABLE IF NOT EXISTS menu_items (
    item_id     INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    price       DECIMAL(6,2) NOT NULL,
    category    ENUM('Food', 'Drinks', 'Desserts') NOT NULL,
    image_url   VARCHAR(255),
    available   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookings table (Table reservations)
CREATE TABLE IF NOT EXISTS bookings (
    booking_id   INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NOT NULL,
    booking_date DATE NOT NULL,
    time_slot    VARCHAR(20) NOT NULL,
    party_size   INT NOT NULL DEFAULT 2,
    game_id      INT,
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

-- Sample Games
INSERT INTO games (title, description, min_players, max_players, genre, difficulty, image_url, price_per_hour) VALUES
('Catan', 'Trade, build, and settle the island of Catan in this classic strategy game.', 3, 4, 'Strategy', 'Medium', 'images/catan.jpg', 5.00),
('Codenames', 'Give one-word clues to help your team guess the right words.', 4, 8, 'Party', 'Easy', 'images/codenames.jpg', 3.00),
('Pandemic', 'Work together to stop global outbreaks and save humanity.', 2, 4, 'Co-op', 'Hard', 'images/pandemic.jpg', 5.00),
('Ticket to Ride', 'Collect cards and claim railway routes across the map.', 2, 5, 'Family', 'Easy', 'images/ticket-to-ride.jpg', 4.00),
('Azul', 'Draft beautiful tiles and decorate the walls of your palace.', 2, 4, 'Abstract', 'Medium', 'images/azul.jpg', 4.50),
('Wingspan', 'Attract birds to your wildlife preserves in this engine-building game.', 1, 5, 'Strategy', 'Medium', 'images/wingspan.jpg', 5.50);

-- Sample Menu Items
INSERT INTO menu_items (name, description, price, category, image_url) VALUES
('Classic Nachos', 'Crispy tortilla chips topped with melted cheese, jalapeños, and salsa.', 12.90, 'Food', 'images/nachos.jpg'),
('Truffle Fries', 'Shoestring fries tossed in truffle oil and parmesan.', 10.90, 'Food', 'images/truffle-fries.jpg'),
('Margherita Pizza', 'Wood-fired pizza with fresh mozzarella, basil, and tomato sauce.', 16.90, 'Food', 'images/pizza.jpg'),
('Iced Matcha Latte', 'Premium matcha blended with oat milk over ice.', 7.50, 'Drinks', 'images/matcha.jpg'),
('Craft Root Beer', 'House-brewed root beer with vanilla and spices.', 6.00, 'Drinks', 'images/rootbeer.jpg'),
('Espresso', 'Double shot of single-origin espresso.', 5.00, 'Drinks', 'images/espresso.jpg'),
('Warm Brownie Sundae', 'Fudge brownie with vanilla ice cream and chocolate sauce.', 11.90, 'Desserts', 'images/brownie.jpg'),
('Churros', 'Golden churros dusted with cinnamon sugar, served with dipping sauce.', 8.90, 'Desserts', 'images/churros.jpg');
