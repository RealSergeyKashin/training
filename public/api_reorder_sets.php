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

$exercise_id = isset($input['exercise_id']) ? (int)$input['exercise_id'] : 0;
$ids = $input['ids'] ?? null;

if ($exercise_id <= 0 || !is_array($ids) || $ids === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$ids = array_map('intval', $ids);
if (count($ids) !== count(array_unique($ids)) || in_array(0, $ids, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.id
    FROM sets s
    JOIN exercises e ON e.id = s.exercise_id
    JOIN workouts w ON w.id = e.workout_id
    WHERE s.exercise_id = ?
      AND s.deleted_at IS NULL
      AND e.deleted_at IS NULL
      AND w.deleted_at IS NULL
    ORDER BY s.sort_order ASC, s.id ASC
");
$stmt->execute([$exercise_id]);
$existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$incoming = $ids;
sort($incoming);
$check = $existing;
sort($check);

if ($existing === [] || $incoming !== $check) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("
        UPDATE sets
        SET sort_order = ?
        WHERE id = ? AND exercise_id = ? AND deleted_at IS NULL
    ");
    foreach ($ids as $index => $id) {
        $update->execute([$index + 1, $id, $exercise_id]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save']);
    exit;
}

echo json_encode(['ok' => true]);
