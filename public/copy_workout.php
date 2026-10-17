<?php
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /');
    exit;
}

// Исходная тренировка
$stmt = $pdo->prepare("
    SELECT * FROM workouts
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$id]);
$source = $stmt->fetch();

if (!$source) {
    header('Location: /');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO workouts (workout_date, body_weight, body_weight_unit, notes)
        VALUES (CURDATE(), NULL, ?, NULL)
    ");
    $stmt->execute([
        $source['body_weight_unit'],
    ]);
    $new_workout_id = (int)$pdo->lastInsertId();

    // Упражнения
    $stmt = $pdo->prepare("
        SELECT * FROM exercises
        WHERE workout_id = ? AND deleted_at IS NULL
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$id]);
    $exercises = $stmt->fetchAll();

    $insertExercise = $pdo->prepare("
        INSERT INTO exercises (workout_id, catalog_id, name, measurement_type, sort_order, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmtSets = $pdo->prepare("
        SELECT * FROM sets
        WHERE exercise_id = ? AND deleted_at IS NULL
        ORDER BY sort_order ASC, id ASC
    ");

    $insertSet = $pdo->prepare("
        INSERT INTO sets (
            exercise_id, sort_order, is_warmup,
            planned_weight, actual_weight,
            planned_reps, actual_reps,
            planned_value, actual_value, notes
        ) VALUES (?, ?, ?, ?, NULL, ?, NULL, ?, NULL, ?)
    ");

    foreach ($exercises as $ex) {
        $insertExercise->execute([
            $new_workout_id,
            $ex['catalog_id'],
            $ex['name'],
            $ex['measurement_type'],
            $ex['sort_order'],
            null,
        ]);
        $new_exercise_id = (int)$pdo->lastInsertId();

        $stmtSets->execute([$ex['id']]);
        foreach ($stmtSets->fetchAll() as $set) {
            // План копируем, факт — пустой
            $insertSet->execute([
                $new_exercise_id,
                $set['sort_order'],
                $set['is_warmup'],
                $set['planned_weight'],
                $set['planned_reps'],
                $set['planned_value'],
                null,
            ]);
        }
    }

    $pdo->commit();
    header('Location: /view.php?id=' . $new_workout_id);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo 'Ошибка копирования: ' . htmlspecialchars($e->getMessage());
    exit;
}