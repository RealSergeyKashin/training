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

$id = isset($input['id']) ? (int)$input['id'] : 0;
$field = $input['field'] ?? '';
$value = array_key_exists('value', $input) ? $input['value'] : null;

$allowed = ['workout_date', 'body_weight', 'notes'];

if ($id <= 0 || !in_array($field, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM workouts WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

if ($field === 'body_weight') {
    $dbValue = ($value === '' || $value === null) ? null : $value;
    $unit = get_setting($pdo, 'weight_unit', 'kg');
    if (!in_array($unit, ['kg', 'lbs'], true)) {
        $unit = 'kg';
    }
    $stmt = $pdo->prepare("
        UPDATE workouts
        SET body_weight = ?, body_weight_unit = ?
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$dbValue, $unit, $id]);
} elseif ($field === 'notes') {
    $dbValue = trim((string)$value);
    $dbValue = $dbValue === '' ? null : $dbValue;
    $stmt = $pdo->prepare("UPDATE workouts SET notes = ? WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$dbValue, $id]);
} else { // workout_date
    $dbValue = (string)$value;
    if ($dbValue === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'empty_date']);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE workouts SET workout_date = ? WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$dbValue, $id]);
}

echo json_encode(['ok' => true, 'field' => $field]);