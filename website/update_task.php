<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$project_id = $_POST['project_id'] ?? null;
$task_id    = $_POST['task_id'] ?? null;
$status     = $_POST['status'] ?? null;
$progress   = $_POST['progress'] ?? null;

$allowed_status = ['todo', 'in_progress', 'done'];
$changed = false;


if (!$project_id || !$task_id) {
    sendResponse(false, "Paramètres manquants.");
}

$projects = load_projects();


foreach ($projects as &$project) {
    if ($project['id'] !== $project_id) {
        continue;
    }

    foreach ($project['tasks'] as &$task) {
        if ($task['id'] !== $task_id) {
            continue;
        }

        // Mise à jour status
        if ($status !== null) {
            if (!in_array($status, $allowed_status, true)) {
                sendResponse(false, "Statut invalide.");
            }
            $task['status'] = $status;
            $changed = true;
        }

        // Mise à jour progress
        if ($progress !== null) {
            $pv = intval($progress);
            if ($pv < 0 || $pv > 100) {
                sendResponse(false, "Progression invalide.");
            }
            $task['progress'] = $pv;
            $changed = true;
        }

        break 2; 
    }
}


if (!$changed) {
    sendResponse(false, "Aucune modification effectuée.");
}

// Sauvegarde sécurisée
save_projects($projects);

sendResponse(true, "Mise à jour réussie.");


// -------------------------------------
// Fonction utilitaire de réponse AJAX
// -------------------------------------
function sendResponse(bool $success, string $msg)
{
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $msg
        ]);
        exit;
    }

    header('Location: dashboard.php');
    exit;
}
