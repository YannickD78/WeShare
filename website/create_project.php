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
            <div class="member-row">
                <input type="text" name="member_name[]" placeholder="Nom du membre" data-temp="true">
                <input type="email" name="member_email[]" placeholder="Email du membre" data-temp="true">
                <button type="button" class="btn-secondary btn-confirm" onclick="confirmMember(this)">✓ Confirmer</button>
            </div>
        </div>
        <button type="button" class="btn-secondary" onclick="addMemberRow()">+ Ajouter un membre</button>


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