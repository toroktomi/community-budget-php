<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('USERS_FILE', __DIR__ . '/data/users.json');
define('PROJECTS_FILE', __DIR__ . '/data/projects.json');
define('VOTES_FILE', __DIR__ . '/data/votes.json');
define('CATEGORIES_FILE', __DIR__ . '/data/categories.json');
define('STATUSES_FILE', __DIR__ . '/data/statuses.json');

define('STATUS_PENDING', 1);
define('STATUS_APPROVED', 2);
define('STATUS_REJECTED', 3);
define('STATUS_REWORK', 4);

function loadJSON($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function saveJSON($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $users = loadJSON(USERS_FILE);
    foreach ($users as $user) {
        if ($user['id'] == $_SESSION['user_id']) {
            return $user;
        }
    }
    return null;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['is_admin'];
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

function getCategories() {
    return loadJSON(CATEGORIES_FILE);
}

function getCategoryById($id) {
    $categories = getCategories();
    foreach ($categories as $cat) {
        if ($cat['id'] == $id) {
            return $cat;
        }
    }
    return null;
}

function getStatusText($statusId) {
    $statuses = loadJSON(STATUSES_FILE);
    foreach ($statuses as $status) {
        if ($status['id'] == $statusId) {
            return $status['text'];
        }
    }
    return 'unknown';
}

function getProjectById($id) {
    $projects = loadJSON(PROJECTS_FILE);
    foreach ($projects as $project) {
        if ($project['id'] == $id) {
            return $project;
        }
    }
    return null;
}

function getUserById($id) {
    $users = loadJSON(USERS_FILE);
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

function isVotingOpen($project) {
    if (!$project || $project['status'] != STATUS_APPROVED || !$project['approved']) {
        return false;
    }
    $approvedDate = strtotime($project['approved']);
    $twoWeeksLater = strtotime('+2 weeks', $approvedDate);
    return time() <= $twoWeeksLater;
}

function getProjectVotes($projectId) {
    $votes = loadJSON(VOTES_FILE);
    $count = 0;
    foreach ($votes as $vote) {
        if ($vote['project'] == $projectId) {
            $count++;
        }
    }
    return $count;
}

function hasUserVoted($userId, $projectId) {
    $votes = loadJSON(VOTES_FILE);
    foreach ($votes as $vote) {
        if ($vote['user'] == $userId && $vote['project'] == $projectId) {
            return true;
        }
    }
    return false;
}

function getUserVotesInCategory($userId, $categoryId) {
    $votes = loadJSON(VOTES_FILE);
    $projects = loadJSON(PROJECTS_FILE);
    $count = 0;

    foreach ($votes as $vote) {
        if ($vote['user'] == $userId) {
            foreach ($projects as $project) {
                if ($project['id'] == $vote['project'] && $project['category'] == $categoryId) {
                    $count++;
                    break;
                }
            }
        }
    }
    return $count;
}

function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
