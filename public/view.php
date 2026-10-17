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

function input_num($v): string {
    if ($v === null || $v === '') {
        return '';
    }
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

function icon_trash(): string {
    return '<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">'
        . '<path d="M3 4h8M5.5 4V3h3v1M4.5 4.5l.5 7h4l.5-7" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}

function icon_pencil(): string {
    return '<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">'
        . '<path d="M8.2 2.2l3.6 3.6L5 12.6H1.4V9z" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тренировка <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?></title>
    <link rel="stylesheet" href="/assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>">
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
               value="<?= htmlspecialchars(input_num($workout['body_weight'])) ?>">
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
                       class="icon-btn" aria-label="Выше" title="Выше">↑</a>
                    <a href="reorder.php?type=exercise&id=<?= (int)$exercise['id'] ?>&dir=down"
                       class="icon-btn" aria-label="Ниже" title="Ниже">↓</a>
                    <button type="button"
                            class="btn btn-sm js-add-set"
                            data-exercise-id="<?= (int)$exercise['id'] ?>">
                        + Подход
                    </button>
                    <a href="edit_exercise.php?id=<?= (int)$exercise['id'] ?>"
                       class="icon-btn" aria-label="Изменить" title="Изменить"><?= icon_pencil() ?></a>
                    <a href="delete.php?type=exercise&id=<?= (int)$exercise['id'] ?>"
                       class="icon-btn danger"
                       aria-label="Удалить упражнение"
                       title="Удалить упражнение"
                       onclick="return confirm('Удалить упражнение и его подходы?');"><?= icon_trash() ?></a>
                </div>
            </div>

            <?php if (empty($ex_sets)): ?>
                <div class="no-sets">Подходов пока нет</div>
            <?php else: ?>
                <div class="sets">
                    <?php foreach ($ex_sets as $set): ?>
                        <?php $row_class = $set['is_warmup'] ? 'warmup' : 'work'; ?>
                        <?php $set_notes = trim((string)($set['notes'] ?? '')); ?>
                        <div class="set-row <?= $row_class ?>"
                             id="set-<?= (int)$set['id'] ?>"
                             data-set-id="<?= (int)$set['id'] ?>">

                            <a href="reorder.php?type=set&id=<?= (int)$set['id'] ?>&dir=up"
                               class="icon-btn set-up" aria-label="Выше" title="Выше">↑</a>
                            <a href="reorder.php?type=set&id=<?= (int)$set['id'] ?>&dir=down"
                               class="icon-btn set-down" aria-label="Ниже" title="Ниже">↓</a>

                            <div class="set-num"><?= (int)$set['sort_order'] ?></div>
                            <span class="save-status js-status"></span>

                            <span class="set-lbl set-lbl-plan">План</span>
                            <input type="number" class="js-auto set-plan-w" data-field="planned_weight"
                                   step="0.5" min="0" placeholder="<?= htmlspecialchars($unit_label) ?>"
                                   value="<?= htmlspecialchars(input_num($set['planned_weight'])) ?>">
                            <input type="number" class="js-auto set-plan-r" data-field="planned_reps"
                                   step="1" min="0" placeholder="повт"
                                   value="<?= $set['planned_reps'] !== null ? htmlspecialchars((string)(int)$set['planned_reps']) : '' ?>">
                            <div class="set-tools set-tools-plan">
                                <a class="icon-btn danger" href="delete.php?type=set&id=<?= (int)$set['id'] ?>"
                                   aria-label="Удалить подход" title="Удалить подход"
                                   onclick="return confirm('Удалить этот подход?');"><?= icon_trash() ?></a>
                                <label class="icon-btn type-toggle">
                                    <input type="checkbox"
                                        class="js-auto"
                                        data-field="is_warmup"
                                        data-invert="1"
                                        aria-label="Рабочий"
                                        <?= !$set['is_warmup'] ? 'checked' : '' ?>>
                                </label>
                            </div>

                            <span class="set-lbl set-lbl-fact">Факт</span>
                            <input type="number" class="js-auto set-fact-w" data-field="actual_weight"
                                   step="0.5" min="0" placeholder="<?= htmlspecialchars($unit_label) ?>"
                                   value="<?= htmlspecialchars(input_num($set['actual_weight'])) ?>">
                            <input type="number" class="js-auto set-fact-r" data-field="actual_reps"
                                   step="1" min="0" placeholder="повт"
                                   value="<?= $set['actual_reps'] !== null ? htmlspecialchars((string)(int)$set['actual_reps']) : '' ?>">
                            <div class="set-tools set-tools-fact">
                                <button type="button" class="icon-btn js-copy-plan" aria-label="Подставить план в факт" title="Подставить план в факт">=</button>
                                <button type="button" class="icon-btn js-add-note" aria-label="Комментарий" title="Комментарий">T</button>
                            </div>

                            <div class="set-notes<?= $set_notes !== '' ? ' is-open' : '' ?>">
                                <input type="text"
                                    class="js-auto set-notes-input"
                                    data-field="notes"
                                    placeholder="Комментарий к подходу"
                                    value="<?= htmlspecialchars($set['notes'] ?? '') ?>">
                                <button type="button" class="icon-btn js-clear-note" aria-label="Удалить комментарий" title="Удалить комментарий">×</button>
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

    function showBubble(anchor, text) {
        if (!anchor) return;
        var bubble = anchor.querySelector('.bubble');
        if (!bubble) {
            bubble = document.createElement('span');
            bubble.className = 'bubble';
            anchor.appendChild(bubble);
        }
        bubble.textContent = text;
        bubble.classList.add('show');
        clearTimeout(bubble._hide);
        bubble._hide = setTimeout(function () {
            bubble.classList.remove('show');
        }, 2000);
    }

    function scheduleSave(input) {
        var row = input.closest('.set-row');
        if (!row) return;

        var field = input.getAttribute('data-field');

        if (input.type === 'checkbox') {
            var invert = input.getAttribute('data-invert') === '1';
            var checked = input.checked;
            // data-field=is_warmup: в БД 1 = разминка
            // UI: checked = рабочий → is_warmup = 0
            var value = invert ? (checked ? 0 : 1) : (checked ? 1 : 0);

            row.classList.toggle('warmup', value === 1);
            row.classList.toggle('work', value === 0);
            showBubble(input.closest('.type-toggle'), value === 0 ? 'Рабочий' : 'Разминка');
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
            showBubble(btn, 'План подставлен');
        });
    });

    document.querySelectorAll('.js-add-note').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.set-row');
            var notes = row.querySelector('.set-notes');
            var input = row.querySelector('.set-notes-input');
            notes.classList.add('is-open');
            showBubble(btn, 'Комментарий');
            if (input) input.focus();
        });
    });

    document.querySelectorAll('.js-clear-note').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.set-row');
            var notes = row.querySelector('.set-notes');
            var input = row.querySelector('.set-notes-input');
            if (input) {
                input.value = '';
                scheduleSave(input);
            }
            notes.classList.remove('is-open');
        });
    });

    document.querySelectorAll('.set-notes-input').forEach(function (input) {
        input.addEventListener('blur', function (event) {
            if (input.value.trim() !== '') return;
            var next = event.relatedTarget;
            if (next && next.classList.contains('js-clear-note')) return;
            var notes = input.closest('.set-notes');
            if (notes) notes.classList.remove('is-open');
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