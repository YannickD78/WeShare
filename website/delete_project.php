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
    $_SESSION['error'] = "Projet introuvable.";
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND created_by = ?");
    $stmt->execute([$project_id, $user['id']]);

    if ($stmt->rowCount() > 0) {
        log_activity($user['id'], 'delete_project', "project_$project_id", "Projet supprimé");
        $_SESSION['success'] = "Le projet a été supprimé avec succès.";
    } else {
        $_SESSION['error'] = "Impossible de supprimer ce projet (vous n'êtes peut-être pas le créateur).";
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Erreur technique : " . $e->getMessage();
}
header('Location: dashboard.php');
exit;
