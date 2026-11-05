<?php
require_once 'config.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = getProjectById($projectId);

if (!$project) {
    header('Location: index.php');
    exit;
}

if ($project['status'] != STATUS_APPROVED) {
    $currentUser = getCurrentUser();
    if (!$currentUser ||
        ($currentUser['id'] != $project['owner'] && !$currentUser['is_admin'])) {
        header('Location: index.php');
        exit;
    }
}

$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    if (isset($_POST['approve'])) {
        $projects = loadJSON(PROJECTS_FILE);
        $currentUser = getCurrentUser();
        foreach ($projects as &$p) {
            if ($p['id'] == $projectId) {
                $oldStatus = $p['status'];
                $p['status'] = STATUS_APPROVED;
                $p['approved'] = date('Y-m-d H:i:s');

                if (!isset($p['history'])) {
                    $p['history'] = [];
                }
                $p['history'][] = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'action' => 'approved',
                    'admin' => $currentUser['username'],
                    'old_status' => $oldStatus,
                    'new_status' => STATUS_APPROVED
                ];
                break;
            }
        }
        saveJSON(PROJECTS_FILE, $projects);
        $message = 'A projekt jóváhagyva!';
        $project = getProjectById($projectId); // Reload
    } elseif (isset($_POST['reject'])) {
        $projects = loadJSON(PROJECTS_FILE);
        $currentUser = getCurrentUser();
        foreach ($projects as &$p) {
            if ($p['id'] == $projectId) {
                $oldStatus = $p['status'];
                $p['status'] = STATUS_REJECTED;

                if (!isset($p['history'])) {
                    $p['history'] = [];
                }
                $p['history'][] = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'action' => 'rejected',
                    'admin' => $currentUser['username'],
                    'old_status' => $oldStatus,
                    'new_status' => STATUS_REJECTED
                ];
                break;
            }
        }
        saveJSON(PROJECTS_FILE, $projects);
        $message = 'A projekt visszautasítva.';
        $project = getProjectById($projectId);
    } elseif (isset($_POST['rework'])) {
        $comment = trim($_POST['admin_comment'] ?? '');

        if (empty($comment)) {
            $errors[] = 'A komment megadása kötelező javításra küldéskor.';
        } else {
            $projects = loadJSON(PROJECTS_FILE);
            $currentUser = getCurrentUser();
            foreach ($projects as &$p) {
                if ($p['id'] == $projectId) {
                    $oldStatus = $p['status'];
                    $p['status'] = STATUS_REWORK;

                    if (!isset($p['history'])) {
                        $p['history'] = [];
                    }
                    $p['history'][] = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'action' => 'rework',
                        'admin' => $currentUser['username'],
                        'comment' => $comment,
                        'old_status' => $oldStatus,
                        'new_status' => STATUS_REWORK
                    ];
                    break;
                }
            }
            saveJSON(PROJECTS_FILE, $projects);
            $message = 'A projekt javításra visszaküldve.';
            $project = getProjectById($projectId);
        }
    }
}

