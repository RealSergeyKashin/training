<?php
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /public/');
    exit;
}

// Получаем тренировку
$stmt = $pdo->prepare("
    SELECT * FROM workouts 
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$id]);
$workout = $stmt->fetch();

if (!$workout) {
    header('Location: /public/');
    exit;
}

// Пока упражнений ещё нет — просто заготовка
$exercises = [];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тренировка <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
            color: #222;
            line-height: 1.5;
        }
        h1 { margin-bottom: 8px; }
        .meta {
            color: #6b7280;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-right: 10px;
            margin-bottom: 20px;
        }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        .btn-secondary:hover { background: #d1d5db; }
        .notes {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .empty {
            color: #6b7280;
            padding: 30px 0;
            text-align: center;
            border: 2px dashed #e5e7eb;
            border-radius: 10px;
        }
    </style>
</head>
<body>

    <a href="/public/" class="btn btn-secondary">← Все тренировки</a>

    <h1>Тренировка <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?></h1>

    <div class="meta">
        <?php if ($workout['body_weight']): ?>
            Вес тела: <strong><?= htmlspecialchars($workout['body_weight']) ?> <?= htmlspecialchars($workout['body_weight_unit']) ?></strong>
            ·
        <?php endif; ?>
        Создана: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($workout['created_at']))) ?>
    </div>

    <?php if ($workout['notes']): ?>
        <div class="notes">
            <?= nl2br(htmlspecialchars($workout['notes'])) ?>
        </div>
    <?php endif; ?>

    <a href="add_exercise.php?workout_id=<?= $workout['id'] ?>" class="btn">+ Добавить упражнение</a>

    <h2>Упражнения</h2>

    <div class="empty">
        Пока нет ни одного упражнения.<br>
        Добавьте первое!
    </div>

</body>
</html>