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
    SELECT * FROM exercises
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$id]);
$exercise = $stmt->fetch();

if (!$exercise) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("SELECT workout_date FROM workouts WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([(int)$exercise['workout_id']]);
$workout_date = $stmt->fetchColumn();
$workout_date_label = $workout_date ? date('d.m.Y', strtotime($workout_date)) : '';

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
    $measurement_type = $exercise['measurement_type'] ?: 'weight';

    if ($custom_name !== '') {
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
        $error = 'Укажите название упражнения.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE exercises
            SET catalog_id = ?, name = ?, measurement_type = ?, notes = ?
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([
            $catalog_id,
            $name,
            $measurement_type,
            $notes !== '' ? $notes : null,
            $id,
        ]);
        header('Location: /view.php?id=' . (int)$exercise['workout_id']);
        exit;
    }
} else {
    $is_custom = empty($exercise['catalog_id']);
    $_POST = [
        'catalog_id' => $exercise['catalog_id'],
        'custom_name' => $is_custom ? $exercise['name'] : '',
        'notes' => $exercise['notes'] ?? '',
    ];
}

$posted_custom = $_POST['custom_name'] ?? '';
$posted_catalog = isset($_POST['catalog_id']) && $_POST['catalog_id'] !== '' ? (int)$_POST['catalog_id'] : null;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать упражнение</title>
    <link rel="stylesheet" href="/assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>">
</head>
<body>
    <div class="form-page">
        <nav class="crumbs" aria-label="Навигация">
            <a href="/" class="back-link">Все тренировки</a>
            <a href="/view.php?id=<?= (int)$exercise['workout_id'] ?>" class="back-link">Тренировка <?= htmlspecialchars($workout_date_label) ?></a>
        </nav>
        <h1>Редактировать упражнение</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="id" value="<?= $id ?>">

            <label for="catalog_id">Упражнение из списка</label>
            <select id="catalog_id" name="catalog_id">
                <?php foreach ($catalog as $index => $item): ?>
                    <option value="<?= (int)$item['id'] ?>"
                        <?= ($posted_catalog === (int)$item['id'] || ($posted_catalog === null && $posted_custom === '' && $index === 0)) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($item['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="custom_name">Или своё название</label>
            <div class="input-wrap">
                <input type="text" id="custom_name" name="custom_name"
                    value="<?= htmlspecialchars($posted_custom) ?>" autocomplete="off">
                <button type="button" class="clear-btn" id="clear_custom">×</button>
            </div>
            <div class="hint">Если заполнено — используется оно, а не пункт из списка</div>

            <label for="notes">Комментарий</label>
            <textarea id="notes" name="notes"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            
            <div class="form-actions">
                <button type="submit" class="btn">Сохранить</button>
                <a href="/view.php?id=<?= (int)$exercise['workout_id'] ?>" class="btn btn-secondary">Отменить</a>
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