<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nem vagy bejelentkezve.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$projectId = (int)($input['project_id'] ?? 0);

if (!$projectId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Érvénytelen projekt.']);
    exit;
}

$project = getProjectById($projectId);
if (!$project) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'A projekt nem található.']);
    exit;
}

if ($project['status'] != STATUS_APPROVED) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Csak jóváhagyott projektekre lehet szavazni.']);
    exit;
}

if (!isVotingOpen($project)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A szavazás már lezárult erre a projektre.']);
    exit;
}

$currentUser = getCurrentUser();
$votes = loadJSON(VOTES_FILE);

if ($action === 'vote') {
    if (hasUserVoted($currentUser['id'], $projectId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Már szavaztál erre a projektre.']);
        exit;
    }

    $userVotesInCategory = getUserVotesInCategory($currentUser['id'], $project['category']);
    if ($userVotesInCategory >= 3) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Már felhasználtad mind a 3 szavazatod ebben a kategóriában.']);
        exit;
    }

    $votes[] = [
        'user' => $currentUser['id'],
        'project' => $projectId
    ];
    saveJSON(VOTES_FILE, $votes);

    echo json_encode([
        'success' => true,
        'vote_count' => getProjectVotes($projectId),
        'remaining_votes' => 3 - getUserVotesInCategory($currentUser['id'], $project['category'])
    ]);

} elseif ($action === 'unvote') {
    if (!hasUserVoted($currentUser['id'], $projectId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nem szavaztál erre a projektre.']);
        exit;
    }

    $votes = array_filter($votes, function($v) use ($currentUser, $projectId) {
        return !($v['user'] == $currentUser['id'] && $v['project'] == $projectId);
    });
    $votes = array_values($votes); // Re-index array
    saveJSON(VOTES_FILE, $votes);

    echo json_encode([
        'success' => true,
        'vote_count' => getProjectVotes($projectId),
        'remaining_votes' => 3 - getUserVotesInCategory($currentUser['id'], $project['category'])
    ]);

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Érvénytelen művelet.']);
}
