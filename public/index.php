<?php
require_once __DIR__ . '/../config/database.php';

// Получаем список тренировок (только не удалённые)
$stmt = $pdo->query("
    SELECT id, workout_date, body_weight, body_weight_unit, notes, created_at
    FROM workouts
    WHERE deleted_at IS NULL
    ORDER BY workout_date DESC, id DESC
");
$workouts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои тренировки</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
            color: #222;
            line-height: 1.5;
        }
        h1 { margin-bottom: 10px; }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 30px;
        }
        .btn:hover { background: #1d4ed8; }
        .workout {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 12px;
            background: #fff;
        }
        .workout-date {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .workout-meta {
            color: #6b7280;
            font-size: 14px;
        }
        .empty {
            color: #6b7280;
            padding: 40px 0;
            text-align: center;
        }
    </style>
</head>
<body>

    <h1>Мои тренировки</h1>

    <a href="create.php" class="btn">+ Новая тренировка</a>

    <?php if (empty($workouts)): ?>
        <div class="empty">
            Пока нет ни одной тренировки.<br>
            Создайте первую!
        </div>
    <?php else: ?>
        <?php foreach ($workouts as $workout): ?>
            
            <div class="workout">
                <a href="view.php?id=<?= $workout['id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                    <div class="workout-date">
                        <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?>
                    </div>
                    <div class="workout-meta">
                        <?php if ($workout['body_weight']): ?>
                            Вес: <?= htmlspecialchars($workout['body_weight']) ?> <?= htmlspecialchars($workout['body_weight_unit']) ?>
                            ·
                        <?php endif; ?>
                        Создана: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($workout['created_at']))) ?>
                    </div>
                    <?php if ($workout['notes']): ?>
                        <div style="margin-top:8px;"><?= nl2br(htmlspecialchars($workout['notes'])) ?></div>
                    <?php endif; ?>
                </a>
                <div style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="copy_workout.php?id=<?= (int)$workout['id'] ?>"
                    style="font-size: 13px; color: #2563eb; text-decoration: none;">Копировать</a>
                    <a href="delete.php?type=workout&id=<?= (int)$workout['id'] ?>"
                    onclick="return confirm('Удалить эту тренировку?');"
                    style="font-size: 13px; color: #b91c1c; text-decoration: none;">Удалить</a>
                </div>
            </div>

        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>