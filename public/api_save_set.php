<?php
require_once __DIR__ . '/../config/database.php';

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

$set_id = isset($input['set_id']) ? (int)$input['set_id'] : 0;
$field  = $input['field'] ?? '';
$value  = array_key_exists('value', $input) ? $input['value'] : null;

$allowed = [
    'planned_weight',
    'actual_weight',
    'planned_reps',
    'actual_reps',
    'is_warmup',
    'notes',
];

if ($set_id <= 0 || !in_array($field, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

// Подход должен существовать
$stmt = $pdo->prepare("
    SELECT s.id
    FROM sets s
    JOIN exercises e ON e.id = s.exercise_id
    WHERE s.id = ?
      AND s.deleted_at IS NULL
      AND e.deleted_at IS NULL
");
$stmt->execute([$set_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

// Нормализация значения
if ($field === 'is_warmup') {
    $dbValue = ((int)$value) ? 1 : 0;
} elseif ($field === 'planned_reps' || $field === 'actual_reps') {
    if ($value === '' || $value === null) {
        $dbValue = null;
    } else {
        $dbValue = (int)$value;
    }
} elseif ($field === 'notes') {
    $dbValue = trim((string)$value);
    $dbValue = $dbValue === '' ? null : $dbValue;
} else {
    // weight
    if ($value === '' || $value === null) {
        $dbValue = null;
    } else {
        $dbValue = $value;
    }
}

$sql = "UPDATE sets SET {$field} = ? WHERE id = ? AND deleted_at IS NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dbValue, $set_id]);

echo json_encode(['ok' => true, 'field' => $field, 'value' => $dbValue]);