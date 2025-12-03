<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$task_id    = $_POST['task_id'] ?? null;
$status     = $_POST['status'] ?? null;
$progress   = $_POST['progress'] ?? null;
$dateOrDay  = $_POST['date'] ?? null;  // For recurring tasks, the specific date/day

if (!$task_id) {
    sendResponse(false, "Paramètres manquants.");
}

$allowed_status = ['todo', 'in_progress', 'done'];

try {
    // On récupère la tâche (sql)
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $row = $stmt->fetch();

    if (!$row) {
        sendResponse(false, "Tâche introuvable.");
    }

    // On décode les colonnes texte pour manipuler des tableaux
    $task = [
        'id' => $row['id'],
        'status' => $row['status'],
        'progress' => (int)$row['progress'],
        'mode' => $row['mode'],
        'is_recurring' => (bool)$row['is_recurring'],
        'daily_progress' => json_decode($row['daily_progress'] ?? '[]', true),
        'daily_status' => json_decode($row['daily_status'] ?? '[]', true)
    ];

    $changed = false;

    // Update status
    if ($status !== null) {
        if (!in_array($status, $allowed_status, true)) sendResponse(false, "Statut invalide.");
        
        if ($task['is_recurring'] && $dateOrDay) {
            if ($task['mode'] === 'bar') {
                $statusValue = ($status === 'done') ? 100 : (($status === 'in_progress') ? 50 : 0);
                set_daily_progress($task, $dateOrDay, $statusValue);
            } else {
                if (!isset($task['daily_status'])) $task['daily_status'] = [];
                $task['daily_status'][$dateOrDay] = $status;
            }
        } else {
            $task['status'] = $status;
        }
        $changed = true;
    }

    // Update progress
    if ($progress !== null) {
        $pv = intval($progress);
        if ($pv < 0 || $pv > 100) sendResponse(false, "Progression invalide.");
        
        if ($task['is_recurring'] && $dateOrDay) {
            set_daily_progress($task, $dateOrDay, $pv);
        } else {
            $task['progress'] = $pv;
        }
        $changed = true;
    }

    if (!$changed) {
        sendResponse(false, "Aucune modification effectuée.");
    }

    // On sauvegarde en bdd (encode en JSON)
    $sql = "UPDATE tasks SET status = ?, progress = ?, daily_progress = ?, daily_status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $task['status'],
        $task['progress'],
        json_encode($task['daily_progress']), 
        json_encode($task['daily_status']),
        $task_id
    ]);

    log_activity(current_user()['id'], 'update_task', "task_$task_id", "Status: $status / Progress: $progress");

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Mise à jour réussie.",
            'progress' => intval($_POST['progress'] ?? 0),
            'status' => $_POST['status'] ?? null,
            'date' => $dateOrDay
        ]);
        exit;
    }
    
    sendResponse(true, "Mise à jour réussie.");

} catch (Exception $e) {
    sendResponse(false, "Erreur serveur : " . $e->getMessage());
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
?>