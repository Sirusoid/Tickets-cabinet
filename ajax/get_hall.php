<?php
//ajax/get_hall.php

require_once __DIR__ . '/../init.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$hall_id = $_GET['hall_id'] ?? null;
if (!$hall_id) {
    http_response_code(400);
    echo json_encode(['error' => 'hall_id required']);
    exit;
}

$stmt = $pdo->prepare("SELECT seat_map FROM halls WHERE id = :id");
$stmt->execute([':id' => $hall_id]);
$hall = $stmt->fetch(PDO::FETCH_ASSOC);

if ($hall && $hall['seat_map']) {
    // seat_map хранится как JSON
    echo $hall['seat_map'];
} else {
    http_response_code(404);
    echo json_encode(['error' => 'hall not found or empty']);
}
