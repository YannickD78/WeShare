<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * JSON file read/write utilities (à changer plus tard pour la base de données)
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
/*function load_users(): array
{
    return load_json(USERS_FILE);
}

function save_users(array $users): void
{
    save_json(USERS_FILE, $users);
}*/

/*function find_user_by_email(string $email): ?array
{
    $users = load_users();
    $email = strtolower(trim($email));
    foreach ($users as $user) {
        if (strtolower($user['email']) === $email) {
            return $user;
        }
    }
    return null;
}*/

function find_user_by_email(string $email): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([strtolower(trim($email))]);
    
    $user = $stmt->fetch();
    return $user ?: null;
}

/*function create_user(string $name, string $email, string $password): array
{
    $users = load_users();

    if (find_user_by_email($email)) {
        throw new Exception("Cet email est déjà utilisé.");
    }

    $user = [
        'id' => uniqid('u_', true),
        'name' => trim($name),
        'email' => strtolower(trim($email)),
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ];

    $users[] = $user;
    save_users($users);

    return $user;
}*/

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
        'name' => $user['nom'],
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
 * Project-related functions (à changer plus tard pour la base de données)
 */
function load_projects(): array
{
    $projects = load_json(PROJECTS_FILE);
    
    // Auto-migrate old data format
    migrate_daily_progress_keys($projects);
    
    return $projects;
}

function save_projects(array $projects): void
{
    // Auto-migrate old data format before saving
    migrate_daily_progress_keys($projects);
    
    $tmpFile = PROJECTS_FILE . '.tmp';

    // Encode en JSON propre
    $json = json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // S'assurer que le répertoire existe
    $dataDir = dirname(PROJECTS_FILE);
    if (!is_dir($dataDir)) {
        throw new Exception("Le répertoire data n'existe pas: $dataDir");
    }
    
    // Vérifier les permissions d'écriture
    if (!is_writable($dataDir)) {
        throw new Exception("Le répertoire data n'est pas accessible en écriture: $dataDir");
    }

    // Écrit dans un fichier temporaire avec verrou
    $fp = fopen($tmpFile, 'c');
    if (!$fp) {
        throw new Exception("Impossible d'écrire dans le fichier temporaire: $tmpFile");
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new Exception("Impossible de verrouiller le fichier des projets.");
    }

    ftruncate($fp, 0);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    // Remplace le fichier original
    if (!rename($tmpFile, PROJECTS_FILE)) {
        throw new Exception("Impossible de renommer le fichier temporaire vers " . PROJECTS_FILE);
    }
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
 * Reset completed recurring tasks at the start of a new day
 * For tasks completed on their assigned day, reset them if it's a new day
 */
function reset_daily_tasks(): void
{
    try {
        $projects = load_projects();
        $today = new DateTime('today');
        $modified = false;
        
        foreach ($projects as &$project) {
            foreach ($project['tasks'] as &$task) {
                // Only handle non-recurring tasks
                // Recurring tasks manage their state through daily_progress per day
                if (($task['is_recurring'] ?? false)) {
                    continue;
                }
                
                // Only reset non-recurring tasks that are complete
                if (!is_task_complete($task)) {
                    continue;
                }
                
                // For non-recurring tasks, reset them
                $mode = $task['mode'] ?? 'status';
                if ($mode === 'bar') {
                    $task['progress'] = 0;
                } else {
                    $task['status'] = 'todo';
                }
                $modified = true;
            }
        }
        
        if ($modified) {
            save_projects($projects);
        }
    } catch (Exception $e) {
        // Silently fail - this is a maintenance function
    }
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

