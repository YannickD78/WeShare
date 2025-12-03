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

try {
    $pdo->beginTransaction();

    // On vérifie les droits et l'existence du projet
    $stmt = $pdo->prepare("
        SELECT p.id, p.created_by 
        FROM projects p 
        JOIN participants part ON p.id = part.project_id 
        WHERE p.id = ? AND part.user_id = ?
    ");
    $stmt->execute([$project_id, $user['id']]);
    $project = $stmt->fetch();

    if (!$project) {
        throw new Exception("Projet introuvable ou droits insuffisants.");
    }

    // On met à jour les infos de base
    $stmt = $pdo->prepare("UPDATE projects SET nom = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $description, $project_id]);

    // On gère les membres (sync)
    $member_emails_input = $_POST['member_email'] ?? [];
    $member_names_input = $_POST['member_name'] ?? [];
    $final_member_ids = [];

    $final_member_ids[] = $project['created_by'];

    for ($i = 0; $i < count($member_emails_input); $i++) {
        $m_email = strtolower(trim($member_emails_input[$i] ?? ''));
        $m_name = trim($member_names_input[$i] ?? '');

        if ($m_email === '' || !filter_var($m_email, FILTER_VALIDATE_EMAIL)) continue;

        // Trouver ou créer l'utilisateur
        $u = find_user_by_email($m_email);
        if ($u) {
            $final_member_ids[] = $u['id'];
        } else {
            $new_user = create_user($m_name ?: explode('@', $m_email)[0], $m_email, 'weshare_invite');
            $final_member_ids[] = $new_user['id'];
        }
    }
    
    $final_member_ids = array_unique($final_member_ids);

    // On supprime les anciens membres qui ne sont plus dans la liste
    $placeholders = implode(',', array_fill(0, count($final_member_ids), '?'));
    $sqlDeleteMembers = "DELETE FROM participants WHERE project_id = ? AND user_id NOT IN ($placeholders)";
    // Puis on fusionne l'ID projet avec les IDs membres pour les paramètres
    $stmt = $pdo->prepare($sqlDeleteMembers);
    $stmt->execute(array_merge([$project_id], $final_member_ids));

    // On ajoute les nouveaux membres
    $stmtCheck = $pdo->prepare("SELECT id FROM participants WHERE project_id = ? AND user_id = ?");
    
    foreach ($final_member_ids as $uid) {
        $stmtCheck->execute([$project_id, $uid]);
        if (!$stmtCheck->fetch()) {
            $stmtInsert = $pdo->prepare("INSERT INTO participants (project_id, user_id) VALUES (?, ?)");
            $stmtInsert->execute([$project_id, $uid]);
        }
    }


    // On gère les taches (sync)
    
    $task_ids_input = $_POST['task_id'] ?? [];
    $task_titles = $_POST['task_title'] ?? [];
    $task_assigned_to = $_POST['task_assigned_to'] ?? [];
    $task_modes = $_POST['task_mode'] ?? [];
    $task_recurring = $_POST['task_recurring'] ?? [];
    $task_recurring_days = $_POST['task_recurring_days'] ?? []; // Tableau à 2 dimensions

    // On supprime les taches qui ne sont plus dans le formulaire
    $valid_task_ids = array_filter($task_ids_input); // Enlève les vides (nouvelles taches)
    
    if (!empty($valid_task_ids)) {
        $placeholders = implode(',', array_fill(0, count($valid_task_ids), '?'));
        $sqlDeleteTasks = "DELETE FROM tasks WHERE project_id = ? AND id NOT IN ($placeholders)";
        $stmt = $pdo->prepare($sqlDeleteTasks);
        $stmt->execute(array_merge([$project_id], $valid_task_ids));
    } else {
        if (count($task_titles) > 0) {
             $pdo->prepare("DELETE FROM tasks WHERE project_id = ?")->execute([$project_id]);
        }
        if (empty($task_titles)) {
             $pdo->prepare("DELETE FROM tasks WHERE project_id = ?")->execute([$project_id]);
        }
    }


    // On met à jour ou crée les taches
    $stmtUpdate = $pdo->prepare("UPDATE tasks SET titre=?, assigned_to=?, mode=?, is_recurring=?, recurring_days=? WHERE id=? AND project_id=?");
    $stmtInsert = $pdo->prepare("INSERT INTO tasks (project_id, titre, assigned_to, status, progress, mode, is_recurring, recurring_days, daily_progress, daily_status) VALUES (?, ?, ?, 'todo', 0, ?, ?, ?, '{}', '{}')");

    for ($i = 0; $i < count($task_titles); $i++) {
        $t_title = trim($task_titles[$i] ?? '');
        if ($t_title === '') continue;

        $t_id = $task_ids_input[$i] ?? '';
        $t_assigned_email = strtolower(trim($task_assigned_to[$i] ?? ''));
        $t_mode = ($task_modes[$i] ?? 'status') === 'bar' ? 'bar' : 'status';
        
        $t_is_rec = isset($task_recurring[$i]) ? 1 : 0; 
        
        $t_rec_days_json = '[]';
        if ($t_is_rec && isset($task_recurring_days[$i])) {
            $t_rec_days_json = json_encode($task_recurring_days[$i]);
        }

        // Trouver id assigné
        $t_assigned_id = null;
        if ($t_assigned_email) {
            $u = find_user_by_email($t_assigned_email);
            if ($u) $t_assigned_id = $u['id'];
        }

        if ($t_id) {
            // Mise à jour
            $stmtUpdate->execute([$t_title, $t_assigned_id, $t_mode, $t_is_rec, $t_rec_days_json, $t_id, $project_id]);
        } else {
            // Création
            $stmtInsert->execute([$project_id, $t_title, $t_assigned_id, $t_mode, $t_is_rec, $t_rec_days_json]);
        }
    }

    $pdo->commit();
    $_SESSION['success'] = "Le projet a été modifié avec succès.";
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
    header('Location: modify_project.php?id=' . urlencode($project_id));
    exit;
}