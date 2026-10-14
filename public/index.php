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
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>

    <h1>Мои тренировки</h1>

    <a href="api_create_workout.php" class="btn">+ Новая тренировка</a>

    <?php if (empty($workouts)): ?>
        <div class="empty">
            Пока нет ни одной тренировки.<br>
            Создайте первую!
        </div>
    <?php else: ?>
        <?php foreach ($workouts as $workout): ?>
            
            <div class="workout">
                <a href="view.php?id=<?= (int)$workout['id'] ?>" class="workout-card-link">
                    <div class="workout-date">
                        <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?>
                    </div>
                    <div class="workout-meta">
                        <?php if ($workout['body_weight']): ?>
                            Вес: <?= htmlspecialchars($workout['body_weight']) ?>
                            <?= htmlspecialchars($workout['body_weight_unit']) ?> ·
                        <?php endif; ?>
                        Создана: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($workout['created_at']))) ?>
                    </div>
                    <?php if ($workout['notes']): ?>
                        <div class="workout-notes"><?= nl2br(htmlspecialchars($workout['notes'])) ?></div>
                    <?php endif; ?>
                </a>
                <div class="workout-card-actions">
                    <a class="link-action" href="copy_workout.php?id=<?= (int)$workout['id'] ?>">Копировать</a>
                    <a class="link-action danger"
                    href="delete.php?type=workout&id=<?= (int)$workout['id'] ?>"
                    onclick="return confirm('Удалить эту тренировку?');">Удалить</a>
                </div>
            </div>

        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>