<?php
require_once __DIR__ . '/../config/database.php';

$workout_id = isset($_GET['workout_id']) ? (int)$_GET['workout_id'] : 0;
if ($workout_id <= 0 && isset($_POST['workout_id'])) {
    $workout_id = (int)$_POST['workout_id'];
}

if ($workout_id <= 0) {
    header('Location: /');
    exit;
}

// Проверяем, что тренировка существует
$stmt = $pdo->prepare("
    SELECT id, workout_date FROM workouts
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$workout_id]);
$workout = $stmt->fetch();

if (!$workout) {
    header('Location: /');
    exit;
}

// Справочник упражнений
$catalog = $pdo->query("
    SELECT id, name, measurement_type
    FROM exercise_catalog
    WHERE deleted_at IS NULL
    ORDER BY name
")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catalog_id = $_POST['catalog_id'] !== '' ? (int)$_POST['catalog_id'] : null;
    $custom_name = trim($_POST['custom_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Имя: из справочника или своё
    $name = '';
    $measurement_type = 'weight';

    if ($catalog_id) {
        foreach ($catalog as $item) {
            if ((int)$item['id'] === $catalog_id) {
                $name = $item['name'];
                $measurement_type = $item['measurement_type'];
                break;
            }
        }
    }

    if ($custom_name !== '') {
        $name = $custom_name;
        $catalog_id = null; // своё название — без привязки к справочнику
    }

    if ($name === '') {
        $error = 'Выберите упражнение из списка или введите своё название.';
    } else {
        // Следующий sort_order
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order
            FROM exercises
            WHERE workout_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$workout_id]);
        $next_order = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO exercises (workout_id, catalog_id, name, measurement_type, sort_order, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $workout_id,
            $catalog_id,
            $name,
            $measurement_type,
            $next_order,
            $notes !== '' ? $notes : null,
        ]);

        header('Location: /view.php?id=' . $workout_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить упражнение</title>
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
        select, input[type="text"], textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        textarea { min-height: 80px; resize: vertical; }
        .hint { font-size: 13px; color: #6b7280; margin-top: 4px; }
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

    <h1>Добавить упражнение</h1>
    <div class="meta">
        Тренировка <?= htmlspecialchars(date('d.m.Y', strtotime($workout['workout_date']))) ?>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="workout_id" value="<?= $workout_id ?>">

        <label for="catalog_id">Упражнение из списка</label>
        <select id="catalog_id" name="catalog_id">
            <option value="">— не выбрано —</option>
            <?php foreach ($catalog as $item): ?>
                <option value="<?= (int)$item['id'] ?>"
                    <?= (isset($_POST['catalog_id']) && (int)$_POST['catalog_id'] === (int)$item['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($item['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="custom_name">Или своё название</label>
        <input type="text" id="custom_name" name="custom_name"
               placeholder="Например: Отжимания на брусьях"
               value="<?= htmlspecialchars($_POST['custom_name'] ?? '') ?>">
        <div class="hint">Если заполнено — используется оно, а не пункт из списка</div>

        <label for="notes">Комментарий к упражнению</label>
        <textarea id="notes" name="notes" placeholder="Опционально"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>

        <button type="submit" class="btn">Сохранить упражнение</button>
        <a href="/view.php?id=<?= $workout_id ?>" class="btn btn-secondary">Отмена</a>
    </form>

</body>
</html>