<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * JSON file read/write utilities
 */
function load_json(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_json(string $file, array $data): void
{
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * User-related functions
 */

function find_user_by_email(string $email): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT id, nom as name, email, password FROM users WHERE email = ?");
    $stmt->execute([strtolower(trim($email))]);
    
    $user = $stmt->fetch();
    return $user ?: null;
}

function create_user(string $name, string $email, string $password): array
{
    global $pdo;

    if (find_user_by_email($email)) {
        throw new Exception("Cet email est déjà utilisé.");
    }

    $emailClean = strtolower(trim($email));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (nom, email, password) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $emailClean, $passwordHash]);

    return [
        'id' => $pdo->lastInsertId(),
        'nom' => $name,
        'email' => $emailClean
    ];
}

function authenticate(string $email, string $password): ?array
{
    $user = find_user_by_email($email);
    if (!$user) {
        return null;
    }
    if (!password_verify($password, $user['password'])) {
        return null;
    }
    return $user;
}

function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
    ];
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Project-related functions
 */

function get_full_user_projects(int $user_id): array {
    global $pdo;
    
    // On récupère les ID des projets (créés ou membre)
    $sql = "SELECT DISTINCT p.id 
            FROM projects p
            LEFT JOIN participants part ON p.id = part.project_id
            WHERE p.created_by = ? OR part.user_id = ?
            ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    $projectIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($projectIds)) return [];

    $projects = [];

    foreach ($projectIds as $pid) {
        // Infos du projet
        $stmt = $pdo->prepare("SELECT p.id, p.nom as name, p.description, p.status, p.created_at,
                                      u.nom as creator_name, u.email as creator_email, p.created_by as creator_id
                               FROM projects p
                               JOIN users u ON p.created_by = u.id
                               WHERE p.id = ?");
        $stmt->execute([$pid]);
        $projData = $stmt->fetch();

        // Infos des membres
        $stmt = $pdo->prepare("SELECT u.nom as name, u.email 
                               FROM participants part
                               JOIN users u ON part.user_id = u.id
                               WHERE part.project_id = ?");
        $stmt->execute([$pid]);
        $members = $stmt->fetchAll();
        
        // On ajoute le créateur à la liste des membres si pas présent
        $creatorInMembers = false;
        foreach ($members as $m) {
            if ($m['email'] === $projData['creator_email']) $creatorInMembers = true;
        }
        if (!$creatorInMembers) {
            array_unshift($members, ['name' => $projData['creator_name'], 'email' => $projData['creator_email']]);
        }
        $projData['members'] = $members;

        // Infos des taches
        $stmt = $pdo->prepare("SELECT t.*, u.email as assigned_email 
                               FROM tasks t 
                               LEFT JOIN users u ON t.assigned_to = u.id 
                               WHERE t.project_id = ?");
        $stmt->execute([$pid]);
        $dbTasks = $stmt->fetchAll();
        
        $tasks = [];
        foreach ($dbTasks as $t) {
            // On décode les JSON stockés en texte pour retrouver les tableaux php
            $tasks[] = [
                'id' => $t['id'],
                'title' => $t['titre'],
                'assigned_to' => $t['assigned_email'] ?? '', 
                'assigned_to_id' => $t['assigned_to'],
                'status' => $t['status'],
                'progress' => (int)$t['progress'],
                'mode' => $t['mode'],
                'is_recurring' => (bool)$t['is_recurring'],
                'recurring_days' => json_decode($t['recurring_days'] ?? '[]', true),
                'daily_progress' => json_decode($t['daily_progress'] ?? '[]', true),
                'daily_status' => json_decode($t['daily_status'] ?? '[]', true)
            ];
        }
        $projData['tasks'] = $tasks;

        $projects[] = $projData;
    }

    return $projects;
}


function generate_id(string $prefix = ''): string
{
    return uniqid($prefix, true);
}

/**
 * Get human-readable status label
 */
function status_label(string $status): string
{
    switch ($status) {
        case 'in_progress':
            return 'En cours';
        case 'done':
            return 'Terminé';
        case 'todo':
        default:
            return 'À faire';
    }
}

/**
 * Check if task is complete (done or at 100%)
 * For recurring tasks, checks daily progress for the specific date, or checks ALL daily entries without a specific date
 */
function is_task_complete(array $task, ?string $specificDate = null): bool
{
    $mode = $task['mode'] ?? 'status';
    
    if (!($task['is_recurring'] ?? false)) {
        // Non-recurring task
        if ($mode === 'bar') {
            return ($task['progress'] ?? 0) >= 100;
        } else {
            return ($task['status'] ?? 'todo') === 'done';
        }
    }
    
    // Recurring task
    if ($specificDate) {
        // Check specific date for recurring task
        if ($mode === 'bar') {
            // For bar mode, check daily_progress
            $dailyProgress = $task['daily_progress'] ?? [];
            return ($dailyProgress[$specificDate] ?? 0) >= 100;
        } else {
            // For status mode, check daily_status first, then fallback to daily_progress
            $dailyStatus = $task['daily_status'] ?? [];
            if (isset($dailyStatus[$specificDate])) {
                return $dailyStatus[$specificDate] === 'done';
            }
            // Fallback to progress-based completion
            $dailyProgress = $task['daily_progress'] ?? [];
            return ($dailyProgress[$specificDate] ?? 0) >= 100;
        }
    }
    
    // No specific date for recurring task: check if all daily entries are complete
    if ($mode === 'bar') {
        // For bar mode: all must be >= 100
        $dailyProgress = $task['daily_progress'] ?? [];
        if (empty($dailyProgress)) {
            return false;
        }
        foreach ($dailyProgress as $progress) {
            if ($progress < 100) {
                return false;
            }
        }
        return true;
    } else {
        // For status mode: all must be 'done'
        $dailyStatus = $task['daily_status'] ?? [];
        if (empty($dailyStatus)) {
            return false;
        }
        foreach ($dailyStatus as $status) {
            if ($status !== 'done') {
                return false;
            }
        }
        return true;
    }
}

/**
 * Format recurring days for display
 */
function format_recurring_days(array $recurring_days): string
{
    if (empty($recurring_days)) {
        return '';
    }
    
    $dayLabels = [
        'mon' => 'Lun',
        'tue' => 'Mar',
        'wed' => 'Mer',
        'thu' => 'Jeu',
        'fri' => 'Ven',
        'sat' => 'Sam',
        'sun' => 'Dim'
    ];
    
    $labels = [];
    foreach ($recurring_days as $day) {
        if (isset($dayLabels[$day])) {
            $labels[] = $dayLabels[$day];
        }
    }
    
    return implode(', ', $labels);
}

/**
 * Get progress for a task on a specific day (by date string or day abbreviation)
 */
/**
 * Get daily progress/status for a task on a specific date/day
 * For bar mode: returns progress value (0-100)
 * For status mode: returns progress value if available, otherwise returns 0
 */
function get_daily_progress(array $task, ?string $dayOrDate = null): int
{
    if (!($task['is_recurring'] ?? false)) {
        return $task['progress'] ?? 0;
    }
    
    if (!$dayOrDate) {
        return 0;
    }
    
    $dailyProgress = $task['daily_progress'] ?? [];
    
    // If it's a date (YYYY-MM-DD format), try multiple lookups
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayOrDate)) {
        // Try the date key directly
        if (isset($dailyProgress[$dayOrDate])) {
            return $dailyProgress[$dayOrDate];
        }
        
        // Fallback: try old format with "status_" prefix (for backward compatibility)
        $statusKey = "status_" . $dayOrDate;
        if (isset($dailyProgress[$statusKey])) {
            return $dailyProgress[$statusKey];
        }
        
        // Fallback: try matching by day of week
        try {
            $date = new DateTime($dayOrDate);
            $dayOfWeek = (int)$date->format('N'); // 1=Monday, 7=Sunday
            $dayToRecurring = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];
            $dayAbbr = $dayToRecurring[$dayOfWeek] ?? null;
            if ($dayAbbr && isset($dailyProgress[$dayAbbr])) {
                return $dailyProgress[$dayAbbr];
            }
        } catch (Exception $e) {
            // Invalid date format, continue
        }
        
        return 0;
    }
    
    // Otherwise assume it's a day abbreviation (mon, tue, etc)
    return $dailyProgress[$dayOrDate] ?? 0;
}

