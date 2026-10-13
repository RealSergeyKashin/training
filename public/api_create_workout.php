<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$unit = get_setting($pdo, 'weight_unit', 'kg');
if (!in_array($unit, ['kg', 'lbs'], true)) {
    $unit = 'kg';
}

$stmt = $pdo->prepare("
    INSERT INTO workouts (workout_date, body_weight_unit)
    VALUES (CURDATE(), ?)
");
$stmt->execute([$unit]);
$id = (int)$pdo->lastInsertId();

header('Location: /view.php?id=' . $id);
exit;