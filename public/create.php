<?php
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workout_date = $_POST['workout_date'] ?? date('Y-m-d');
    $body_weight = $_POST['body_weight'] !== '' ? $_POST['body_weight'] : null;
    $body_weight_unit = $_POST['body_weight_unit'] ?? 'kg';
    $notes = trim($_POST['notes'] ?? '');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO workouts (workout_date, body_weight, body_weight_unit, notes)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$workout_date, $body_weight, $body_weight_unit, $notes ?: null]);

        // После создания перенаправляем на главную
        header('Location: /');
        exit;
    } catch (Exception $e) {
        $error = 'Ошибка при сохранении: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новая тренировка</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
            color: #222;
            line-height: 1.5;
        }
        h1 { margin-bottom: 24px; }
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            margin-top: 16px;
        }
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        textarea { min-height: 100px; resize: vertical; }
        .row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        .row > div { flex: 1; }
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

    <h1>Новая тренировка</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="workout_date">Дата тренировки</label>
        <input type="date" id="workout_date" name="workout_date" 
               value="<?= htmlspecialchars($_POST['workout_date'] ?? date('Y-m-d')) ?>" required>

        <div class="row">
            <div>
                <label for="body_weight">Вес тела</label>
                <input type="number" id="body_weight" name="body_weight" 
                       step="0.1" min="0" placeholder="Например 82.4"
                       value="<?= htmlspecialchars($_POST['body_weight'] ?? '') ?>">
            </div>
            <div style="flex: 0 0 100px;">
                <label for="body_weight_unit">Ед.</label>
                <select id="body_weight_unit" name="body_weight_unit">
                    <option value="kg" selected>кг</option>
                    <option value="lbs">фунты</option>
                </select>
            </div>
        </div>

        <label for="notes">Комментарий</label>
        <textarea id="notes" name="notes" placeholder="Как самочувствие, что планируете..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>

        <button type="submit" class="btn">Сохранить тренировку</button>
        <a href="/" class="btn btn-secondary">Отмена</a>
    </form>

</body>
</html>