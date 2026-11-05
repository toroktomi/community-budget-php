<?php
require_once 'config.php';

requireLogin();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = (int)($_POST['category'] ?? 0);
    $postalCode = trim($_POST['postal_code'] ?? '');
    $image = trim($_POST['image'] ?? '');

    if (strlen($title) < 10) {
        $errors[] = 'A projekt címének legalább 10 karakter hosszúnak kell lennie.';
    }

    if (strlen($description) < 150) {
        $errors[] = 'A projekt leírásának legalább 150 karakter hosszúnak kell lennie.';
    }

    $categories = getCategories();
    $validCategory = false;
    foreach ($categories as $cat) {
        if ($cat['id'] == $category) {
            $validCategory = true;
            break;
        }
    }
    if (!$validCategory) {
        $errors[] = 'Érvénytelen kategória.';
    }

    if (!preg_match('/^\d{4}$/', $postalCode)) {
        $errors[] = 'Az irányítószámnak 4 számjegyből kell állnia.';
    } elseif ($postalCode != '1007') {
        $first = (int)substr($postalCode, 0, 1);
        $district = (int)substr($postalCode, 1, 2);
        $fourth = (int)substr($postalCode, 3, 1);

        if ($first != 1) {
            $errors[] = 'Az irányítószám első számjegyének 1-nek kell lennie (Budapest).';
        }
        if ($district < 1 || $district > 23) {
            $errors[] = 'Az irányítószám 2-3. számjegye 01 és 23 között kell legyen (kerület).';
        }
        if ($fourth < 1 || $fourth > 9) {
            $errors[] = 'Az irányítószám negyedik számjegyének 1 és 9 között kell lennie.';
        }
    }

    if (!empty($image) && !filter_var($image, FILTER_VALIDATE_URL)) {
        $errors[] = 'A kép linkjének érvényes URL-nek kell lennie.';
    }

    if (empty($errors)) {
        $projects = loadJSON(PROJECTS_FILE);
        $currentUser = getCurrentUser();

        $newProject = [
            'id' => count($projects) > 0 ? max(array_column($projects, 'id')) + 1 : 1,
            'status' => STATUS_PENDING,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'postal_code' => $postalCode,
            'image' => $image,
            'owner' => $currentUser['id'],
            'submitted' => date('Y-m-d H:i:s'),
            'approved' => null,
            'history' => [
                [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'action' => 'submit',
                    'user' => $currentUser['username']
                ]
            ]
        ];

        $projects[] = $newProject;
        saveJSON(PROJECTS_FILE, $projects);

        $success = 'A projekt sikeresen beküldve! Egy admin hamarosan elbírálja.';

        $_POST = [];
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projekt beküldése - Budapesti Közösségi Költségvetés</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Budapesti Közösségi Költségvetés</h1>
            <nav>
                <a href="index.php">Főoldal</a>
                <?php if (isLoggedIn()): ?>
                    <a href="projects-own.php">Saját projektek</a>
                    <a href="submit-project.php" class="active">Projekt beküldése</a>
                    <?php if (isAdmin()): ?>
                        <a href="projects-admin.php">Admin</a>
                        <a href="statistics.php">Statisztikák</a>
                    <?php endif; ?>
                    <a href="auth.php?logout=1">Kijelentkezés</a>
                <?php else: ?>
                    <a href="auth.php">Bejelentkezés</a>
                <?php endif; ?>
            </nav>
        </header>

        <main>
            <h2>Új projekt beküldése</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= e($success) ?>
                    <a href="projects-own.php">Saját projektek megtekintése</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="submit-project.php" class="project-form">
                <div class="form-group">
                    <label for="title">Projekt címe *</label>
                    <input type="text" id="title" name="title" required
                           value="<?= e($_POST['title'] ?? '') ?>">
                    <small>Legalább 10 karakter</small>
                </div>

                <div class="form-group">
                    <label for="description">Projekt leírása *</label>
                    <textarea id="description" name="description" rows="8" required><?= e($_POST['description'] ?? '') ?></textarea>
                    <small>Legalább 150 karakter</small>
                </div>

                <div class="form-group">
                    <label for="category">Kategória *</label>
                    <select id="category" name="category" required>
                        <option value="">Válassz kategóriát...</option>
                        <?php foreach (getCategories() as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                    <?= (isset($_POST['category']) && $_POST['category'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['text']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="postal_code">Irányítószám *</label>
                    <input type="text" id="postal_code" name="postal_code" required
                           pattern="\d{4}"
                           value="<?= e($_POST['postal_code'] ?? '') ?>">
                    <small>Budapesti irányítószám (pl. 1011, 1139, 1007)</small>
                </div>

                <div class="form-group">
                    <label for="image">Kép URL (opcionális)</label>
                    <input type="url" id="image" name="image"
                           value="<?= e($_POST['image'] ?? '') ?>">
                    <small>Teljes URL (pl. https://example.com/image.jpg)</small>
                </div>

                <button type="submit" class="btn btn-primary">Projekt beküldése</button>
            </form>
        </main>
    </div>
</body>
</html>
