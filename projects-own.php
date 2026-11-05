<?php
require_once 'config.php';

requireLogin();

$currentUser = getCurrentUser();
$projects = loadJSON(PROJECTS_FILE);

$userProjects = array_filter($projects, function($p) use ($currentUser) {
    return $p['owner'] == $currentUser['id'];
});

usort($userProjects, function($a, $b) {
    return strtotime($b['submitted']) - strtotime($a['submitted']);
});
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saját projektek - Budapesti Közösségi Költségvetés</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Budapesti Közösségi Költségvetés</h1>
            <nav>
                <a href="index.php">Főoldal</a>
                <?php if (isLoggedIn()): ?>
                    <a href="projects-own.php" class="active">Saját projektek</a>
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
            <h2>Saját projektjeim</h2>

            <?php if (empty($userProjects)): ?>
                <p class="no-projects">
                    Még nem küldtél be egyetlen projektet sem.
                    <a href="submit-project.php">Küldj be egyet most!</a>
                </p>
            <?php else: ?>
                <div class="projects-list">
                    <?php foreach ($userProjects as $project): ?>
                        <?php
                        $category = getCategoryById($project['category']);
                        $statusText = getStatusText($project['status']);
                        $voteCount = getProjectVotes($project['id']);
                        ?>
                        <div class="project-card <?= $project['status'] == STATUS_REWORK ? 'needs-attention' : '' ?>">
                            <div class="project-status-badge status-<?= $statusText ?>">
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

                            <h3>
                                <a href="project.php?id=<?= $project['id'] ?>">
                                    <?= e($project['title']) ?>
                                </a>
                            </h3>

                            <div class="project-info">
                                <div>
                                    <strong>Kategória:</strong> <?= e($category['text']) ?>
                                </div>
                                <div>
                                    <strong>Beküldve:</strong>
                                    <?= e(date('Y-m-d H:i', strtotime($project['submitted']))) ?>
                                </div>
                                <?php if ($project['status'] == STATUS_APPROVED): ?>
                                    <div>
                                        <strong>Szavazatok:</strong> <?= $voteCount ?>
                                    </div>
                                    <div>
                                        <strong>Jóváhagyva:</strong>
                                        <?= e(date('Y-m-d H:i', strtotime($project['approved']))) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($project['status'] == STATUS_REWORK): ?>
                                <div class="alert alert-warning">
                                    <strong>Figyelem!</strong> Ez a projekt javításra lett visszaküldve.
                                    Kérjük, javítsd és küldd be újra!
                                </div>
                            <?php endif; ?>

                            <a href="project.php?id=<?= $project['id'] ?>" class="btn btn-small btn-primary">
                                Részletek megtekintése
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
