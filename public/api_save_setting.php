<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$key = $input['key'] ?? '';
$value = $input['value'] ?? '';

if ($key !== 'weight_unit' || !in_array($value, ['kg', 'lbs'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

set_setting($pdo, 'weight_unit', $value);

echo json_encode(['ok' => true, 'key' => $key, 'value' => $value]);