/**
 * Get daily status for a task on a specific date/day
 * For status mode: returns status string (todo/in_progress/done)
 * For bar mode: converts progress to status (100=done, 50=in_progress, else=todo)
 */
function get_daily_status(array $task, ?string $dayOrDate = null): string
{
    if (!($task['is_recurring'] ?? false)) {
        return $task['status'] ?? 'todo';
    }
    
    if (!$dayOrDate) {
        return 'todo';
    }
    
    $mode = $task['mode'] ?? 'status';
    
    // For status mode, check daily_status first
    if ($mode === 'status') {
        $dailyStatus = $task['daily_status'] ?? [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayOrDate)) {
            if (isset($dailyStatus[$dayOrDate])) {
                return $dailyStatus[$dayOrDate];
            }
        } else {
            // day abbreviation
            if (isset($dailyStatus[$dayOrDate])) {
                return $dailyStatus[$dayOrDate];
            }
        }
    }
    
    // Fallback: convert progress to status for bar mode or if daily_status not found
    $progress = get_daily_progress($task, $dayOrDate);
    if ($progress >= 100) {
        return 'done';
    } elseif ($progress >= 50) {
        return 'in_progress';
    }
    return 'todo';
}

/**
 * Set progress for a task on a specific day (by date string or day abbreviation)
 */
