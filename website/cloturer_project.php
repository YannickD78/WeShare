<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$project_id = $_POST['project_id'] ?? '';
$user = current_user();
$email = strtolower($user['email']);

$projects = load_projects();

foreach ($projects as &$project) {
    if ($project['id'] === $project_id) {
        
        // Seul le créateur peut clôturer
        if (strtolower($project['creator_email']) === $email) {
            $project['status'] = 'done';
        }

        break;
    }
}

save_projects($projects);

header('Location: dashboard.php');
exit;
