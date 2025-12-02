<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create_project.php');
    exit;
}

$name = trim($_POST['project_name'] ?? '');
$description = trim($_POST['project_description'] ?? '');
$project_id = trim($_POST['project_id'] ?? '');

if ($name === '') {
    $_SESSION['error'] = "Le nom du projet est obligatoire.";
    header('Location: create_project.php');
    exit;
}

// 处理成员
$members = [];
$member_names = $_POST['member_name'] ?? [];
$member_emails = $_POST['member_email'] ?? [];

for ($i = 0; $i < count($member_emails); $i++) {
    $m_name = trim($member_names[$i] ?? '');
    $m_email = trim($member_emails[$i] ?? '');
    if ($m_email === '' && $m_name === '') {
        continue;
    }
    if (!filter_var($m_email, FILTER_VALIDATE_EMAIL)) {
        continue; // 简单过滤无效 email
    }
    $members[] = [
        'name' => $m_name ?: $m_email,
        'email' => strtolower($m_email),
    ];
}

// 确保创建人也在成员列表中（按 email 去重）
$creator_email = strtolower($user['email']);
$already_in = false;
foreach ($members as $m) {
    if (strtolower($m['email']) === $creator_email) {
        $already_in = true;
        break;
    }
}
if (!$already_in) {
    $members[] = [
        'name' => $user['name'],
        'email' => $creator_email,
    ];
}

// Traiter les tâches - au départ pas de progress/status, juste titre et assigné
$tasks = [];
$task_titles = $_POST['task_title'] ?? [];
$task_assigned_to = $_POST['task_assigned_to'] ?? [];
$task_modes = $_POST['task_mode'] ?? [];
$task_recurring = $_POST['task_recurring'] ?? [];
$task_recurring_days = $_POST['task_recurring_days'] ?? [];

for ($i = 0; $i < count($task_titles); $i++) {
    $title = trim($task_titles[$i] ?? '');
    $assigned_email = trim($task_assigned_to[$i] ?? '');
    $mode = trim($task_modes[$i] ?? 'status');
    $is_recurring = isset($task_recurring[$i]) && $task_recurring[$i] === 'on' ? true : false;
    
    // Valider le mode
    if ($mode !== 'bar') {
        $mode = 'status';
    }
    
    if ($title === '') {
        continue;
    }
    
    // Parse recurring days
    $recurring_days = [];
    if ($is_recurring && isset($task_recurring_days[$i]) && is_array($task_recurring_days[$i])) {
        $valid_days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        foreach ($task_recurring_days[$i] as $day) {
            $day = strtolower(trim($day));
            if (in_array($day, $valid_days)) {
                $recurring_days[] = $day;
            }
        }
    }
    
    $tasks[] = [
        'id' => generate_id('t_'),
        'title' => $title,
        'assigned_to' => strtolower($assigned_email),
        'status' => 'todo',
        'progress' => 0,
        'mode' => $mode,
        'is_recurring' => $is_recurring,
        'recurring_days' => $recurring_days,
    ];
}

$projects = load_projects();

$project = [
    'id' => $project_id ?: generate_id('proj_'),
    'name' => $name,
    'description' => $description,
    'creator_id' => $user['id'],
    'creator_name' => $user['name'],
    'creator_email' => $creator_email,
    'members' => $members,
    'tasks' => $tasks,
    'created_at' => date('c'),
    'status' => 'active',
];

$projects[] = $project;
save_projects($projects);

header('Location: dashboard.php');
exit;
