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
$dateOrDay  = $_POST['date'] ?? null;  // For recurring tasks, the specific date/day

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
            
            // For recurring tasks with a specific date, update daily_progress
            // Store status as progress value: done=100, in_progress=50, todo=0
            if (($task['is_recurring'] ?? false) && $dateOrDay) {
                $statusValue = ($status === 'done') ? 100 : (($status === 'in_progress') ? 50 : 0);
                set_daily_progress($task, $dateOrDay, $statusValue);
            } else {
                // For non-recurring tasks, update global status
                $task['status'] = $status;
            }
            $changed = true;
        }

        // Mise à jour progress (non-recurring or global for recurring)
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
try {
    save_projects($projects);
    sendResponse(true, "Mise à jour réussie.");
} catch (Exception $e) {
    sendResponse(false, "Erreur de sauvegarde: " . $e->getMessage());
}


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
