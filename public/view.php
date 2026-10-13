<?php
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/settings.php';
$weight_unit = get_setting($pdo, 'weight_unit', 'kg');
if (!in_array($weight_unit, ['kg', 'lbs'], true)) {
    $weight_unit = 'kg';
}
$unit_label = $weight_unit === 'lbs' ? 'фунты' : 'кг';


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

        .workout-edit {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
            display: grid;
            gap: 10px;
        }
        .workout-edit-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .workout-edit-row.full { flex-direction: column; align-items: stretch; }
        .workout-edit-row .lbl {
            font-size: 0.75rem;
            color: var(--muted);
            min-width: 72px;
        }
        .workout-edit-row input[type="date"],
        .workout-edit-row input[type="number"],
        .workout-edit-row select,
        .workout-edit-row textarea {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .workout-edit-row input[type="number"] { width: 100px; }
        .workout-edit-row textarea { width: 100%; resize: vertical; }

        .set-notes {
            grid-column: 2 / -1;
            padding: 0 0 4px;
        }
        .set-notes-input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.85rem;
            background: #fff;
            color: var(--text);
        }
        .set-notes-input:focus {
            outline: none;
            border-color: #93c5fd;
        }
    </style>
</head>
<body>

<div class="top-nav">
    <a href="/" class="btn btn-secondary btn-sm">← Все тренировки</a>
</div>

<div class="workout-edit" data-workout-id="<?= (int)$workout['id'] ?>">
    <div class="workout-edit-row">
        <label class="lbl">Дата</label>
        <input type="date" class="js-workout-auto" data-field="workout_date"
               value="<?= htmlspecialchars($workout['workout_date']) ?>">
    </div>

    <div class="workout-edit-row">
        <label class="lbl">Вес тела</label>
        <input type="number" class="js-workout-auto" data-field="body_weight"
               step="0.1" min="0" placeholder="0"
               value="<?= $workout['body_weight'] !== null ? htmlspecialchars($workout['body_weight']) : '' ?>">
        <select id="global_weight_unit" title="Единицы для всего приложения">
            <option value="kg" <?= $weight_unit === 'kg' ? 'selected' : '' ?>>кг</option>
            <option value="lbs" <?= $weight_unit === 'lbs' ? 'selected' : '' ?>>фунты</option>
        </select>
        <span class="save-status js-workout-status"></span>
    </div>

    <div class="workout-edit-row full">
        <label class="lbl">Комментарий</label>
        <textarea class="js-workout-auto" data-field="notes" rows="2"
                  placeholder="Комментарий к тренировке"><?= htmlspecialchars($workout['notes'] ?? '') ?></textarea>
    </div>
</div>

<div class="actions">
    <a href="copy_workout.php?id=<?= (int)$workout['id'] ?>" class="btn btn-secondary btn-sm">Копировать</a>
    <a href="delete.php?type=workout&id=<?= (int)$workout['id'] ?>"
       class="btn btn-danger btn-sm"
       onclick="return confirm('Удалить эту тренировку?');">Удалить</a>
    <a href="add_exercise.php?workout_id=<?= (int)$workout['id'] ?>" class="btn btn-sm">+ Упражнение</a>
</div>

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
                    <button type="button"
                            class="btn btn-sm js-add-set"
                            data-exercise-id="<?= (int)$exercise['id'] ?>">
                        + Подход
                    </button>
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
                                       step="0.5" min="0" placeholder="<?= htmlspecialchars($unit_label) ?>"
                                       value="<?= $set['planned_weight'] !== null ? htmlspecialchars($set['planned_weight']) : '' ?>">
                                <input type="number" class="js-auto" data-field="planned_reps"
                                       step="1" min="0" placeholder="повт"
                                       value="<?= $set['planned_reps'] !== null ? htmlspecialchars((string)(int)$set['planned_reps']) : '' ?>">
                            </div>

                            <div class="inline-fields">
                                <span class="lbl">факт</span>
                                <input type="number" class="js-auto" data-field="actual_weight"
                                       step="0.5" min="0" placeholder="<?= htmlspecialchars($unit_label) ?>"
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

                            <div class="set-notes">
                                <input type="text"
                                    class="js-auto set-notes-input"
                                    data-field="notes"
                                    placeholder="Комментарий к подходу"
                                    value="<?= htmlspecialchars($set['notes'] ?? '') ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
(function () {

    (function scrollToFocus() {
        const params = new URLSearchParams(window.location.search);
        const focus = params.get('focus');
        if (!focus) return;
        const el = document.getElementById(focus);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('saved');
            setTimeout(() => el.classList.remove('saved'), 1200);
        }
    })();

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

// --- автосохранение тренировки ---
const workoutBox = document.querySelector('.workout-edit');
const workoutTimers = new WeakMap();

function workoutStatus(text) {
    const el = document.querySelector('.js-workout-status');
    if (el) el.textContent = text;
}

function saveWorkoutField(field, value) {
    if (!workoutBox) return;
    const id = workoutBox.getAttribute('data-workout-id');
    workoutStatus('…');
    fetch('api_save_workout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id), field: field, value: value })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok) throw new Error('fail');
        workoutStatus('ок');
        setTimeout(() => { if (document.querySelector('.js-workout-status')?.textContent === 'ок') workoutStatus(''); }, 800);
    })
    .catch(() => workoutStatus('ошибка'));
}

document.querySelectorAll('.js-workout-auto').forEach(el => {
    const handler = () => {
        const field = el.getAttribute('data-field');
        const value = el.value;
        clearTimeout(workoutTimers.get(el));
        workoutTimers.set(el, setTimeout(() => saveWorkoutField(field, value), 400));
    };
    el.addEventListener('input', handler);
    el.addEventListener('change', handler);
});

// --- глобальные единицы ---
const unitSelect = document.getElementById('global_weight_unit');
if (unitSelect) {
    unitSelect.addEventListener('change', () => {
        fetch('api_save_setting.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: 'weight_unit', value: unitSelect.value })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) location.reload();
        });
    });
}

// --- добавить подход ---
document.querySelectorAll('.js-add-set').forEach(btn => {
    btn.addEventListener('click', () => {
        const exerciseId = Number(btn.getAttribute('data-exercise-id'));
        btn.disabled = true;

        fetch('api_add_set.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ exercise_id: exerciseId })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) throw new Error('fail');
            // query-параметр focus меняет URL → будет полная перезагрузка
            window.location.href = 'view.php?id=' + data.workout_id +
                '&focus=set-' + data.set_id;
        })
        .catch(() => {
            alert('Не удалось добавить подход');
            btn.disabled = false;
        });
    });
});

</script>
</body>
</html>