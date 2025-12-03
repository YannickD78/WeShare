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
    $stmt = $pdo->prepare("UPDATE projects SET status = 'active' WHERE id = ? AND created_by = ?");
    $stmt->execute([$project_id, $user['id']]);

    if ($stmt->rowCount() > 0) {
        log_activity($user['id'], 'reactivate_project', "project_$project_id", "Projet réactivé");
    }

} catch (Exception $e) {
    // Silencieux en cas d'erreur
}

header('Location: dashboard.php');
exit;
