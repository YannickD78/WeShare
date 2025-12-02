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
    
    $projects = load_projects();
    $project = null;
    $projectIndex = -1;
    
    // Find project
    foreach ($projects as $idx => $p) {
        if ($p['id'] === $projectId) {
            $project = $p;
            $projectIndex = $idx;
            break;
        }
    }
    
    if (!$project) {
        throw new Exception('Project not found');
    }
    
    // Verify user is member
    $isMember = false;
    foreach ($project['members'] as $member) {
        if (strtolower($member['email']) === $email) {
            $isMember = true;
            break;
        }
    }
    
    if (!$isMember) {
        throw new Exception('Not a project member');
    }
    
    // Find task
    $task = null;
    $taskIndex = -1;
    foreach ($project['tasks'] as $idx => $t) {
        if ($t['id'] === $taskId) {
            $task = $t;
            $taskIndex = $idx;
            break;
        }
    }
    
    if (!$task) {
        throw new Exception('Task not found');
    }
    
    // Verify task is assigned to user
    if (strtolower($task['assigned_to']) !== $email) {
        throw new Exception('Task not assigned to you');
    }
    
    // Only for bar-mode recurring tasks
    if ($task['mode'] !== 'bar' || !($task['is_recurring'] ?? false)) {
        throw new Exception('Invalid task type for daily update');
    }
    
    // Accept both day abbreviations and full dates
    // Days can be: mon, tue, wed, thu, fri, sat, sun OR YYYY-MM-DD
    $validDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $isDateFormat = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayOrDate);
    
    if (!$isDateFormat && !in_array($dayOrDate, $validDays)) {
        throw new Exception('Invalid day or date format');
    }
    
    // Get current daily progress
    $currentProgress = get_daily_progress($task, $dayOrDate);
    $newProgress = $currentProgress;
    
    // Apply action
    switch ($action) {
        case 'increment':
            if ($value === null) {
                throw new Exception('Value required for increment action');
            }
            $newProgress = min(100, $currentProgress + $value);
            break;
        case 'set':
            if ($value === null) {
                throw new Exception('Value required for set action');
            }
            $newProgress = max(0, min(100, $value));
            break;
        case 'complete':
            $newProgress = 100;
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    // Update task
    set_daily_progress($projects[$projectIndex]['tasks'][$taskIndex], $dayOrDate, $newProgress);
    
    // Save
    save_projects($projects);
    
    // Return updated task
    $updatedTask = $projects[$projectIndex]['tasks'][$taskIndex];
    $taskProgress = get_daily_progress($updatedTask, $dayOrDate);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'task_id' => $taskId,
        'day_or_date' => $dayOrDate,
        'progress' => $taskProgress,
        'is_complete' => $taskProgress >= 100
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
