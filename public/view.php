<?php
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /');
    exit;
}

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

$stmt = $pdo->prepare("
    SELECT id, name, notes, sort_order, measurement_type
    FROM exercises
    WHERE workout_id = ? AND deleted_at IS NULL
    ORDER BY sort_order ASC, id ASC
");
$stmt->execute([$id]);
$exercises = $stmt->fetchAll();

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

function fmt_num($v) {
    if ($v === null || $v === '') return '—';
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тренировка <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?></title>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #fff;
            --border: #e5e7eb;
            --text: #111827;
            --muted: #6b7280;
            --blue: #2563eb;
            --blue-hover: #1d4ed8;
            --red: #b91c1c;
            --warmup: #fef3c7;
            --work: #eff6ff;
            --done: #ecfdf5;
            --pending: #fff7ed;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 820px;
            margin: 0 auto;
            padding: 24px 16px 48px;
            color: var(--text);
            background: var(--bg);
            line-height: 1.45;
        }
        a { color: var(--blue); }
        h1 { margin: 0 0 6px; font-size: 1.6rem; }
        h2 { margin: 28px 0 12px; font-size: 1.15rem; }
        .top-nav { margin-bottom: 16px; }
        .meta { color: var(--muted); font-size: 0.95rem; margin-bottom: 12px; }
        .notes {
            background: #f3f4f6;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.95rem;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
        }
        .btn {
            display: inline-block;
            background: var(--blue);
            color: #fff !important;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            border: none;
        }
        .btn:hover { background: var(--blue-hover); }
        .btn-secondary {
            background: #e5e7eb;
            color: #374151 !important;
        }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-danger {
            background: #fee2e2;
            color: var(--red) !important;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        .empty {
            color: var(--muted);
            padding: 28px 16px;
            text-align: center;
            border: 2px dashed var(--border);
            border-radius: 10px;
            background: var(--card);
        }
        .exercise {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .exercise-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }
        .exercise-title {
            font-size: 1.1rem;
            font-weight: 650;
        }
        .exercise-notes {
            color: var(--muted);
            font-size: 0.9rem;
            margin-top: 6px;
        }
        .exercise-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .sets {
            margin-top: 12px;
            border-top: 1px solid var(--border);
        }
        .set-row {
            display: grid;
            grid-template-columns: 28px 88px 1fr 1fr auto;
            gap: 8px;
            align-items: center;
            padding: 10px 8px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.92rem;
            border-radius: 8px;
            margin-top: 6px;
        }
        .set-row.warmup { background: var(--warmup); }
        .set-row.work { background: var(--work); }
        .set-row.done { background: var(--done); }
        .set-row.pending { background: var(--pending); }
        .set-num { color: var(--muted); font-weight: 600; }
        .set-type { font-weight: 600; font-size: 0.85rem; }
        .set-plan, .set-fact { color: var(--text); }
        .set-fact strong { font-weight: 700; }
        .set-actions { white-space: nowrap; }
        .set-actions a {
            text-decoration: none;
            font-size: 0.8rem;
            margin-left: 6px;
        }
        .set-actions .del { color: var(--red); }
        .set-notes {
            grid-column: 2 / -1;
            color: var(--muted);
            font-size: 0.85rem;
            margin-top: -2px;
            padding-bottom: 4px;
        }
        .no-sets {
            color: #9ca3af;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        @media (max-width: 640px) {
            .set-row {
                grid-template-columns: 24px 1fr;
                gap: 4px 8px;
            }
            .set-type, .set-plan, .set-fact, .set-actions {
                grid-column: 2;
            }
            .set-actions { margin-top: 2px; }
        }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="/" class="btn btn-secondary btn-sm">← Все тренировки</a>
    </div>

    <h1>Тренировка <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?></h1>

    <div class="meta">
        <?php if ($workout['body_weight']): ?>
            Вес тела: <strong><?= htmlspecialchars(fmt_num($workout['body_weight'])) ?> <?= htmlspecialchars($workout['body_weight_unit']) ?></strong>
            ·
        <?php endif; ?>
        Создана: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($workout['created_at']))) ?>
    </div>

    <div class="actions">
        <a href="edit_workout.php?id=<?= (int)$workout['id'] ?>" class="btn btn-secondary btn-sm">Изменить</a>
        <a href="copy_workout.php?id=<?= (int)$workout['id'] ?>" class="btn btn-secondary btn-sm">Копировать</a>
        <a href="delete.php?type=workout&id=<?= (int)$workout['id'] ?>"
           class="btn btn-danger btn-sm"
           onclick="return confirm('Удалить эту тренировку?');">Удалить</a>
        <a href="add_exercise.php?workout_id=<?= (int)$workout['id'] ?>" class="btn btn-sm">+ Упражнение</a>
    </div>

    <?php if ($workout['notes']): ?>
        <div class="notes"><?= nl2br(htmlspecialchars($workout['notes'])) ?></div>
    <?php endif; ?>

    <h2>Упражнения</h2>

    <?php if (empty($exercises)): ?>
        <div class="empty">
            Пока нет ни одного упражнения.<br>
            Добавьте первое!
        </div>
    <?php else: ?>
        <?php foreach ($exercises as $exercise): ?>
            <?php $ex_sets = $sets_by_exercise[(int)$exercise['id']] ?? []; ?>
            <div class="exercise">
                <div class="exercise-head">
                    <div>
                        <div class="exercise-title">
                            <?= (int)$exercise['sort_order'] ?>. <?= htmlspecialchars($exercise['name']) ?>
                        </div>
                        <?php if ($exercise['notes']): ?>
                            <div class="exercise-notes"><?= nl2br(htmlspecialchars($exercise['notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="exercise-actions">
                        <a href="reorder.php?type=exercise&id=<?= (int)$exercise['id'] ?>&dir=up"
                            class="btn btn-secondary btn-sm" title="Выше">↑</a>
                        <a href="reorder.php?type=exercise&id=<?= (int)$exercise['id'] ?>&dir=down"
                            class="btn btn-secondary btn-sm" title="Ниже">↓</a>
                        <a href="add_set.php?exercise_id=<?= (int)$exercise['id'] ?>" class="btn btn-sm">+ Подход</a>
                        <a href="edit_exercise.php?id=<?= (int)$exercise['id'] ?>" class="btn btn-secondary btn-sm">Изменить</a>
                        <a href="delete.php?type=exercise&id=<?= (int)$exercise['id'] ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Удалить упражнение и его подходы?');">Удалить</a>
                    </div>
                </div>

                <?php if (empty($ex_sets)): ?>
                    <div class="no-sets">Подходов пока нет</div>
                <?php else: ?>
                    <div class="sets">
                        <?php foreach ($ex_sets as $set): ?>
                            <?php
                                $has_actual = ($set['actual_weight'] !== null && $set['actual_weight'] !== '')
                                    || ($set['actual_reps'] !== null && $set['actual_reps'] !== '');
                                $row_class = $set['is_warmup'] ? 'warmup' : 'work';
                                $row_class .= $has_actual ? ' done' : ' pending';
                            ?>
                            <div class="set-row <?= $row_class ?>">
                                <div class="set-num"><?= (int)$set['sort_order'] ?></div>
                                <div class="set-type"><?= $set['is_warmup'] ? 'Разминка' : 'Рабочий' ?></div>
                                <div class="set-plan">
                                    План: <?= htmlspecialchars(fmt_num($set['planned_weight'])) ?> кг
                                    × <?= $set['planned_reps'] !== null ? (int)$set['planned_reps'] : '—' ?>
                                </div>
                                <div class="set-fact">
                                    Факт:
                                    <?php if ($has_actual): ?>
                                        <strong><?= htmlspecialchars(fmt_num($set['actual_weight'])) ?> кг
                                        × <?= $set['actual_reps'] !== null ? (int)$set['actual_reps'] : '—' ?></strong>
                                    <?php else: ?>
                                        <span style="color:#c2410c;">не заполнен</span>
                                    <?php endif; ?>
                                </div>
                                <div class="set-actions">
                                    <a href="reorder.php?type=set&id=<?= (int)$set['id'] ?>&dir=up" title="Выше">↑</a>
                                    <a href="reorder.php?type=set&id=<?= (int)$set['id'] ?>&dir=down" title="Ниже">↓</a>
                                    <a href="edit_set.php?id=<?= (int)$set['id'] ?>">изменить</a>
                                    <a class="del" href="delete.php?type=set&id=<?= (int)$set['id'] ?>"
                                       onclick="return confirm('Удалить этот подход?');">удалить</a>
                                </div>
                                <?php if ($set['notes']): ?>
                                    <div class="set-notes"><?= nl2br(htmlspecialchars($set['notes'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>