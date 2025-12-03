<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$project_id = $_POST['project_id'] ?? null;
$task_id    = $_POST['task_id'] ?? null;
$user = current_user();

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function sendResponse(bool $success, string $msg)
{
    global $is_ajax;
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $msg
        ]);
        exit;
    }

    if ($success) {
        $_SESSION['success'] = $msg;
    } else {
        $_SESSION['error'] = $msg;
    }
    header('Location: dashboard.php');
    exit;
}

if (!$project_id || !$task_id) {
    sendResponse(false, "Paramètres manquants.");
}

try {
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM projects p 
        LEFT JOIN participants part ON p.id = part.project_id 
        WHERE p.id = ? AND (p.created_by = ? OR part.user_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$project_id, $user['id'], $user['id']]);
    
    if (!$stmt->fetch()) {
        sendResponse(false, "Vous n'avez pas la permission de supprimer cette tâche.");
    }

    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND project_id = ?");
    $stmt->execute([$task_id, $project_id]);

    if ($stmt->rowCount() > 0) {
        log_activity($user['id'], 'delete_task', "task_$task_id", "Tâche supprimée");
        sendResponse(true, "Tâche supprimée avec succès.");
    } else {
        sendResponse(false, "Tâche introuvable ou déjà supprimée.");
    }

} catch (Exception $e) {
    sendResponse(false, "Erreur de base de données.");
}
?>
