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
    if ($v === null || $v === '') {
        return '—';
    }
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
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 900px;
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
            padding-top: 4px;
        }
        .set-row {
            display: grid;
            grid-template-columns: 28px 120px 1fr 1fr auto;
            gap: 8px;
            align-items: center;
            padding: 10px 8px;
            border-radius: 8px;
            margin-top: 6px;
            font-size: 0.92rem;
            background: #f8fafc;
            border: 1px solid transparent;
        }
        .set-row.warmup { background: #fffbeb; }
        .set-row.work { background: #f8fafc; }
        .set-row.saving { border-color: #93c5fd; }
        .set-row.saved { border-color: #86efac; }
        .set-row.error { border-color: #fca5a5; }
        .set-num {
            color: var(--muted);
            font-weight: 600;
        }
        .type-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .type-toggle input {
            width: auto;
            margin: 0;
        }
        .inline-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .inline-fields .lbl {
            font-size: 0.72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .inline-fields input[type="number"] {
            width: 72px;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fff;
        }
        .btn-plan {
            background: #e5e7eb;
            color: #374151;
            border: none;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 0.75rem;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-plan:hover { background: #d1d5db; }
        .set-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            justify-content: flex-end;
        }
        .set-actions a {
            text-decoration: none;
            font-size: 0.8rem;
        }
        .set-actions .del { color: var(--red); }
        .save-status {
            font-size: 0.7rem;
            color: var(--muted);
            min-width: 48px;
            text-align: right;
        }
        .set-notes {
            grid-column: 2 / -1;
            color: var(--muted);
            font-size: 0.85rem;
            padding: 0 0 4px;
        }
        .no-sets {
            color: #9ca3af;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        @media (max-width: 720px) {
            .set-row {
                grid-template-columns: 24px 1fr;
            }
            .type-toggle,
            .inline-fields,
            .set-actions {
                grid-column: 2;
            }
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
                        <?php $row_class = $set['is_warmup'] ? 'warmup' : 'work'; ?>
                        <div class="set-row <?= $row_class ?>"
                             id="set-<?= (int)$set['id'] ?>"
                             data-set-id="<?= (int)$set['id'] ?>">

                            <div class="set-num"><?= (int)$set['sort_order'] ?></div>

                            <div class="type-toggle">
                                <input type="checkbox"
                                       class="js-auto"
                                       data-field="is_warmup"
                                       <?= $set['is_warmup'] ? 'checked' : '' ?>>
                                <span class="js-type-label"><?= $set['is_warmup'] ? 'Разминка' : 'Рабочий' ?></span>
                            </div>

                            <div class="inline-fields">
                                <span class="lbl">план</span>
                                <input type="number" class="js-auto" data-field="planned_weight"
                                       step="0.5" min="0" placeholder="кг"
                                       value="<?= $set['planned_weight'] !== null ? htmlspecialchars($set['planned_weight']) : '' ?>">
                                <input type="number" class="js-auto" data-field="planned_reps"
                                       step="1" min="0" placeholder="повт"
                                       value="<?= $set['planned_reps'] !== null ? htmlspecialchars((string)(int)$set['planned_reps']) : '' ?>">
                            </div>

                            <div class="inline-fields">
                                <span class="lbl">факт</span>
                                <input type="number" class="js-auto" data-field="actual_weight"
                                       step="0.5" min="0" placeholder="кг"
                                       value="<?= $set['actual_weight'] !== null ? htmlspecialchars($set['actual_weight']) : '' ?>">
                                <input type="number" class="js-auto" data-field="actual_reps"
                                       step="1" min="0" placeholder="повт"
                                       value="<?= $set['actual_reps'] !== null ? htmlspecialchars((string)(int)$set['actual_reps']) : '' ?>">
                                <button type="button" class="btn-plan js-copy-plan" title="Подставить план в факт">= план</button>
                            </div>

                            <div class="set-actions">
                                <span class="save-status js-status"></span>
                                <a href="reorder.php?type=set&id=<?= (int)$set['id'] ?>&dir=up" title="Выше">↑</a>
                                <a href="reorder.php?type=set&id=<?= (int)$set['id'] ?>&dir=down" title="Ниже">↓</a>
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

<script>
(function () {
    const timers = new WeakMap();

    function setStatus(row, text, state) {
        const el = row.querySelector('.js-status');
        if (el) el.textContent = text;
        row.classList.remove('saving', 'saved', 'error');
        if (state) row.classList.add(state);
    }

    function saveField(row, field, value) {
        const setId = row.getAttribute('data-set-id');
        setStatus(row, '…', 'saving');

        return fetch('api_save_set.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                set_id: Number(setId),
                field: field,
                value: value
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) throw new Error(data.error || 'fail');
            setStatus(row, 'ок', 'saved');
            setTimeout(function () {
                var status = row.querySelector('.js-status');
                if (status && status.textContent === 'ок') {
                    setStatus(row, '', null);
                }
            }, 800);
        })
        .catch(function () {
            setStatus(row, 'ошибка', 'error');
        });
    }

    function scheduleSave(input) {
        var row = input.closest('.set-row');
        if (!row) return;

        var field = input.getAttribute('data-field');

        if (input.type === 'checkbox') {
            var value = input.checked ? 1 : 0;
            var label = row.querySelector('.js-type-label');
            if (label) label.textContent = input.checked ? 'Разминка' : 'Рабочий';
            row.classList.toggle('warmup', input.checked);
            row.classList.toggle('work', !input.checked);
            saveField(row, field, value);
            return;
        }

        var value = input.value;
        clearTimeout(timers.get(input));
        timers.set(input, setTimeout(function () {
            saveField(row, field, value);
        }, 400));
    }

    document.querySelectorAll('.js-auto').forEach(function (el) {
        if (el.type === 'checkbox') {
            el.addEventListener('change', function () { scheduleSave(el); });
        } else {
            el.addEventListener('input', function () { scheduleSave(el); });
            el.addEventListener('change', function () { scheduleSave(el); });
        }
    });

    document.querySelectorAll('.js-copy-plan').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.set-row');
            var planW = row.querySelector('[data-field="planned_weight"]');
            var planR = row.querySelector('[data-field="planned_reps"]');
            var actW = row.querySelector('[data-field="actual_weight"]');
            var actR = row.querySelector('[data-field="actual_reps"]');

            if (planW && actW) {
                actW.value = planW.value;
                scheduleSave(actW);
            }
            if (planR && actR) {
                actR.value = planR.value;
                scheduleSave(actR);
            }
        });
    });
})();
</script>
</body>
</html>