function set_daily_progress(array &$task, ?string $dayOrDate, int $progress): void
{
    if (!($task['is_recurring'] ?? false)) {
        $task['progress'] = max(0, min(100, $progress));
        return;
    }
    
    if (!$dayOrDate) {
        return;
    }
    
    if (!isset($task['daily_progress'])) {
        $task['daily_progress'] = [];
    }
    
    $task['daily_progress'][$dayOrDate] = max(0, min(100, $progress));
}

/**
 * Get average progress for recurring task across all its days
 * Or total progress for non-recurring tasks
 */
function get_overall_progress(array $task): int
{
    if (!($task['is_recurring'] ?? false)) {
        return $task['progress'] ?? 0;
    }
    
    $dailyProgress = $task['daily_progress'] ?? [];
    if (empty($dailyProgress)) {
        return 0;
    }
    
    return (int)round(array_sum($dailyProgress) / count($dailyProgress));
}

/**
 * Migrate old daily_progress keys to new format (removes "status_" prefix)
 * This ensures old data with "status_2025-12-02" becomes "2025-12-02"
 */
function migrate_daily_progress_keys(array &$projects): void
{
    foreach ($projects as &$project) {
        foreach ($project['tasks'] ?? [] as &$task) {
            if (!isset($task['daily_progress']) || !is_array($task['daily_progress'])) {
                continue;
            }
            
            $newProgress = [];
            foreach ($task['daily_progress'] as $key => $value) {
                // Remove "status_" prefix if present
                if (strpos($key, 'status_') === 0) {
                    $newKey = substr($key, 7); // Remove "status_" prefix
                    $newProgress[$newKey] = $value;
                } else {
                    $newProgress[$key] = $value;
                }
            }
            
            $task['daily_progress'] = $newProgress;
        }
    }
}

function get_latest_daily_status($task) {
    if (!($task['is_recurring'] ?? false) || empty($task['daily_status'])) {
        return $task['status'] ?? 'todo';
    }
    // Cherche le statut du jour le plus proche (passé ou aujourd'hui)
    $today = date('Y-m-d');
    $closest = null;
    foreach ($task['daily_status'] as $date => $status) {
        if ($date <= $today && ($closest === null || $date > $closest)) {
            $closest = $date;
        }
    }
    return $closest ? $task['daily_status'][$closest] : $task['status'] ?? 'todo';
}

/**
 * Get the relevant date for a recurring task (today or next occurrence)
 * Returns the YYYY-MM-DD date string
 */
function get_relevant_date_for_recurring_task(array $task): ?string
{
    if (!($task['is_recurring'] ?? false)) {
        return null;
    }

    $dayMap = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
    $today = new DateTime();
    $currentDayNum = (int)$today->format('w'); // 0=Sun, 1=Mon, etc.

    // Find the next occurrence of this task
    $minDaysAhead = 7;
    $nextOccurrence = null;

    foreach ($task['recurring_days'] ?? [] as $day) {
        $targetDayNum = $dayMap[$day] ?? null;
        if ($targetDayNum === null) continue;
        
        $daysAhead = ($targetDayNum - $currentDayNum + 7) % 7;
        if ($daysAhead < $minDaysAhead) {
            $minDaysAhead = $daysAhead;
            $nextOccurrence = $daysAhead;
        }
    }

    if ($nextOccurrence !== null) {
        $relevantDate = new DateTime();
        $relevantDate->modify("+{$nextOccurrence} days");
        return $relevantDate->format('Y-m-d');
    }

    return null;
}

// Track user activity
function log_activity(?int $user_id, string $action, string $target, string $details = ''): void {
    global $pdo;
    try {
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $sql = "INSERT INTO activity_logs (user_id, type_action, element_cible, page_url, details) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $action, $target, $url, $details]);
    } catch (Exception $e) {
        // Silencieux pour ne pas bloquer
    }
}
