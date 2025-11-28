<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$project_id = $_POST['project_id'] ?? null;
$name = trim($_POST['project_name'] ?? '');
$description = trim($_POST['project_description'] ?? '');

if (!$project_id || $name === '') {
    $_SESSION['error'] = "Le nom du projet est obligatoire.";
    header('Location: modify_project.php?id=' . urlencode($project_id ?? ''));
    exit;
}

$projects = load_projects();
$project = null;
$project_index = null;

foreach ($projects as $index => $p) {
    if ($p['id'] === $project_id) {
        $project = $p;
        $project_index = $index;
        break;
    }
}

if (!$project) {
    $_SESSION['error'] = "Projet introuvable.";
    header('Location: dashboard.php');
    exit;
}

// Vérifier que l'utilisateur est membre du projet
$is_member = false;
$user_email = strtolower($user['email']);
foreach ($project['members'] as $member) {
    if (strtolower($member['email']) === $user_email) {
        $is_member = true;
        break;
    }
}
if (!$is_member) {
    $_SESSION['error'] = "Vous n'avez pas la permission de modifier ce projet.";
    header('Location: dashboard.php');
    exit;
}

// Traiter les membres
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
        continue; // Filtrer les emails invalides
    }
    
    $members[] = [
        'name' => $m_name ?: $m_email,
        'email' => strtolower($m_email),
    ];
}

// S'assurer que le créateur est dans la liste des membres
$creator_email = strtolower($project['creator_email']);
$already_in = false;
foreach ($members as $m) {
    if (strtolower($m['email']) === $creator_email) {
        $already_in = true;
        break;
    }
}
if (!$already_in) {
    $members[] = [
        'name' => $project['creator_name'],
        'email' => $creator_email,
    ];
}

// Traiter les tâches
$tasks = [];
$task_titles = $_POST['task_title'] ?? [];
$task_assigned_to = $_POST['task_assigned_to'] ?? [];
$task_ids = $_POST['task_id'] ?? [];

for ($i = 0; $i < count($task_titles); $i++) {
    $title = trim($task_titles[$i] ?? '');
    $assigned_email = trim($task_assigned_to[$i] ?? '');
    $task_id = $task_ids[$i] ?? null;
    
    if ($title === '') {
        continue;
    }
    
    // Garder l'ID existant ou en générer un nouveau
    if (!$task_id) {
        $task_id = generate_id('t_');
    }
    
    // Chercher la tâche existante pour récupérer son statut
    $status = 'todo';
    foreach ($project['tasks'] as $old_task) {
        if ($old_task['id'] === $task_id) {
            $status = $old_task['status'] ?? 'todo';
            break;
        }
    }
    
    $tasks[] = [
        'id' => $task_id,
        'title' => $title,
        'assigned_to' => strtolower($assigned_email),
        'status' => $status,
    ];
}

// Mettre à jour le projet
$project['name'] = $name;
$project['description'] = $description;
$project['members'] = $members;
$project['tasks'] = $tasks;

$projects[$project_index] = $project;
save_projects($projects);

$_SESSION['success'] = "Le projet a été modifié avec succès.";
header('Location: dashboard.php');
exit;
