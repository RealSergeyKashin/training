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

if ($exercise_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.id, e.workout_id
    FROM exercises e
    JOIN workouts w ON w.id = e.workout_id
    WHERE e.id = ? AND e.deleted_at IS NULL AND w.deleted_at IS NULL
");
$stmt->execute([$exercise_id]);
$exercise = $stmt->fetch();

if (!$exercise) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

// Следующий порядок
$stmt = $pdo->prepare("
    SELECT COALESCE(MAX(sort_order), 0) + 1
    FROM sets
    WHERE exercise_id = ? AND deleted_at IS NULL
");
$stmt->execute([$exercise_id]);
$next_order = (int)$stmt->fetchColumn();

// Последний подход этого упражнения (если есть) — как шаблон
$stmt = $pdo->prepare("
    SELECT *
    FROM sets
    WHERE exercise_id = ? AND deleted_at IS NULL
    ORDER BY sort_order DESC, id DESC
    LIMIT 1
");
$stmt->execute([$exercise_id]);
$prev = $stmt->fetch();

if ($prev) {
    $is_warmup = (int)$prev['is_warmup'];
    $planned_weight = $prev['planned_weight'];
    $planned_reps = $prev['planned_reps'];
    if ($planned_reps === null || (int)$planned_reps < 1) {
        $planned_reps = 1;
    } else {
        $planned_reps = (int)$planned_reps;
    }
    // Факт: вес = план, повторы = 0
    $actual_weight = $planned_weight;
    $actual_reps = 0;
    $notes = null; // комментарий не копируем — это уже другая попытка
} else {
    // Первый подход в упражнении
    $is_warmup = 0;          // рабочий
    $planned_weight = null;
    $planned_reps = 1;       // минимум 1
    $actual_weight = null;   // плана веса ещё нет
    $actual_reps = 0;
    $notes = null;
}

$stmt = $pdo->prepare("
    INSERT INTO sets (
        exercise_id, sort_order, is_warmup,
        planned_weight, actual_weight,
        planned_reps, actual_reps, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $exercise_id,
    $next_order,
    $is_warmup,
    $planned_weight,
    $actual_weight,
    $planned_reps,
    $actual_reps,
    $notes,
]);

$set_id = (int)$pdo->lastInsertId();

echo json_encode([
    'ok' => true,
    'set_id' => $set_id,
    'workout_id' => (int)$exercise['workout_id'],
]);