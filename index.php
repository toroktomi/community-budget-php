<?php
require_once 'config.php';

$filterCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$projects = loadJSON(PROJECTS_FILE);
$categories = getCategories();
$currentUser = getCurrentUser();

$approvedProjects = array_filter($projects, function($p) {
    return $p['status'] == STATUS_APPROVED;
});

$projectsByCategory = [];
foreach ($categories as $cat) {
    $projectsByCategory[$cat['id']] = [];
}

foreach ($approvedProjects as $project) {
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
    <title>Budapesti Közösségi Költségvetés</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Budapesti Közösségi Költségvetés</h1>
            <nav>
                <a href="index.php" class="active">Főoldal</a>
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
            <h2>Projektek</h2>

            <div class="filter-section">
                <label for="category-filter">Kategória szűrő:</label>
                <select id="category-filter" onchange="window.location.href='index.php?category=' + this.value">
                    <option value="0" <?= $filterCategory == 0 ? 'selected' : '' ?>>Összes projekt</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['text']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php foreach ($categories as $category): ?>
                <?php
                if ($filterCategory != 0 && $filterCategory != $category['id']) {
                    continue;
                }

                $categoryProjects = $projectsByCategory[$category['id']];
                if (empty($categoryProjects)) {
                    continue;
                }
                ?>

                <div class="category-section">
                    <h3><?= e($category['text']) ?></h3>

                    <?php if (isLoggedIn()): ?>
                        <p class="votes-remaining">
                            Még leadható szavazatok ebben a kategóriában:
                            <strong><?= 3 - getUserVotesInCategory($currentUser['id'], $category['id']) ?></strong>
                        </p>
                    <?php endif; ?>

                    <div class="projects-list">
                        <?php foreach ($categoryProjects as $project): ?>
                            <?php
                            $voteCount = getProjectVotes($project['id']);
                            $hasVoted = isLoggedIn() && hasUserVoted($currentUser['id'], $project['id']);
                            $votingOpen = isVotingOpen($project);
                            $canVote = isLoggedIn() && $votingOpen && !$hasVoted && getUserVotesInCategory($currentUser['id'], $category['id']) < 3;
                            ?>
                            <div class="project-card">
                                <div class="project-header">
                                    <h4>
                                        <a href="project.php?id=<?= $project['id'] ?>">
                                            <?= e($project['title']) ?>
                                        </a>
                                    </h4>
                                    <div class="vote-info">
                                        <span class="vote-count"><?= $voteCount ?> szavazat</span>
                                        <?php if (!$votingOpen): ?>
                                            <span class="voting-closed">Lezárva</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (isLoggedIn()): ?>
                                    <div class="vote-actions">
                                        <?php if ($hasVoted): ?>
                                            <?php if ($votingOpen): ?>
                                                <button class="btn btn-small btn-warning vote-btn"
                                                        data-project-id="<?= $project['id'] ?>"
                                                        data-action="unvote">
                                                    Szavazat visszavonása
                                                </button>
                                            <?php else: ?>
                                                <span class="voted-indicator">✓ Szavaztál rá</span>
                                            <?php endif; ?>
                                        <?php elseif ($canVote): ?>
                                            <button class="btn btn-small btn-success vote-btn"
                                                    data-project-id="<?= $project['id'] ?>"
                                                    data-action="vote">
                                                Szavazok rá
                                            </button>
                                        <?php elseif (!$votingOpen): ?>
                                            <span class="info-text">A szavazás lezárult</span>
                                        <?php else: ?>
                                            <span class="info-text">Nincs több szavazatod ebben a kategóriában</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($approvedProjects)): ?>
                <p class="no-projects">Még nincsenek jóváhagyott projektek.</p>
            <?php endif; ?>
        </main>
    </div>

    <script src="js/voting.js"></script>
</body>
</html>
