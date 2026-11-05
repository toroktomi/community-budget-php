<?php
require_once 'config.php';

requireAdmin();

$projects = loadJSON(PROJECTS_FILE);
$categories = getCategories();

$pendingProjects = array_filter($projects, function($p) {
    return $p['status'] == STATUS_PENDING;
});

$projectsByCategory = [];
foreach ($categories as $cat) {
    $projectsByCategory[$cat['id']] = [];
}

foreach ($pendingProjects as $project) {
    if (isset($projectsByCategory[$project['category']])) {
        $projectsByCategory[$project['category']][] = $project;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Budapesti Közösségi Költségvetés</title>
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
                        <a href="projects-admin.php" class="active">Admin</a>
                        <a href="statistics.php">Statisztikák</a>
                    <?php endif; ?>
                    <a href="auth.php?logout=1">Kijelentkezés</a>
                <?php else: ?>
                    <a href="auth.php">Bejelentkezés</a>
                <?php endif; ?>
            </nav>
        </header>

        <main>
            <h2>Elbírálásra váró projektek</h2>

            <?php if (empty($pendingProjects)): ?>
                <p class="no-projects">Jelenleg nincsenek elbírálásra váró projektek.</p>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryProjects = $projectsByCategory[$category['id']];
                    if (empty($categoryProjects)) {
                        continue;
                    }
                    ?>

                    <div class="category-section">
                        <h3><?= e($category['text']) ?></h3>

                        <div class="projects-list">
                            <?php foreach ($categoryProjects as $project): ?>
                                <?php
                                $owner = getUserById($project['owner']);
                                ?>
                                <div class="project-card">
                                    <h4>
                                        <a href="project.php?id=<?= $project['id'] ?>">
                                            <?= e($project['title']) ?>
                                        </a>
                                    </h4>

                                    <div class="project-info">
                                        <div>
                                            <strong>Beküldő:</strong> <?= e($owner['username']) ?>
                                        </div>
                                        <div>
                                            <strong>Beküldve:</strong>
                                            <?= e(date('Y-m-d H:i', strtotime($project['submitted']))) ?>
                                        </div>
                                        <div>
                                            <strong>Irányítószám:</strong> <?= e($project['postal_code']) ?>
                                        </div>
                                    </div>

                                    <div class="project-description-preview">
                                        <?= e(mb_substr($project['description'], 0, 200)) ?>...
                                    </div>

                                    <a href="project.php?id=<?= $project['id'] ?>" class="btn btn-small btn-primary">
                                        Részletek és elbírálás
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
