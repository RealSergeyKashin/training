<?php
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /');
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
    header('Location: /');
    exit;
}

// Упражнения этой тренировки
$stmt = $pdo->prepare("
    SELECT id, name, notes, sort_order, measurement_type
    FROM exercises
    WHERE workout_id = ? AND deleted_at IS NULL
    ORDER BY sort_order ASC, id ASC
");
$stmt->execute([$id]);
$exercises = $stmt->fetchAll();

// Подходы по всем упражнениям тренировки
$sets_by_exercise = [];
if ($exercises) {
    $exercise_ids = array_column($exercises, 'id');
    $placeholders = implode(',', array_fill(0, count($exercise_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT *
        FROM sets
        WHERE exercise_id IN ($placeholders) AND deleted_at IS NULL
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute($exercise_ids);
    foreach ($stmt->fetchAll() as $set) {
        $sets_by_exercise[(int)$set['exercise_id']][] = $set;
    }
}

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

    <a href="/" class="btn btn-secondary">← Все тренировки</a>

    <h1>Тренировка <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?></h1>

    <div class="meta">
        <?php if ($workout['body_weight']): ?>
            Вес тела: <strong><?= htmlspecialchars($workout['body_weight']) ?> <?= htmlspecialchars($workout['body_weight_unit']) ?></strong>
            ·
        <?php endif; ?>
        Создана: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($workout['created_at']))) ?>
    </div>


    <div style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="copy_workout.php?id=<?= (int)$workout['id'] ?>" class="btn btn-secondary">Копировать как шаблон</a>
        <a href="delete.php?type=workout&id=<?= (int)$workout['id'] ?>"
        class="btn btn-secondary"
        style="color: #b91c1c;"
        onclick="return confirm('Удалить эту тренировку?');">Удалить тренировку</a>
    </div>


    <?php if ($workout['notes']): ?>
        <div style="background:#f9fafb; padding:16px; border-radius:8px; margin-bottom:30px;">
            <?= nl2br(htmlspecialchars($workout['notes'])) ?>
        </div>
    <?php endif; ?>

    <a href="add_exercise.php?workout_id=<?= $workout['id'] ?>" class="btn">+ Добавить упражнение</a>

    <h2>Упражнения</h2>

    <?php if (empty($exercises)): ?>
        <div class="empty" style="border:2px dashed #e5e7eb; border-radius:10px;">
            Пока нет ни одного упражнения.<br>
            Добавьте первое!
        </div>
    <?php else: ?>
        <?php foreach ($exercises as $exercise): ?>
            <?php $ex_sets = $sets_by_exercise[(int)$exercise['id']] ?? []; ?>
            <div style="border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 20px; margin-bottom: 16px; background: #fff;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="font-size: 18px; font-weight: 600;">
                        <?= (int)$exercise['sort_order'] ?>. <?= htmlspecialchars($exercise['name']) ?>
                    </div>
                    <a href="add_set.php?exercise_id=<?= (int)$exercise['id'] ?>"
                    style="display: inline-block; background: #2563eb; color: white; padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500;">
                        + Подход
                    </a>

                    <a href="delete.php?type=exercise&id=<?= (int)$exercise['id'] ?>"
                    onclick="return confirm('Удалить упражнение и его подходы?');"
                    style="font-size: 13px; color: #b91c1c; text-decoration: none; margin-left: 8px;">
                        Удалить
                    </a>
                    
                </div>

                <?php if ($exercise['notes']): ?>
                    <div style="color: #6b7280; font-size: 14px; margin-top: 6px;">
                        <?= nl2br(htmlspecialchars($exercise['notes'])) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($ex_sets)): ?>
                    <div style="color: #9ca3af; font-size: 14px; margin-top: 12px;">
                        Подходов пока нет
                    </div>
                <?php else: ?>
                    <div style="margin-top: 12px;">
                        <?php foreach ($ex_sets as $set): ?>
                            <div style="border-top: 1px solid #f3f4f6; padding: 10px 0; font-size: 14px;">
                                <strong>
                                    <?= (int)$set['sort_order'] ?>.
                                    <?= $set['is_warmup'] ? 'Разминка' : 'Рабочий' ?>
                                </strong>
                                ·
                                план:
                                <?= $set['planned_weight'] !== null ? htmlspecialchars($set['planned_weight']) . ' кг' : '—' ?>
                                ×
                                <?= $set['planned_reps'] !== null ? (int)$set['planned_reps'] : '—' ?>
                                ·
                                факт:
                                <?= $set['actual_weight'] !== null ? htmlspecialchars($set['actual_weight']) . ' кг' : '—' ?>
                                ×
                                <?= $set['actual_reps'] !== null ? (int)$set['actual_reps'] : '—' ?>
                                <?php if ($set['notes']): ?>
                                    <div style="color: #6b7280; margin-top: 4px;">
                                        <?= nl2br(htmlspecialchars($set['notes'])) ?>
                                    </div>
                                <?php endif; ?>

                                <a href="delete.php?type=set&id=<?= (int)$set['id'] ?>"
                                onclick="return confirm('Удалить этот подход?');"
                                style="margin-left: 8px; color: #b91c1c; text-decoration: none; font-size: 13px;">
                                    удалить
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>