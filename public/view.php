<?php
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/settings.php';
$weight_unit = get_setting($pdo, 'weight_unit', 'kg');
if (!in_array($weight_unit, ['kg', 'lbs'], true)) {
    $weight_unit = 'kg';
}
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
    return '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">'
        . '<path d="M2.4 4.2h11.2M6.1 4.2V2.5h3.8v1.7M3.6 4.2l.8 9.3h7.2l.8-9.3" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}

function icon_pencil(): string {
    return '<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">'
        . '<path d="M1 13.1l2.5-1.1 8.2-8.2-1.6-1.6L1.9 10.4z" fill="none" stroke="#1f2937" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<path d="M1.9 10.4L3.5 12" fill="none" stroke="#1f2937" stroke-width="1.2" stroke-linecap="round"/>'
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

<?php
$weekdays = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
$weekday = $weekdays[(int)date('w', strtotime($workout['workout_date']))];
$unit_short = $weight_unit === 'lbs' ? 'фнт' : 'кг';
?>
<nav class="crumbs" aria-label="Навигация">
    <a href="/" class="back-link">Все тренировки</a>
</nav>

<div class="workout-edit" data-workout-id="<?= (int)$workout['id'] ?>">
    <div class="workout-title-row">
        <h2>Тренировка</h2>
        <input type="date" id="workout-date" name="workout_date" class="js-workout-auto date-ghost" data-field="workout_date"
               aria-label="Дата"
               value="<?= htmlspecialchars($workout['workout_date']) ?>">
        <label for="workout-date" class="weekday" id="workout-weekday"><?= htmlspecialchars($weekday) ?></label>
    </div>

    <div class="workout-edit-row">
        <label class="lbl">Вес тела</label>
        <input type="number" id="body-weight" name="body_weight" class="js-workout-auto body-weight" data-field="body_weight"
               inputmode="decimal" step="0.1" min="0"
               aria-label="Вес тела"
               value="<?= htmlspecialchars(input_num($workout['body_weight'])) ?>">
        <select id="global_weight_unit" name="weight_unit" class="unit-ghost" title="Единицы для всего приложения">
            <option value="kg" <?= $weight_unit === 'kg' ? 'selected' : '' ?>>кг</option>
            <option value="lbs" <?= $weight_unit === 'lbs' ? 'selected' : '' ?>>фнт</option>
        </select>
        <span class="save-status js-workout-status"></span>
    </div>

    <div class="note-line<?= trim((string)($workout['notes'] ?? '')) === '' ? ' is-empty' : '' ?>">
        <div class="note-read">
            <span class="note-text"><?= htmlspecialchars(trim((string)($workout['notes'] ?? '')) === '' ? 'Комментарий к тренировке' : $workout['notes']) ?></span><span class="note-pencil" aria-hidden="true"><?= icon_pencil() ?></span>
        </div>
        <textarea id="workout-notes" name="workout_notes" class="js-workout-auto workout-notes-input plain-note" data-field="notes" rows="1"
                  placeholder="Комментарий к тренировке"><?= htmlspecialchars($workout['notes'] ?? '') ?></textarea>
        <button type="button" class="icon-btn note-clear js-clear-note" aria-label="Очистить комментарий" title="Очистить комментарий">×</button>
    </div>
</div>

<div class="actions">
    <div class="actions-main">
        <a href="add_exercise.php?workout_id=<?= (int)$workout['id'] ?>" class="btn btn-sm">+ Упражнение</a>
        <a href="copy_workout.php?id=<?= (int)$workout['id'] ?>" class="btn btn-secondary btn-sm">Дублировать</a>
    </div>
    <a href="delete.php?type=workout&id=<?= (int)$workout['id'] ?>"
       class="btn btn-danger btn-sm"
       onclick="return confirm('Удалить эту тренировку?');">Удалить</a>
</div>

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
                    <div class="exercise-actions-main">
                        <a href="edit_exercise.php?id=<?= (int)$exercise['id'] ?>"
                           class="icon-btn" aria-label="Изменить" title="Изменить"><?= icon_pencil() ?></a>
                        <button type="button"
                                class="btn btn-sm js-add-set"
                                data-exercise-id="<?= (int)$exercise['id'] ?>">
                            + Подход
                        </button>
                        <a href="reorder.php?type=exercise&id=<?= (int)$exercise['id'] ?>&dir=up"
                           class="icon-btn" aria-label="Выше" title="Выше">↑</a>
                        <a href="reorder.php?type=exercise&id=<?= (int)$exercise['id'] ?>&dir=down"
                           class="icon-btn" aria-label="Ниже" title="Ниже">↓</a>
                    </div>
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
                <div class="sets" data-exercise-id="<?= (int)$exercise['id'] ?>">
                    <div class="set-head" aria-hidden="true">
                        <span class="set-grip-space"></span>
                        <div class="set-main">
                            <span class="set-w set-head-plan">План</span>
                            <span class="set-r"></span>
                            <span class="set-w">Факт</span>
                        </div>
                    </div>
                    <?php foreach ($ex_sets as $set): ?>
                        <?php
                            $set_notes = trim((string)($set['notes'] ?? ''));
                            $sid = (int)$set['id'];
                        ?>
                        <div class="set-row"
                             id="set-<?= (int)$set['id'] ?>"
                             data-set-id="<?= (int)$set['id'] ?>">
                            <div class="set-line">
                                <button type="button" class="set-grip" aria-label="Перетащить подход" title="Перетащить подход">
                                    <svg width="10" height="16" viewBox="0 0 10 16" aria-hidden="true">
                                        <circle cx="2" cy="2" r="1.2" fill="currentColor"/>
                                        <circle cx="8" cy="2" r="1.2" fill="currentColor"/>
                                        <circle cx="2" cy="8" r="1.2" fill="currentColor"/>
                                        <circle cx="8" cy="8" r="1.2" fill="currentColor"/>
                                        <circle cx="2" cy="14" r="1.2" fill="currentColor"/>
                                        <circle cx="8" cy="14" r="1.2" fill="currentColor"/>
                                    </svg>
                                </button>
                                <div class="set-main">
                                    <input type="number" id="set-<?= $sid ?>-planned-weight" name="set-<?= $sid ?>-planned-weight"
                                           class="js-auto set-w" data-field="planned_weight"
                                           inputmode="decimal" step="0.5" min="0"
                                           placeholder="<?= htmlspecialchars($unit_short) ?>"
                                           aria-label="План, вес"
                                           value="<?= htmlspecialchars(input_num($set['planned_weight'])) ?>">
                                    <input type="number" id="set-<?= $sid ?>-planned-reps" name="set-<?= $sid ?>-planned-reps"
                                           class="js-auto set-r" data-field="planned_reps"
                                           inputmode="numeric" step="1" min="0"
                                           placeholder="×"
                                           aria-label="План, повторы"
                                           value="<?= $set['planned_reps'] !== null ? htmlspecialchars((string)(int)$set['planned_reps']) : '' ?>">
                                    <input type="number" id="set-<?= $sid ?>-actual-weight" name="set-<?= $sid ?>-actual-weight"
                                           class="js-auto set-w set-fact" data-field="actual_weight"
                                           inputmode="decimal" step="0.5" min="0"
                                           placeholder="<?= htmlspecialchars($unit_short) ?>"
                                           aria-label="Факт, вес"
                                           value="<?= htmlspecialchars(input_num($set['actual_weight'])) ?>">
                                    <input type="number" id="set-<?= $sid ?>-actual-reps" name="set-<?= $sid ?>-actual-reps"
                                           class="js-auto set-r set-fact" data-field="actual_reps"
                                           inputmode="numeric" step="1" min="0"
                                           placeholder="×"
                                           aria-label="Факт, повторы"
                                           value="<?= $set['actual_reps'] !== null ? htmlspecialchars((string)(int)$set['actual_reps']) : '' ?>">
                                    <button type="button" class="icon-btn js-copy-plan" aria-label="Подставить план в факт" title="Подставить план в факт">=</button>
                                    <button type="button" class="icon-btn js-add-note<?= $set_notes !== '' ? ' is-hidden' : '' ?>" aria-label="Комментарий" title="Комментарий"><?= icon_pencil() ?></button>
                                </div>
                                <a class="icon-btn danger" href="delete.php?type=set&id=<?= (int)$set['id'] ?>"
                                   aria-label="Удалить подход" title="Удалить подход"
                                   onclick="return confirm('Удалить этот подход?');"><?= icon_trash() ?></a>
                            </div>
                            <span class="save-status js-status"></span>

                            <div class="set-notes<?= $set_notes !== '' ? ' is-open' : '' ?>">
                                <div class="note-line<?= $set_notes === '' ? ' is-empty' : '' ?>">
                                    <div class="note-read">
                                        <span class="note-text"><?= htmlspecialchars($set_notes === '' ? 'Комментарий к подходу' : $set['notes']) ?></span><span class="note-pencil" aria-hidden="true"><?= icon_pencil() ?></span>
                                    </div>
                                    <textarea id="set-<?= $sid ?>-notes" name="set-<?= $sid ?>-notes"
                                              class="js-auto set-notes-input plain-note" data-field="notes" rows="1"
                                              placeholder="Комментарий к подходу"><?= htmlspecialchars($set['notes'] ?? '') ?></textarea>
                                    <button type="button" class="icon-btn note-clear js-clear-note" aria-label="Удалить комментарий" title="Удалить комментарий">×</button>
                                </div>
                                <span class="note-gap" aria-hidden="true"></span>
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
            showBubble(input.closest('.type-toggle'), value === 0 ? 'Подход назначен рабочим' : 'Подход назначен разогревочным');
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
            btn.classList.add('is-hidden');
            showBubble(btn, 'Комментарий');
            if (input) input.focus();
        });
    });

    document.querySelectorAll('.js-clear-note').forEach(function (btn) {
        btn.addEventListener('pointerdown', function (event) {
            event.preventDefault();
        });
        btn.addEventListener('click', function () {
            var line = btn.closest('.note-line');
            var input = line && line.querySelector('textarea');
            if (!input) return;
            input.value = '';
            if (typeof syncNoteRead === 'function') syncNoteRead(input);
            if (typeof fitNote === 'function') fitNote(input);
            var row = btn.closest('.set-row');
            if (!row) {
                saveWorkoutField(input.getAttribute('data-field'), '');
                input.blur();
                return;
            }
            scheduleSave(input);
            var notes = row.querySelector('.set-notes');
            if (notes) notes.classList.remove('is-open');
            var add = row.querySelector('.js-add-note');
            if (add) add.classList.remove('is-hidden');
        });
    });

    document.querySelectorAll('.set-notes-input').forEach(function (input) {
        input.addEventListener('blur', function (event) {
            if (input.value.trim() !== '') return;
            var next = event.relatedTarget;
            if (next && next.classList.contains('js-clear-note')) return;
            var notes = input.closest('.set-notes');
            if (notes) notes.classList.remove('is-open');
            var row = input.closest('.set-row');
            var add = row && row.querySelector('.js-add-note');
            if (add) add.classList.remove('is-hidden');
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

const weekdays = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
const weekdayEl = document.getElementById('workout-weekday');

function showWeekday(value) {
    if (!weekdayEl || !value) return;
    const date = new Date(value + 'T00:00:00');
    if (Number.isNaN(date.getTime())) return;
    weekdayEl.textContent = weekdays[date.getDay()];
}

if (weekdayEl) {
    weekdayEl.addEventListener('click', function (event) {
        const input = document.getElementById('workout-date');
        if (!input) return;
        const ios = /iP(hone|ad|od)/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (ios) return;
        if (typeof input.showPicker !== 'function') return;
        event.preventDefault();
        try {
            input.showPicker();
        } catch (e) {
            input.focus();
        }
    });
}

document.querySelectorAll('.js-workout-auto').forEach(el => {
    const handler = () => {
        const field = el.getAttribute('data-field');
        const value = el.value;
        if (field === 'workout_date') showWeekday(value);
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

function syncNoteRead(el) {
    const line = el.closest('.note-line');
    if (!line) return;
    const text = line.querySelector('.note-text');
    const empty = el.value.trim() === '';
    if (text) text.textContent = empty ? (el.getAttribute('placeholder') || '') : el.value;
    line.classList.toggle('is-empty', empty);
}

function fitNote(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

document.querySelectorAll('textarea.plain-note').forEach(function (el) {
    const line = el.closest('.note-line');
    if (line) {
        line.addEventListener('click', function (event) {
            if (event.target.closest('.note-clear')) return;
            el.focus();
        });
    }
    el.addEventListener('input', function () {
        syncNoteRead(el);
        fitNote(el);
    });
    el.addEventListener('focus', function () { fitNote(el); });
});

window.addEventListener('resize', function () {
    document.querySelectorAll('textarea.plain-note').forEach(fitNote);
});

document.querySelectorAll('.set-grip').forEach(function (grip) {
    grip.addEventListener('pointerdown', function (event) {
        if (event.button !== 0) return;
        const row = grip.closest('.set-row');
        const list = grip.closest('.sets');
        if (!row || !list) return;
        event.preventDefault();
        const startOrder = [...list.querySelectorAll('.set-row')].map(function (item) {
            return item.getAttribute('data-set-id');
        }).join(',');
        const rect = row.getBoundingClientRect();
        const offsetY = event.clientY - rect.top;
        let active = false;
        let placeholder = null;
        try { grip.setPointerCapture(event.pointerId); } catch (err) {}

        function placeGap(clientY) {
            const rows = [...list.querySelectorAll('.set-row')];
            for (let i = 0; i < rows.length; i++) {
                const box = rows[i].getBoundingClientRect();
                if (clientY < box.top + box.height / 2) {
                    rows[i].before(placeholder);
                    return;
                }
            }
            list.appendChild(placeholder);
        }

        function move(e) {
            if (e.pointerId !== event.pointerId) return;
            if (!active) {
                if (Math.abs(e.clientY - event.clientY) < 4) return;
                active = true;
                placeholder = document.createElement('div');
                placeholder.className = 'set-placeholder';
                placeholder.style.height = rect.height + 'px';
                row.before(placeholder);
                row.classList.add('is-dragging');
                row.style.width = rect.width + 'px';
                row.style.left = rect.left + 'px';
            }
            row.style.top = (e.clientY - offsetY) + 'px';
            placeGap(e.clientY);
        }

        function end(e) {
            if (e.pointerId !== event.pointerId) return;
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            if (placeholder) placeholder.replaceWith(row);
            row.classList.remove('is-dragging');
            row.style.width = '';
            row.style.left = '';
            row.style.top = '';
            if (!active) return;
            const nextOrder = [...list.querySelectorAll('.set-row')].map(function (item) {
                return item.getAttribute('data-set-id');
            }).join(',');
            if (nextOrder === startOrder) return;
            const ids = nextOrder.split(',').map(Number);
            fetch('api_reorder_sets.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    exercise_id: Number(list.getAttribute('data-exercise-id')),
                    ids: ids
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) { if (!data.ok) throw new Error('fail'); })
            .catch(function () { window.location.reload(); });
        }

        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
    });
});

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