$owner = getUserById($project['owner']);
$category = getCategoryById($project['category']);
$voteCount = getProjectVotes($project['id']);
$votingOpen = isVotingOpen($project);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($project['title']) ?> - Budapesti Közösségi Költségvetés</title>
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
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <div class="project-detail">
                <div class="project-status-badge status-<?= getStatusText($project['status']) ?>">
                    <?php
                    $statusTexts = [
                        STATUS_PENDING => 'Elbírálásra vár',
                        STATUS_APPROVED => 'Jóváhagyva',
                        STATUS_REJECTED => 'Visszautasítva',
                        STATUS_REWORK => 'Javításra visszaküldve'
                    ];
                    echo e($statusTexts[$project['status']] ?? 'Ismeretlen');
                    ?>
                </div>

                <h2><?= e($project['title']) ?></h2>

                <?php if ($project['image']): ?>
                    <div class="project-image">
                        <img src="<?= e($project['image']) ?>" alt="<?= e($project['title']) ?>">
                    </div>
                <?php endif; ?>

                <div class="project-meta">
                    <div class="meta-item">
                        <strong>Kategória:</strong>
                        <?= e($category['text']) ?>
                    </div>
                    <div class="meta-item">
                        <strong>Irányítószám:</strong>
                        <?= e($project['postal_code']) ?>
                    </div>
                    <div class="meta-item">
                        <strong>Beküldő:</strong>
                        <?= e($owner['username']) ?>
                    </div>
                    <div class="meta-item">
                        <strong>Beküldés dátuma:</strong>
                        <?= e(date('Y-m-d H:i', strtotime($project['submitted']))) ?>
                    </div>
                    <?php if ($project['approved']): ?>
                        <div class="meta-item">
                            <strong>Jóváhagyás dátuma:</strong>
                            <?= e(date('Y-m-d H:i', strtotime($project['approved']))) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($project['status'] == STATUS_APPROVED): ?>
                        <div class="meta-item">
                            <strong>Szavazatok száma:</strong>
                            <?= $voteCount ?>
                        </div>
                        <div class="meta-item">
                            <strong>Szavazás állapota:</strong>
                            <?php if ($votingOpen): ?>
                                <span class="voting-open">Nyitva</span>
                            <?php else: ?>
                                <span class="voting-closed">Lezárva</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="project-description">
                    <h3>Leírás</h3>
                    <p><?= nl2br(e($project['description'])) ?></p>
                </div>

                <?php if (isset($project['history']) && !empty($project['history'])): ?>
                    <div class="project-history">
                        <h3>Projekt történet</h3>
                        <?php foreach ($project['history'] as $historyItem): ?>
                            <div class="history-item history-<?= e($historyItem['action']) ?>">
                                <div class="history-header">
                                    <strong><?= e(date('Y-m-d H:i', strtotime($historyItem['timestamp']))) ?></strong>
                                    <?php if (isset($historyItem['admin'])): ?>
                                        - Admin: <?= e($historyItem['admin']) ?>
                                    <?php elseif (isset($historyItem['user'])): ?>
                                        - Felhasználó: <?= e($historyItem['user']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="history-content">
                                    <?php if ($historyItem['action'] == 'submit'): ?>
                                        <p> <strong>Projekt beküldve elbírálásra</strong></p>
                                    <?php elseif ($historyItem['action'] == 'approved'): ?>
                                        <p> <strong>Projekt jóváhagyva</strong></p>
                                    <?php elseif ($historyItem['action'] == 'rejected'): ?>
                                        <p> <strong>Projekt elutasítva</strong></p>
                                    <?php elseif ($historyItem['action'] == 'rework'): ?>
                                        <p> <strong>Projekt javításra visszaküldve</strong></p>
                                        <?php if (isset($historyItem['comment'])): ?>
                                            <p class="admin-comment"><em>Admin komment: <?= nl2br(e($historyItem['comment'])) ?></em></p>
                                        <?php endif; ?>
                                    <?php elseif ($historyItem['action'] == 'resubmit'): ?>
                                        <p> <strong>Projekt újra beküldve elbírálásra</strong></p>
                                    <?php elseif ($historyItem['action'] == 'edit'): ?>
                                        <p> <strong>Projekt módosítva</strong></p>
                                        <?php if (isset($historyItem['changes'])): ?>
                                            <ul class="changes-list">
                                                <?php foreach ($historyItem['changes'] as $field => $change): ?>
                                                    <li>
                                                        <strong><?= e($field) ?>:</strong><br>
                                                        Régi: <code><?= e(mb_substr($change['old'], 0, 100)) ?><?= mb_strlen($change['old']) > 100 ? '...' : '' ?></code><br>
                                                        Új: <code><?= e(mb_substr($change['new'], 0, 100)) ?><?= mb_strlen($change['new']) > 100 ? '...' : '' ?></code>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php
                $currentUser = getCurrentUser();
                $isOwner = $currentUser && $currentUser['id'] == $project['owner'];
                ?>

                <?php if ($isOwner && $project['status'] == STATUS_REWORK): ?>
                    <div class="alert alert-warning">
                        <h3> A projekt javításra lett visszaküldve</h3>
                        <p>Kérjük, javítsd a projektet az admin kommentje alapján, majd küldd be újra elbírálásra.</p>
                        <a href="edit-project.php?id=<?= $project['id'] ?>" class="btn btn-primary">
                            Projekt szerkesztése
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (isAdmin() && $project['status'] == STATUS_PENDING): ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="admin-actions">
                        <h3>Admin műveletek</h3>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="approve" class="btn btn-success">
                                 Jóváhagyás
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="reject" class="btn btn-danger">
                                 Visszautasítás
                            </button>
                        </form>

                        <div class="rework-section">
                            <h4>Vagy javításra visszaküldés</h4>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="admin_comment">Admin komment a javításhoz:</label>
                                    <textarea id="admin_comment" name="admin_comment" rows="4" required></textarea>
                                    <small>Írd le, mit kell javítania a felhasználónak</small>
                                </div>
                                <button type="submit" name="rework" class="btn btn-warning">
                                     Javításra visszaküldés
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
