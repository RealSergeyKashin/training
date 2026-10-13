<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$set_id = isset($_POST['set_id']) ? (int)$_POST['set_id'] : 0;
$workout_id = isset($_POST['workout_id']) ? (int)$_POST['workout_id'] : 0;

if ($set_id <= 0 || $workout_id <= 0) {
    header('Location: /');
    exit;
}

// Проверяем, что подход существует и принадлежит этой тренировке
$stmt = $pdo->prepare("
    SELECT s.id
    FROM sets s
    JOIN exercises e ON e.id = s.exercise_id
    WHERE s.id = ?
      AND e.workout_id = ?
      AND s.deleted_at IS NULL
      AND e.deleted_at IS NULL
");
$stmt->execute([$set_id, $workout_id]);
if (!$stmt->fetch()) {
    header('Location: /view.php?id=' . $workout_id);
    exit;
}

$actual_weight = (isset($_POST['actual_weight']) && $_POST['actual_weight'] !== '')
    ? $_POST['actual_weight']
    : null;
$actual_reps = (isset($_POST['actual_reps']) && $_POST['actual_reps'] !== '')
    ? (int)$_POST['actual_reps']
    : null;

$stmt = $pdo->prepare("
    UPDATE sets
    SET actual_weight = ?, actual_reps = ?
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$actual_weight, $actual_reps, $set_id]);

header('Location: /view.php?id=' . $workout_id . '#set-' . $set_id);
exit;