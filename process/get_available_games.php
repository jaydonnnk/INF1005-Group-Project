<?php
/**
 * Get Available Games (JSON endpoint)
 * 
 *
 * Returns games with available copies for a given date + time slot.
 */

header('Content-Type: application/json');

require_once "db.php";

$booking_date = $_GET["booking_date"] ?? "";
$time_slot = $_GET["time_slot"] ?? "";

if (empty($booking_date) || empty($time_slot)) {
    echo json_encode([]);
    exit();
}

// Validate date format (YYYY-MM-DD)
$parsed_date = date_create_from_format('Y-m-d', $booking_date);
if (!$parsed_date || $parsed_date->format('Y-m-d') !== $booking_date) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit();
}

// Validate time_slot against allowed values
$valid_slots = [
    '11:00 AM - 1:00 PM', '1:00 PM - 3:00 PM', '3:00 PM - 5:00 PM',
    '5:00 PM - 7:00 PM', '7:00 PM - 9:00 PM', '9:00 PM - 11:00 PM'
];
if (!in_array($time_slot, $valid_slots, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid time slot']);
    exit();
}

try {
    $stmt = $pdo->prepare(
        "SELECT g.game_id, g.title, g.price_per_hour, g.stripe_price_id,
                (g.quantity - COALESCE(booked.cnt, 0)) AS available_copies
        FROM games g
        LEFT JOIN (
            SELECT game_id, COUNT(*) AS cnt
            FROM bookings
            WHERE booking_date = :date AND time_slot = :slot AND status = 'Confirmed'
            GROUP BY game_id
        ) booked ON booked.game_id = g.game_id
        ORDER BY g.title ASC"
    );
    $stmt->execute([':date' => $booking_date, ':slot' => $time_slot]);
    $games = $stmt->fetchAll();

    echo json_encode($games);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
