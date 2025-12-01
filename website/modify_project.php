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

$projects = load_projects();
$project = null;

foreach ($projects as $p) {
    if ($p['id'] === $project_id) {
        $project = $p;
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

$is_creator = (strtolower($project['creator_email']) === $user_email);
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
            <code>https://weshare.alwaysdata.net/</code>
            <p class="hint">
                Il suffit de copier ce lien et de l'envoyer à vos colocataires / camarades / amis.
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
            <?php foreach ($project['tasks'] as $task): ?>
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
                    <input type="hidden" name="task_id[]" value="<?= htmlspecialchars($task['id']) ?>">
                    <button type="button" class="btn-secondary btn-remove" onclick="removeTaskRow(this)">✕ Supprimer</button>
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
