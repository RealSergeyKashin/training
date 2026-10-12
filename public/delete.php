<?php
require_once __DIR__ . '/../config/database.php';

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || !in_array($type, ['workout', 'exercise', 'set'], true)) {
    header('Location: /');
    exit;
}

if ($type === 'workout') {
    $stmt = $pdo->prepare("
        UPDATE workouts SET deleted_at = NOW()
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    header('Location: /');
    exit;
}

if ($type === 'exercise') {
    // Узнаём workout_id для возврата
    $stmt = $pdo->prepare("SELECT workout_id FROM exercises WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: /');
        exit;
    }
    $workout_id = (int)$row['workout_id'];

    $stmt = $pdo->prepare("UPDATE exercises SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    // Подходы этого упражнения тоже скрываем
    $stmt = $pdo->prepare("UPDATE sets SET deleted_at = NOW() WHERE exercise_id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);

    header('Location: /view.php?id=' . $workout_id);
    exit;
}

if ($type === 'set') {
    $stmt = $pdo->prepare("
        SELECT s.id, e.workout_id
        FROM sets s
        JOIN exercises e ON e.id = s.exercise_id
        WHERE s.id = ? AND s.deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: /');
        exit;
    }
    $workout_id = (int)$row['workout_id'];

    $stmt = $pdo->prepare("UPDATE sets SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: /view.php?id=' . $workout_id);
    exit;
}