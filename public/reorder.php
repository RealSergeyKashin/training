<?php
require_once __DIR__ . '/../config/database.php';

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dir = $_GET['dir'] ?? '';

if ($id <= 0 || !in_array($type, ['exercise', 'set'], true) || !in_array($dir, ['up', 'down'], true)) {
    header('Location: /');
    exit;
}

function swap_sort(PDO $pdo, string $table, string $parent_col, int $parent_id, int $id, string $dir): void
{
    // Текущая запись
    $stmt = $pdo->prepare("
        SELECT id, sort_order FROM {$table}
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    if (!$current) {
        return;
    }

    $op = $dir === 'up' ? '<' : '>';
    $order = $dir === 'up' ? 'DESC' : 'ASC';

    // Соседняя запись в том же родителе
    $stmt = $pdo->prepare("
        SELECT id, sort_order FROM {$table}
        WHERE {$parent_col} = ?
          AND deleted_at IS NULL
          AND sort_order {$op} ?
        ORDER BY sort_order {$order}
        LIMIT 1
    ");
    $stmt->execute([$parent_id, $current['sort_order']]);
    $neighbor = $stmt->fetch();

    if (!$neighbor) {
        return; // уже крайний
    }

    // Меняем sort_order местами
    $stmt = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
    $stmt->execute([$neighbor['sort_order'], $current['id']]);
    $stmt->execute([$current['sort_order'], $neighbor['id']]);
}

if ($type === 'exercise') {
    $stmt = $pdo->prepare("
        SELECT id, workout_id FROM exercises
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: /');
        exit;
    }

    swap_sort($pdo, 'exercises', 'workout_id', (int)$row['workout_id'], $id, $dir);
    header('Location: /view.php?id=' . (int)$row['workout_id']);
    exit;
}

if ($type === 'set') {
    $stmt = $pdo->prepare("
        SELECT s.id, s.exercise_id, e.workout_id
        FROM sets s
        JOIN exercises e ON e.id = s.exercise_id
        WHERE s.id = ? AND s.deleted_at IS NULL AND e.deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: /');
        exit;
    }

    swap_sort($pdo, 'sets', 'exercise_id', (int)$row['exercise_id'], $id, $dir);
    header('Location: /view.php?id=' . (int)$row['workout_id']);
    exit;
}