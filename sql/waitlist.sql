-- ============================================
-- Booking Waitlist Feature
-- The Rolling Dice - Board Game Café
-- Run this against rolling_dice_db to add
-- the waitlist table.
-- ============================================

USE rolling_dice_db;

CREATE TABLE IF NOT EXISTS waitlist (
    waitlist_id  INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NOT NULL,
    booking_date DATE NOT NULL,
    time_slot    VARCHAR(20) NOT NULL,
    party_size   INT NOT NULL DEFAULT 2,
    game_id      INT DEFAULT NULL,
    notes        TEXT,
    status       ENUM('Pending', 'Cancelled') NOT NULL DEFAULT 'Pending',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (game_id)   REFERENCES games(game_id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;