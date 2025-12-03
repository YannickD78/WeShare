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

if ($name === '') {
    $_SESSION['error'] = "Le nom du projet est obligatoire.";
    header('Location: create_project.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO projects (nom, description, created_by, status) VALUES (?, ?, ?, 'active')");
    $stmt->execute([$name, $description, $user['id']]);
    $project_id = $pdo->lastInsertId();

    $member_names = $_POST['member_name'] ?? [];
    $member_emails = $_POST['member_email'] ?? [];

    $stmt = $pdo->prepare("INSERT INTO participants (project_id, user_id) VALUES (?, ?)");
    $stmt->execute([$project_id, $user['id']]);

    for ($i = 0; $i < count($member_emails); $i++) {
        $m_email = strtolower(trim($member_emails[$i] ?? ''));
        $m_name = trim($member_names[$i] ?? '');

        if ($m_email === '' || !filter_var($m_email, FILTER_VALIDATE_EMAIL)) continue;
        if ($m_email === strtolower($user['email'])) continue;

        $existing = find_user_by_email($m_email);
        $member_id = null;

        if ($existing) {
            $member_id = $existing['id'];
        } else {
            // On crée un compte invité
            $new_user = create_user($m_name ?: explode('@', $m_email)[0], $m_email, 'weshare_invite');
            $member_id = $new_user['id'];
        }

        // On vérif doublon avant insertion
        $check = $pdo->prepare("SELECT id FROM participants WHERE project_id = ? AND user_id = ?");
        $check->execute([$project_id, $member_id]);
        if (!$check->fetch()) {
            $stmt->execute([$project_id, $member_id]);
        }
    }

    $task_titles = $_POST['task_title'] ?? [];
    $task_assigned_to = $_POST['task_assigned_to'] ?? [];
    $task_modes = $_POST['task_mode'] ?? [];
    $task_recurring = $_POST['task_recurring'] ?? [];
    $task_recurring_days = $_POST['task_recurring_days'] ?? [];

    // On prépare l'insertion
    $sqlTask = "INSERT INTO tasks (project_id, titre, assigned_to, status, progress, mode, is_recurring, recurring_days, daily_progress, daily_status) 
                VALUES (?, ?, ?, 'todo', 0, ?, ?, ?, '{}', '{}')";
    $stmtTask = $pdo->prepare($sqlTask);

    for ($i = 0; $i < count($task_titles); $i++) {
        $title = trim($task_titles[$i] ?? '');
        if ($title === '') continue;

        $assigned_email = strtolower(trim($task_assigned_to[$i] ?? ''));
        $mode = trim($task_modes[$i] ?? 'status');
        if ($mode !== 'bar') $mode = 'status';

        $is_rec = isset($task_recurring[$i]) && $task_recurring[$i] === 'on' ? 1 : 0;
        
        // Encodage JSON des jours (tableau php -> texte JSON pour la bdd)
        $rec_days_json = '[]';
        if ($is_rec && isset($task_recurring_days[$i]) && is_array($task_recurring_days[$i])) {
            $rec_days_json = json_encode($task_recurring_days[$i]);
        }

        // Pour trouver l'id via l'email
        $assigned_id = null;
        if ($assigned_email) {
            $u = find_user_by_email($assigned_email);
            if ($u) $assigned_id = $u['id'];
        }

        $stmtTask->execute([$project_id, $title, $assigned_id, $mode, $is_rec, $rec_days_json]);
    }

    $pdo->commit();
    log_activity($user['id'], 'create_project', 'project', "Projet $project_id créé");
    
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur lors de la sauvegarde : " . $e->getMessage());
}
?>