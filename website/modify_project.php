<?php
$page_title = 'Modifier un projet';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    $_SESSION['error'] = "ID du projet manquant.";
    header('Location: dashboard.php');
    exit;
}

try {
    // On récupère les infos du projet
    $stmt = $pdo->prepare("SELECT p.*, u.email as creator_email, u.nom as creator_name 
                           FROM projects p 
                           JOIN users u ON p.created_by = u.id 
                           WHERE p.id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();

    if (!$project) {
        $_SESSION['error'] = "Projet introuvable.";
        header('Location: dashboard.php');
        exit;
    }

    // On renomme 'nom' en 'name' pour compatibilité avec le html
    $project['name'] = $project['nom'];

    // On récupère les membres
    $stmt = $pdo->prepare("SELECT u.nom as name, u.email 
                           FROM participants part 
                           JOIN users u ON part.user_id = u.id 
                           WHERE part.project_id = ?");
    $stmt->execute([$project_id]);
    $members = $stmt->fetchAll();
    
    $creatorInList = false;
    foreach($members as $m) {
        if ($m['email'] === $project['creator_email']) $creatorInList = true;
    }
    if (!$creatorInList) {
        array_unshift($members, ['name' => $project['creator_name'], 'email' => $project['creator_email']]);
    }
    $project['members'] = $members;

    // On vérifie les droits (le user est-il membre ?)
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

    // On récupère les taches
    $stmt = $pdo->prepare("SELECT t.*, u.email as assigned_email 
                           FROM tasks t 
                           LEFT JOIN users u ON t.assigned_to = u.id 
                           WHERE t.project_id = ? 
                           ORDER BY t.id ASC");
    $stmt->execute([$project_id]);
    $dbTasks = $stmt->fetchAll();

    $tasks = [];
    foreach ($dbTasks as $t) {
        $tasks[] = [
            'id' => $t['id'],
            'title' => $t['titre'],
            'assigned_to' => $t['assigned_email'] ?? '', // Pour le select
            'mode' => $t['mode'],
            'is_recurring' => (bool)$t['is_recurring'],
            'recurring_days' => json_decode($t['recurring_days'] ?? '[]', true)
        ];
    }
    $project['tasks'] = $tasks;

    $is_creator = ($project['created_by'] == $user['id']);

} catch (Exception $e) {
    $_SESSION['error'] = "Erreur de base de données.";
    header('Location: dashboard.php');
    exit;
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section class="form-page">
    <h1>Modifier le projet : <?= htmlspecialchars($project['name']) ?></h1>
    <p>Modifiez les informations, les membres et les tâches du projet.</p>

    <form method="post" action="update_project.php" class="form-block">
        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">

        <div class="form-group">
            <label for="project_name">Nom du projet</label>
            <input type="text" id="project_name" name="project_name" value="<?= htmlspecialchars($project['name']) ?>" required>
        </div>

        <div class="form-group">
            <label for="project_description">Description (facultatif)</label>
            <textarea id="project_description" name="project_description" rows="3"
                placeholder="Ex : Organisation des tâches ménagères de l'appartement..."><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
        </div>

        <h2>Membres du projet</h2>
        <p class="hint">
            Gérez les membres du projet<?php if ($is_creator): ?>. Vous êtes le créateur et ne pouvez pas être supprimé<?php endif; ?>.
        </p>
        <button type="button" class="btn-secondary invite-link-btn" onclick="toggleInviteLink()">
            Afficher le lien d'invitation
        </button>
        <div id="invite-link-box" class="invite-link-box" style="display: none;">
            <p>Partagez ce lien avec les membres que vous avez ajoutés&nbsp;:</p>
            <code id="invite-link-code" style="cursor: pointer; padding: 8px 12px; background: #f5f5f5; display: inline-block; border-radius: 4px; border: 1px solid #ddd; user-select: all; transition: all 0.2s;" onmouseover="this.style.background='#e8e8e8'; this.style.borderColor='#999';" onmouseout="this.style.background='#f5f5f5'; this.style.borderColor='#ddd';" onclick="copyInviteLink(this)">https://weshare.alwaysdata.net/?project_id=<?= htmlspecialchars($project_id) ?></code>
            <p id="copy-feedback" style="display: none; color: #4caf50; font-weight: bold; margin-top: 8px;">✓ Lien copié !</p>
            <p class="hint">
                Cliquez sur le lien pour le copier, puis envoyez-le à vos colocataires / camarades / amis.
                Ils pourront créer un compte ou se connecter, puis voir ce projet dans leur tableau de bord.
            </p>
        </div>

        <div id="members-container">
            <?php foreach ($project['members'] as $member): ?>
                <div class="member-row">
                    <input type="text" name="member_name[]" placeholder="Nom du membre" value="<?= htmlspecialchars($member['name']) ?>">
                    <input type="email" name="member_email[]" placeholder="Email du membre" value="<?= htmlspecialchars($member['email']) ?>">
                    <?php if (strtolower($member['email']) !== strtolower($project['creator_email'])): ?>
                        <button type="button" class="btn-secondary btn-remove" onclick="removeMemberRow(this)">✕ Supprimer</button>
                    <?php else: ?>
                        <span class="badge badge-creator">Créateur</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-secondary" onclick="addMemberRow()">+ Ajouter un membre</button>


        <h2>Tâches du projet</h2>
        <p class="hint">
            Créez, modifiez ou supprimez les tâches du projet.
        </p>

        <div id="tasks-container">
            <?php foreach ($project['tasks'] as $taskIndex => $task): ?>
                <div class="task-row">
                    <input type="text" name="task_title[]" placeholder="Titre de la tâche" value="<?= htmlspecialchars($task['title']) ?>">
                    <select name="task_assigned_to[]">
                        <option value="">-- Non assigné --</option>
                        <?php foreach ($project['members'] as $member): ?>
                            <option value="<?= htmlspecialchars($member['email']) ?>" <?= strtolower($task['assigned_to'] ?? '') === strtolower($member['email']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($member['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="task_mode[]" class="task-mode-select" title="Mode d'évaluation">
                        <option value="status" <?= ($task['mode'] ?? 'status') === 'status' ? 'selected' : '' ?>>Statut</option>
                        <option value="bar" <?= ($task['mode'] ?? 'status') === 'bar' ? 'selected' : '' ?>>Barre</option>
                    </select>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" name="task_recurring[]" class="task-recurring-check" <?= ($task['is_recurring'] ?? false) ? 'checked' : '' ?> onchange="toggleRecurringDays(this)">
                        <span>Hebdomadaire</span>
                    </label>
                    <input type="hidden" name="task_id[]" value="<?= htmlspecialchars($task['id']) ?>">
                    <button type="button" class="btn-secondary btn-remove" onclick="removeTaskRow(this)">✕ Supprimer</button>
                    <?php if ($task['is_recurring'] ?? false): ?>
                        <div class="recurring-days-container" style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; padding: 8px; background: #f9f9f9; border-radius: 4px; width: 100%;">
                            <?php 
                            $days = [
                                ['label' => 'Lun', 'value' => 'mon'],
                                ['label' => 'Mar', 'value' => 'tue'],
                                ['label' => 'Mer', 'value' => 'wed'],
                                ['label' => 'Jeu', 'value' => 'thu'],
                                ['label' => 'Ven', 'value' => 'fri'],
                                ['label' => 'Sam', 'value' => 'sat'],
                                ['label' => 'Dim', 'value' => 'sun']
                            ];
                            $recurring_days = $task['recurring_days'] ?? [];
                            foreach ($days as $day):
                            ?>
                                <label style="display: flex; align-items: center; gap: 4px; cursor: pointer;">
                                    <input type="checkbox" name="task_recurring_days[<?= $taskIndex ?>][]" value="<?= htmlspecialchars($day['value']) ?>" <?= in_array($day['value'], $recurring_days) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($day['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-secondary" onclick="addTaskRow()">+ Ajouter une tâche</button>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Enregistrer les modifications</button>
            <a href="dashboard.php" class="btn-link">Annuler</a>
        </div>
    </form>

    <?php if ($is_creator): ?>
        <form method="post" action="delete_project.php" style="display:inline; margin-top: 16px;">
            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
            <button type="submit" class="btn-secondary"
                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce projet ?')"
                style="background-color: #e74c3c; color: white;">
                🗑️ Supprimer le projet
            </button>
        </form>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
