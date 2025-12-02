<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$project_id = $_POST['project_id'] ?? null;
$task_id    = $_POST['task_id'] ?? null;

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

$projects = load_projects();
$user = current_user();
$user_email = strtolower($user['email']);

foreach ($projects as &$project) {
    if ($project['id'] !== $project_id) {
        continue;
    }

    // Vérifier que l'utilisateur est membre du projet
    $is_member = false;
    foreach ($project['members'] as $member) {
        if (strtolower($member['email']) === $user_email) {
            $is_member = true;
            break;
        }
    }
    
    if (!$is_member) {
        sendResponse(false, "Vous n'avez pas la permission de supprimer cette tâche.");
    }

    // Find and remove the task
    $task_removed = false;
    foreach ($project['tasks'] as $index => &$task) {
        if ($task['id'] === $task_id) {
            unset($project['tasks'][$index]);
            $task_removed = true;
            break;
        }
    }
    
    if (!$task_removed) {
        sendResponse(false, "Tâche introuvable.");
    }
    
    // Re-index the tasks array
    $project['tasks'] = array_values($project['tasks']);
    break;
}

// Save changes
try {
    save_projects($projects);
    sendResponse(true, "Tâche supprimée avec succès.");
} catch (Exception $e) {
    sendResponse(false, "Erreur de sauvegarde: " . $e->getMessage());
}
?>
