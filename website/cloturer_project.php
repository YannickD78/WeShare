<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$project_id = $_POST['project_id'] ?? '';
$user = current_user();

if (!$project_id) {
    header('Location: dashboard.php');
    exit;
}

try {    
    $stmt = $pdo->prepare("UPDATE projects SET status = 'done' WHERE id = ? AND created_by = ?");
    $stmt->execute([$project_id, $user['id']]);
    
    if ($stmt->rowCount() > 0) {
        log_activity($user['id'], 'close_project', "project_$project_id", "Projet clôturé");
    }

} catch (Exception $e) {
    // Silencieux
}

header('Location: dashboard.php');
exit;
