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

    $name = '';
    $measurement_type = 'weight';

    if ($custom_name !== '') {
        // Своё название имеет приоритет
        $name = $custom_name;
        $catalog_id = null;
    } elseif ($catalog_id) {
        foreach ($catalog as $item) {
            if ((int)$item['id'] === $catalog_id) {
                $name = $item['name'];
                $measurement_type = $item['measurement_type'];
                break;
            }
        }
    }

    if ($name === '') {
        $error = 'Выберите упражнение из списка или введите своё название.';
    } else {
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

$posted_custom = $_POST['custom_name'] ?? '';
$posted_catalog = isset($_POST['catalog_id']) ? (int)$_POST['catalog_id'] : null;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить упражнение</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>

<div class="form-page">

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
            <?php foreach ($catalog as $index => $item): ?>
                <option value="<?= (int)$item['id'] ?>"
                    <?= ($posted_catalog === (int)$item['id'] || ($posted_catalog === null && $index === 0 && $posted_custom === '')) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($item['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="custom_name">Или своё название</label>
        <div class="input-wrap">
            <input type="text" id="custom_name" name="custom_name"
                   placeholder="Например: Отжимания на брусьях"
                   value="<?= htmlspecialchars($posted_custom) ?>"
                   autocomplete="off">
            <button type="button" class="clear-btn" id="clear_custom" aria-label="Очистить">×</button>
        </div>
        <div class="hint">Если заполнено — используется оно, а не пункт из списка</div>

        <label for="notes">Комментарий к упражнению</label>
        <textarea id="notes" name="notes" placeholder="Опционально"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>

        <div class="form-actions">
            <button type="submit" class="btn">Сохранить упражнение</button>
            <a href="/view.php?id=<?= $workout_id ?>" class="btn btn-secondary">Отменить</a>
        </div>
    </form>

    <script>
    (function () {
        const catalog = document.getElementById('catalog_id');
        const custom = document.getElementById('custom_name');
        const clearBtn = document.getElementById('clear_custom');

        function sync() {
            const hasCustom = custom.value.trim() !== '';
            catalog.disabled = hasCustom;
            clearBtn.classList.toggle('visible', hasCustom);
        }

        custom.addEventListener('input', sync);
        clearBtn.addEventListener('click', function () {
            custom.value = '';
            custom.focus();
            sync();
        });

        sync();
    })();
    </script>
</div>
</body>
</html>