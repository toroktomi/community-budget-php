<?php
require_once 'config.php';

requireAdmin();

$projects = loadJSON(PROJECTS_FILE);
$categories = getCategories();
$statuses = loadJSON(STATUSES_FILE);

$projectVotes = [];
foreach ($projects as $project) {
    if ($project['status'] == STATUS_APPROVED) {
        $projectVotes[$project['id']] = [
            'project' => $project,
            'votes' => getProjectVotes($project['id'])
        ];
    }
}

usort($projectVotes, function($a, $b) {
    return $b['votes'] - $a['votes'];
});

$topProject = !empty($projectVotes) ? $projectVotes[0] : null;

$topByCategory = [];
foreach ($categories as $category) {
    $categoryProjects = array_filter($projects, function($p) use ($category) {
        return $p['category'] == $category['id'] && $p['status'] == STATUS_APPROVED;
    });

    $categoryVotes = [];
    foreach ($categoryProjects as $project) {
        $categoryVotes[] = [
            'project' => $project,
            'votes' => getProjectVotes($project['id'])
        ];
    }

    usort($categoryVotes, function($a, $b) {
        return $b['votes'] - $a['votes'];
    });

    $topByCategory[$category['id']] = array_slice($categoryVotes, 0, 3);
}

$projectCounts = [];
foreach ($categories as $category) {
    $projectCounts[$category['id']] = [];
    foreach ($statuses as $status) {
        $count = count(array_filter($projects, function($p) use ($category, $status) {
            return $p['category'] == $category['id'] && $p['status'] == $status['id'];
        }));
        $projectCounts[$category['id']][$status['id']] = $count;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statisztikák - Budapesti Közösségi Költségvetés</title>
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
                        <a href="statistics.php" class="active">Statisztikák</a>
                    <?php endif; ?>
                    <a href="auth.php?logout=1">Kijelentkezés</a>
                <?php else: ?>
                    <a href="auth.php">Bejelentkezés</a>
                <?php endif; ?>
            </nav>
        </header>

        <main>
            <h2>Statisztikák</h2>

            <div class="stats-section">
                <h3>Legtöbb szavazatot kapó projekt összesen</h3>
                <?php if ($topProject): ?>
                    <div class="top-project-card">
                        <h4>
                            <a href="project.php?id=<?= $topProject['project']['id'] ?>">
                                <?= e($topProject['project']['title']) ?>
                            </a>
                        </h4>
                        <p class="vote-count-large"><?= $topProject['votes'] ?> szavazat</p>
                        <p>
                            <strong>Kategória:</strong>
                            <?= e(getCategoryById($topProject['project']['category'])['text']) ?>
                        </p>
                    </div>
                <?php else: ?>
                    <p class="no-data">Még nincsenek jóváhagyott projektek.</p>
                <?php endif; ?>
            </div>

            <div class="stats-section">
                <h3>Top 3 projektek kategóriánként</h3>
                <?php foreach ($categories as $category): ?>
                    <div class="category-stats">
                        <h4><?= e($category['text']) ?></h4>
                        <?php if (!empty($topByCategory[$category['id']])): ?>
                            <ol class="top-projects-list">
                                <?php foreach ($topByCategory[$category['id']] as $item): ?>
                                    <li>
                                        <a href="project.php?id=<?= $item['project']['id'] ?>">
                                            <?= e($item['project']['title']) ?>
                                        </a>
                                        <span class="votes">(<?= $item['votes'] ?> szavazat)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?>
                            <p class="no-data">Még nincsenek jóváhagyott projektek ebben a kategóriában.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="stats-section">
                <h3>Projektek száma kategóriánként és státuszonként</h3>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Kategória</th>
                            <?php foreach ($statuses as $status): ?>
                                <th><?= e(ucfirst($status['text'])) ?></th>
                            <?php endforeach; ?>
                            <th>Összesen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= e($category['text']) ?></td>
                                <?php
                                $total = 0;
                                foreach ($statuses as $status):
                                    $count = $projectCounts[$category['id']][$status['id']];
                                    $total += $count;
                                ?>
                                    <td><?= $count ?></td>
                                <?php endforeach; ?>
                                <td><strong><?= $total ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td><strong>Összesen</strong></td>
                            <?php
                            $grandTotal = 0;
                            foreach ($statuses as $status):
                                $statusTotal = 0;
                                foreach ($categories as $category) {
                                    $statusTotal += $projectCounts[$category['id']][$status['id']];
                                }
                                $grandTotal += $statusTotal;
                            ?>
                                <td><strong><?= $statusTotal ?></strong></td>
                            <?php endforeach; ?>
                            <td><strong><?= $grandTotal ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
