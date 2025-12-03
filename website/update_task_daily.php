<?php
/**
 * AJAX endpoint to update daily task progress
 * Handles incremental updates (+10%, +25%) and completion
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$email = strtolower($user['email']);

header('Content-Type: application/json');

try {
    $projectId = $_POST['project_id'] ?? null;
    $taskId = $_POST['task_id'] ?? null;
    $dayOrDate = $_POST['day'] ?? null;
    $action = $_POST['action'] ?? null; // 'increment', 'set', 'complete'
    $value = isset($_POST['value']) ? (int)$_POST['value'] : null;
    
    if (!$projectId || !$taskId || !$dayOrDate || !$action) {
        throw new Exception('Missing required parameters');
    }
    
    // On récupère la tache en bdd
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND project_id = ?");
    $stmt->execute([$taskId, $projectId]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception('Task not found');
    }

    // On vérifie les permissions (est-ce que le user est assigné ?)
    if ($row['assigned_to'] != $user['id']) {
        throw new Exception('Task not assigned to you');
    }

    // On reconstruit l'objet tache php avec les JSON décodés
    $task = [
        'id' => $row['id'],
        'mode' => $row['mode'],
        'is_recurring' => (bool)$row['is_recurring'],
        'daily_progress' => json_decode($row['daily_progress'] ?? '[]', true),
    ];

    // Vérif mode
    if ($task['mode'] !== 'bar' || !$task['is_recurring']) {
        throw new Exception('Invalid task type for daily update');
    }

    // Logique de mise à jour
    $currentProgress = get_daily_progress($task, $dayOrDate);
    $newProgress = $currentProgress;

    switch ($action) {
        case 'increment':
            if ($value === null) throw new Exception('Value required');
            $newProgress = min(100, $currentProgress + $value);
            break;
        case 'set':
            if ($value === null) throw new Exception('Value required');
            $newProgress = max(0, min(100, $value));
            break;
        case 'complete':
            $newProgress = 100;
            break;
        default:
            throw new Exception('Invalid action');
    }

    // Mise à jour de l'array
    set_daily_progress($task, $dayOrDate, $newProgress);

    // Sauvegarde sql
    $sql = "UPDATE tasks SET daily_progress = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        json_encode($task['daily_progress']),
        $taskId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
