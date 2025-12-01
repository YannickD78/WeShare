<?php
$page_title = 'Créer un projet';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section class="form-page">
    <h1>Créer un nouveau projet</h1>
    <p>Utilisez ce formulaire pour créer un projet de colocation, de cours ou de voyage et répartir les tâches.</p>

    <form method="post" action="save_project.php" class="form-block">
        <div class="form-group">
            <label for="project_name">Nom du projet</label>
            <input type="text" id="project_name" name="project_name" required>
        </div>

        <div class="form-group">
            <label for="project_description">Description (facultatif)</label>
            <textarea id="project_description" name="project_description" rows="3"
                placeholder="Ex : Organisation des tâches ménagères de l’appartement..."></textarea>
        </div>


        <h2>Membres invités</h2>
        <p class="hint">
            Invitez vos colocataires, camarades de classe ou amis pour le projet.
            Vous êtes automatiquement inclus comme créateur.
        </p>
        
        <?php 
        $user = current_user();
        $user_name = htmlspecialchars($user['name'] ?? '');
        $user_email = htmlspecialchars($user['email'] ?? '');
        ?>

        <!--new things added -->
        <button type="button" class="btn-secondary invite-link-btn" onclick="toggleInviteLink()">
            Afficher le lien d’invitation
        </button>
        <div id="invite-link-box" class="invite-link-box" style="display: none;">
            <p>Partagez ce lien avec les membres que vous avez ajoutés&nbsp;:</p>
            <code>https://weshare.alwaysdata.net/</code>
            <p class="hint">
                Il suffit de copier ce lien et de l’envoyer à vos colocataires / camarades / amis.
                Ils pourront créer un compte ou se connecter, puis voir ce projet dans leur tableau de bord.
            </p>
        </div>

        <div id="members-container">
            <!-- Créateur du projet (non modifiable) -->
            <div class="member-row" style="background: #f0f0f0; padding: 10px; margin-bottom: 10px; border-radius: 4px; opacity: 0.9;">
                <input type="text" name="member_name[]" value="<?php echo $user_name; ?>" readonly style="flex: 1; background: #e8e8e8;">
                <input type="email" name="member_email[]" value="<?php echo $user_email; ?>" readonly style="flex: 1; background: #e8e8e8;">
                <span style="background: #007bff; color: white; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; white-space: nowrap;">Créateur</span>
            </div>
        </div>
        <div id="members-new-container"></div>
        <button type="button" class="btn-secondary" onclick="addNewMemberRow()">+ Ajouter un membre</button>


        <h2>Tâches du projet</h2>
        <p class="hint">
            Créez les tâches et assignez-les à un membre du projet.
            Exemple : "Sortir les poubelles", "Préparer le plan du voyage"...
        </p>

        <div id="tasks-container">
            <div class="task-row">
                <input type="text" name="task_title[]" placeholder="Titre de la tâche">
                <select name="task_assigned_to[]">
                    <option value="">-- Non assigné --</option>
                </select>
                <select name="task_mode[]" class="task-mode-select" title="Mode d'évaluation">
                    <option value="status">Statut</option>
                    <option value="bar">Barre</option>
                </select>
                <button type="button" class="btn-secondary btn-remove" onclick="removeTaskRow(this)">✕ Supprimer</button>
            </div>
        </div>
        <button type="button" class="btn-secondary" onclick="addTaskRow()">+ Ajouter une tâche</button>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Enregistrer le projet</button>
            <a href="dashboard.php" class="btn-link">Annuler</a>
        </div>
    </form>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// Ensure task assignee dropdowns are populated on page load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof updateTaskAssigneeSelects === 'function') {
        updateTaskAssigneeSelects();
    }
});
</script>