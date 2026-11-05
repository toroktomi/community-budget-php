<?php
require_once 'config.php';

requireLogin();

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = getProjectById($projectId);
$currentUser = getCurrentUser();

if (!$project || $project['owner'] != $currentUser['id']) {
    header('Location: index.php');
    exit;
}

if ($project['status'] != STATUS_REWORK) {
    header('Location: project.php?id=' . $projectId);
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
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

            $changes = [];
            if ($project['title'] != $title) {
                $changes['Cím'] = ['old' => $project['title'], 'new' => $title];
            }
            if ($project['description'] != $description) {
                $changes['Leírás'] = ['old' => $project['description'], 'new' => $description];
            }
            if ($project['category'] != $category) {
                $oldCat = getCategoryById($project['category']);
                $newCat = getCategoryById($category);
                $changes['Kategória'] = ['old' => $oldCat['text'], 'new' => $newCat['text']];
            }
            if ($project['postal_code'] != $postalCode) {
                $changes['Irányítószám'] = ['old' => $project['postal_code'], 'new' => $postalCode];
            }
            if ($project['image'] != $image) {
                $changes['Kép URL'] = ['old' => $project['image'] ?: '(nincs)', 'new' => $image ?: '(nincs)'];
            }

            foreach ($projects as &$p) {
                if ($p['id'] == $projectId) {
                    $p['title'] = $title;
                    $p['description'] = $description;
                    $p['category'] = $category;
                    $p['postal_code'] = $postalCode;
                    $p['image'] = $image;

                    if (!isset($p['history'])) {
                        $p['history'] = [];
                    }
                    if (!empty($changes)) {
                        $p['history'][] = [
                            'timestamp' => date('Y-m-d H:i:s'),
                            'action' => 'edit',
                            'user' => $currentUser['username'],
                            'changes' => $changes
                        ];
                    }
                    break;
                }
            }

            saveJSON(PROJECTS_FILE, $projects);
            $success = 'A projekt sikeresen frissítve!';
            $project = getProjectById($projectId); // Reload
        }
    } elseif (isset($_POST['resubmit'])) {
        $projects = loadJSON(PROJECTS_FILE);

        foreach ($projects as &$p) {
            if ($p['id'] == $projectId) {
                $p['status'] = STATUS_PENDING;

                if (!isset($p['history'])) {
                    $p['history'] = [];
                }
                $p['history'][] = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'action' => 'resubmit',
                    'user' => $currentUser['username']
                ];
                break;
            }
        }

        saveJSON(PROJECTS_FILE, $projects);
        header('Location: project.php?id=' . $projectId);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projekt szerkesztése - Budapesti Közösségi Költségvetés</title>
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
                    <a href="submit-project.php">Projekt beküldése</a>
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
            <h2>Projekt szerkesztése</h2>

            <div class="alert alert-warning">
                <strong>⚠️ Ez a projekt javításra lett visszaküldve</strong>
                <p>Módosítsd a projekt adatait az admin kommentje alapján, majd küldd be újra elbírálásra.</p>
            </div>

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
                </div>
            <?php endif; ?>

            <?php if (isset($project['history']) && !empty($project['history'])): ?>
                <div class="project-history">
                    <h3>Admin kommentek</h3>
                    <?php
                    $reworkComments = array_filter($project['history'], function($h) {
                        return $h['action'] == 'rework';
                    });
                    ?>
                    <?php foreach ($reworkComments as $item): ?>
                        <div class="history-item history-rework">
                            <div class="history-header">
                                <strong><?= e(date('Y-m-d H:i', strtotime($item['timestamp']))) ?></strong>
                                - Admin: <?= e($item['admin']) ?>
                            </div>
                            <div class="history-content">
                                <p class="admin-comment"><?= nl2br(e($item['comment'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit-project.php?id=<?= $projectId ?>" class="project-form">
                <div class="form-group">
                    <label for="title">Projekt címe *</label>
                    <input type="text" id="title" name="title" required
                           value="<?= e($project['title']) ?>">
                    <small>Legalább 10 karakter</small>
                </div>

                <div class="form-group">
                    <label for="description">Projekt leírása *</label>
                    <textarea id="description" name="description" rows="8" required><?= e($project['description']) ?></textarea>
                    <small>Legalább 150 karakter</small>
                </div>

                <div class="form-group">
                    <label for="category">Kategória *</label>
                    <select id="category" name="category" required>
                        <option value="">Válassz kategóriát...</option>
                        <?php foreach (getCategories() as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                    <?= $project['category'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['text']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="postal_code">Irányítószám *</label>
                    <input type="text" id="postal_code" name="postal_code" required
                           pattern="\d{4}"
                           value="<?= e($project['postal_code']) ?>">
                    <small>Budapesti irányítószám (pl. 1011, 1139, 1007)</small>
                </div>

                <div class="form-group">
                    <label for="image">Kép URL (opcionális)</label>
                    <input type="url" id="image" name="image"
                           value="<?= e($project['image']) ?>">
                    <small>Teljes URL (pl. https://example.com/image.jpg)</small>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update" class="btn btn-primary">
                        💾 Módosítások mentése
                    </button>
                    <button type="submit" name="resubmit" class="btn btn-success"
                            onclick="return confirm('Biztosan újra beküldöd a projektet elbírálásra?')">
                        📤 Újra beküldés elbírálásra
                    </button>
                    <a href="project.php?id=<?= $projectId ?>" class="btn btn-secondary">
                        Vissza a projekthez
                    </a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
