<?php
require_once __DIR__ . '/../config/database.php';

$exercise_id = isset($_GET['exercise_id']) ? (int)$_GET['exercise_id'] : 0;
if ($exercise_id <= 0 && isset($_POST['exercise_id'])) {
    $exercise_id = (int)$_POST['exercise_id'];
}

if ($exercise_id <= 0) {
    header('Location: /');
    exit;
}

// Упражнение + тренировка
$stmt = $pdo->prepare("
    SELECT e.id, e.name, e.workout_id, w.workout_date
    FROM exercises e
    JOIN workouts w ON w.id = e.workout_id
    WHERE e.id = ? AND e.deleted_at IS NULL AND w.deleted_at IS NULL
");
$stmt->execute([$exercise_id]);
$exercise = $stmt->fetch();

if (!$exercise) {
    header('Location: /');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_warmup = isset($_POST['is_warmup']) ? 1 : 0;
    $planned_weight = $_POST['planned_weight'] !== '' ? $_POST['planned_weight'] : null;
    $actual_weight  = $_POST['actual_weight'] !== '' ? $_POST['actual_weight'] : null;
    $planned_reps   = $_POST['planned_reps'] !== '' ? (int)$_POST['planned_reps'] : null;
    $actual_reps    = $_POST['actual_reps'] !== '' ? (int)$_POST['actual_reps'] : null;
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(sort_order), 0) + 1
        FROM sets
        WHERE exercise_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$exercise_id]);
    $next_order = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO sets (
            exercise_id, sort_order, is_warmup,
            planned_weight, actual_weight,
            planned_reps, actual_reps, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $exercise_id,
        $next_order,
        $is_warmup,
        $planned_weight,
        $actual_weight,
        $planned_reps,
        $actual_reps,
        $notes !== '' ? $notes : null,
    ]);

    header('Location: /view.php?id=' . (int)$exercise['workout_id']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить подход</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
            color: #222;
            line-height: 1.5;
        }
        h1 { margin-bottom: 8px; }
        .meta { color: #6b7280; margin-bottom: 24px; }
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            margin-top: 16px;
        }
        input[type="number"], textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        textarea { min-height: 70px; resize: vertical; }
        .row {
            display: flex;
            gap: 12px;
        }
        .row > div { flex: 1; }
        .checkbox-row {
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-row input { width: auto; }
        .checkbox-row label { margin: 0; font-weight: 500; }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-top: 24px;
        }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
            margin-left: 12px;
        }
        .btn-secondary:hover { background: #d1d5db; }
        .error {
            background: #fef2f2;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <h1>Добавить подход</h1>
    <div class="meta">
        <?= htmlspecialchars($exercise['name']) ?>
        · тренировка <?= htmlspecialchars(date('d.m.Y', strtotime($exercise['workout_date']))) ?>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="exercise_id" value="<?= $exercise_id ?>">

        <div class="checkbox-row">
            <input type="checkbox" id="is_warmup" name="is_warmup" value="1">
            <label for="is_warmup">Разминочный подход</label>
        </div>

        <div class="row">
            <div>
                <label for="planned_weight">План, кг</label>
                <input type="number" id="planned_weight" name="planned_weight" step="0.5" min="0"
                       value="<?= htmlspecialchars($_POST['planned_weight'] ?? '') ?>">
            </div>
            <div>
                <label for="actual_weight">Факт, кг</label>
                <input type="number" id="actual_weight" name="actual_weight" step="0.5" min="0"
                       value="<?= htmlspecialchars($_POST['actual_weight'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label for="planned_reps">План, повторы</label>
                <input type="number" id="planned_reps" name="planned_reps" step="1" min="0"
                       value="<?= htmlspecialchars($_POST['planned_reps'] ?? '') ?>">
            </div>
            <div>
                <label for="actual_reps">Факт, повторы</label>
                <input type="number" id="actual_reps" name="actual_reps" step="1" min="0"
                       value="<?= htmlspecialchars($_POST['actual_reps'] ?? '') ?>">
            </div>
        </div>

        <label for="notes">Комментарий к подходу</label>
        <textarea id="notes" name="notes" placeholder="Опционально"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>

        <button type="submit" class="btn">Сохранить подход</button>
        <a href="/view.php?id=<?= (int)$exercise['workout_id'] ?>" class="btn btn-secondary">Отмена</a>
    </form>

</body>
</html>