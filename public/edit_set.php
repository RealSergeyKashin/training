<?php
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0 && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
}

if ($id <= 0) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, e.name AS exercise_name, e.workout_id
    FROM sets s
    JOIN exercises e ON e.id = s.exercise_id
    WHERE s.id = ? AND s.deleted_at IS NULL AND e.deleted_at IS NULL
");
$stmt->execute([$id]);
$set = $stmt->fetch();

if (!$set) {
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
        UPDATE sets SET
            is_warmup = ?,
            planned_weight = ?, actual_weight = ?,
            planned_reps = ?, actual_reps = ?,
            notes = ?
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([
        $is_warmup,
        $planned_weight,
        $actual_weight,
        $planned_reps,
        $actual_reps,
        $notes !== '' ? $notes : null,
        $id,
    ]);

    header('Location: /view.php?id=' . (int)$set['workout_id']);
    exit;
} else {
    $_POST = [
        'is_warmup' => $set['is_warmup'],
        'planned_weight' => $set['planned_weight'],
        'actual_weight' => $set['actual_weight'],
        'planned_reps' => $set['planned_reps'],
        'actual_reps' => $set['actual_reps'],
        'notes' => $set['notes'] ?? '',
    ];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать подход</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; color: #222; line-height: 1.5; }
        h1 { margin-bottom: 8px; }
        .meta { color: #6b7280; margin-bottom: 24px; }
        label { display: block; font-weight: 500; margin-bottom: 6px; margin-top: 16px; }
        input[type="number"], textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 16px; box-sizing: border-box;
        }
        textarea { min-height: 70px; resize: vertical; }
        .row { display: flex; gap: 12px; }
        .row > div { flex: 1; }
        .checkbox-row { margin-top: 16px; display: flex; align-items: center; gap: 8px; }
        .checkbox-row input { width: auto; }
        .checkbox-row label { margin: 0; font-weight: 500; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; font-size: 16px; margin-top: 24px; }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #e5e7eb; color: #374151; margin-left: 12px; }
        .error { background: #fef2f2; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Редактировать подход</h1>
    <div class="meta"><?= htmlspecialchars($set['exercise_name']) ?></div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="checkbox-row">
            <input type="checkbox" id="is_warmup" name="is_warmup" value="1"
                <?= !empty($_POST['is_warmup']) ? 'checked' : '' ?>>
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

        <label for="notes">Комментарий</label>
        <textarea id="notes" name="notes"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>

        <button type="submit" class="btn">Сохранить</button>
        <a href="/view.php?id=<?= (int)$set['workout_id'] ?>" class="btn btn-secondary">Отмена</a>
    </form>
</body>
</html>