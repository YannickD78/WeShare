<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    exit;
}

$project_id = $_POST['project_id'] ?? null;
$task_id = $_POST['task_id'] ?? null;
$date = $_POST['date'] ?? null;

if (!$project_id || !$task_id || !$date) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND project_id = ?");
    $stmt->execute([$task_id, $project_id]);
    $row = $stmt->fetch();

    if (!$row) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    $task = [
        'id' => $row['id'],
        'mode' => $row['mode'],
        'status' => $row['status'],
        'is_recurring' => (bool)$row['is_recurring'],
        'recurring_days' => json_decode($row['recurring_days'] ?? '[]', true),
        'daily_progress' => json_decode($row['daily_progress'] ?? '[]', true),
        'daily_status' => json_decode($row['daily_status'] ?? '[]', true)
    ];
    
    // Get the daily progress for this date
    $dailyProgress = get_daily_progress($task, $date);

    // Determine the status
    $status = 'todo';
    if (($task['mode'] ?? 'status') === 'bar') {
        // For bar mode, we only care about progress
        $status = null;
    } else {
        // For status mode, get the stored status or convert from progress
        if (($task['is_recurring'] ?? false)) {
            // Check if we have a stored status in daily_status
            $dailyStatus = $task['daily_status'] ?? [];
            if (isset($dailyStatus[$date])) {
                $status = $dailyStatus[$date];
            } else {
                // Fallback: convert progress to status
                if ($dailyProgress >= 100) {
                    $status = 'done';
                } else if ($dailyProgress >= 50) {
                    $status = 'in_progress';
                } else {
                    $status = 'todo';
                }
            }
        } else {
            // Non-recurring task: use global status
            $status = $task['status'] ?? 'todo';
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'progress' => $dailyProgress,
        'status' => $status,
        'date' => $date,
        'mode' => $task['mode'] ?? 'status'
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
exit;
