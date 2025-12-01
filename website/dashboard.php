<?php
$page_title = 'Mes projets';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$email = strtolower($user['email']);

$projects = load_projects();

$created_projects = [];
$member_projects = [];

foreach ($projects as $project) {
    if (strtolower($project['creator_email']) === $email) {
        $created_projects[] = $project;
        continue;
    }

    $is_member = false;
    foreach ($project['members'] as $member) {
        if (strtolower($member['email']) === $email) {
            $is_member = true;
            break;
        }
    }
    if ($is_member) {
        $member_projects[] = $project;
    }
}

// l'ordre : actifs d'abord
usort($created_projects, function ($a, $b) {
    return strcmp($a['status'] ?? 'active', $b['status'] ?? 'active');
});

usort($member_projects, function ($a, $b) {
    return strcmp($a['status'] ?? 'active', $b['status'] ?? 'active');
});
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section>
    <h1>Mes projets</h1>
    <p>Bienvenue sur WeShare. Ici vous pouvez organiser les tâches de colocation, projets d’études, voyages, etc.</p>

    <a href="create_project.php" class="btn-primary">+ Créer un nouveau projet</a>
</section>

<section class="project-section">
    <h2>Projets que j’ai créés</h2>
    <?php if (!$created_projects): ?>
        <p>Vous n’avez pas encore créé de projet.</p>
    <?php else: ?>
        <?php foreach ($created_projects as $project): ?>
            <article class="project-card">
                <h3><?= htmlspecialchars($project['name']) ?></h3>
                <?php if (!empty($project['description'])): ?>
                    <p class="project-desc"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                <?php endif; ?>

                <p class="project-meta">
                    Créateur : <?= htmlspecialchars($project['creator_name']) ?>
                    (<?= htmlspecialchars($project['creator_email']) ?>)
                </p>

                <h4>Membres</h4>
                <?php if (empty($project['members'])): ?>
                    <p>Aucun membre invité.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($project['members'] as $member):
                            $is_creator_member = (strtolower($member['email']) === strtolower($project['creator_email']));
                            ?>
                            <li>
                                <?= htmlspecialchars($member['name']) ?> - <?= htmlspecialchars($member['email']) ?>
                                <?php if (!$is_creator_member): ?>
                                    <span class="badge badge-pending">Invitation en attente</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>



                <h4>Tâches</h4>
                <?php if (empty($project['tasks'])): ?>
                    <p>Aucune tâche définie.</p>
                <?php else: ?>
                    <div class="tasks-toggle">
                        <button class="btn-toggle active" data-project="<?= htmlspecialchars($project['id']) ?>" data-view="mine" onclick="toggleTasksView('<?= htmlspecialchars($project['id']) ?>')">Mes tâches</button>
                        <button class="btn-toggle" data-project="<?= htmlspecialchars($project['id']) ?>" data-view="all" onclick="toggleTasksView('<?= htmlspecialchars($project['id']) ?>')">Toutes les tâches</button>
                    </div>

                    <?php
                    $is_done = ($project['status'] ?? 'active') === 'done';
                    $my_tasks = array_filter($project['tasks'], function ($task) use ($email) {
                        return strtolower($task['assigned_to']) === $email;
                    });
                    ?>

                    <?php if (!$my_tasks): ?>
                        <p>Aucune tâche ne vous est assignée.</p>
                    <?php else: ?>
                        <div class="table-wrapper" data-project="<?= htmlspecialchars($project['id']) ?>" data-table="mine">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tâche</th>
                                        <th>Assigné à</th>
                                        <th>État</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($project['tasks'] as $task): ?>
                                        <?php if (strtolower($task['assigned_to']) === $email): ?>
                                            <tr class="my-task" data-task-mode="<?= htmlspecialchars($task['mode'] ?? 'status') ?>">
                                                <td><?= htmlspecialchars($task['title']) ?></td>
                                                <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                                <td>
                                                    <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                        <div class="progress-bar">
                                                            <div class="progress-fill" style="width: <?= $task['progress'] ?? 0 ?>%"></div>
                                                            <span class="progress-text"><?= $task['progress'] ?? 0 ?>%</span>
                                                        </div>
                                                        <?php if (!$is_done): ?>
                                                            <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                <input type="range" name="progress" min="0" max="100" value="<?= $task['progress'] ?? 0 ?>" 
                                                                       class="progress-slider" onchange="return updateTaskAjax(this.form)" style="width: 100%; cursor: pointer;">
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if ($is_my_task && !$is_done): ?>
                                                            <form method="post" action="update_task.php">
                                                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                <select name="status" onchange="return updateTaskAjax(this.form)">
                                                                    <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>À faire</option>
                                                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                                                    <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Terminé</option>
                                                                </select>
                                                            </form>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(status_label($task['status'])) ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="table-wrapper" data-project="<?= htmlspecialchars($project['id']) ?>" data-table="all" style="display: none;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tâche</th>
                                    <th>Assigné à</th>
                                    <th>État</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($project['tasks'] as $task): ?>
                                    <?php $is_my_task = (strtolower($task['assigned_to']) === $email); ?>
                                    <tr class="<?= $is_my_task ? 'my-task' : '' ?>" data-task-mode="<?= htmlspecialchars($task['mode'] ?? 'status') ?>">
                                        <td><?= htmlspecialchars($task['title']) ?></td>
                                        <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                        <td>
                                            <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?= $task['progress'] ?? 0 ?>%"></div>
                                                    <span class="progress-text"><?= $task['progress'] ?? 0 ?>%</span>
                                                </div>
                                                <?php if ($is_my_task && !$is_done): ?>
                                                    <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                        <input type="range" name="progress" min="0" max="100" value="<?= $task['progress'] ?? 0 ?>" 
                                                               class="progress-slider" onchange="this.form.submit()" style="width: 100%; cursor: pointer;">
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($is_my_task && !$is_done): ?>
                                                    <form method="post" action="update_task.php">
                                                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                        <select name="status" onchange="this.form.submit()">
                                                            <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>À faire</option>
                                                            <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                                            <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Terminé</option>
                                                        </select>
                                                    </form>
                                                <?php else: ?>
                                                    <?= htmlspecialchars(status_label($task['status'])) ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
                           <?php if (($project['status'] ?? 'active') !== 'done'): ?>
                    <div style="display:flex; gap:10px; margin-top:10px; align-items:center;">
                        <a href="modify_project.php?id=<?= htmlspecialchars($project['id']) ?>" 
                           class="btn-secondary" 
                           style="display:inline-block;">
                            Modifier le projet
                        </a>

                        <form method="post" action="cloturer_project.php" style="margin:0;">
                            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                            <button type="submit" class="btn-secondary"
                                onclick="return confirm('Êtes-vous sûr de vouloir clôturer ce projet ?')"
                                style="display:inline-block;">
                                Clôturer le projet
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="display:flex; gap:10px; margin-top:10px; align-items:center;">
                        <span style="color: green; font-weight: bold;">(Terminé)</span>
                        <form method="post" action="delete_project.php" style="margin:0;">
                            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                            <button type="submit" class="btn-secondary"
                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce projet ?')"
                                style="display:inline-block; background-color: #e74c3c; color: white;">
                                🗑️ Supprimer le projet
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="project-section">
    <h2>Projets où je suis membre</h2>
    <?php if (!$member_projects): ?>
        <p>Vous n’êtes membre d’aucun projet pour l’instant.</p>
    <?php else: ?>
        <?php foreach ($member_projects as $project): ?>
            <article class="project-card">
                
                <h3><?= htmlspecialchars($project['name']) ?></h3>
                <?php if (!empty($project['description'])): ?>
                    <p class="project-desc"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                <?php endif; ?>

                <p class="project-meta">
                    Créateur : <?= htmlspecialchars($project['creator_name']) ?>
                    (<?= htmlspecialchars($project['creator_email']) ?>)
                </p>

                <h4>Membres</h4>
                <?php if (empty($project['members'])): ?>
                    <p>Aucun membre invité.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($project['members'] as $member): ?>
                            <li><?= htmlspecialchars($member['name']) ?> - <?= htmlspecialchars($member['email']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h4>Mes tâches dans ce projet</h4>
                <?php
                $my_tasks = array_filter($project['tasks'], function ($task) use ($email) {
                    return strtolower($task['assigned_to']) === $email;
                });
                $is_done = ($project['status'] ?? 'active') === 'done';
                ?>

                <div class="tasks-toggle">
                    <button class="btn-toggle active" data-project="<?= htmlspecialchars($project['id']) ?>" data-view="mine" onclick="toggleTasksView('<?= htmlspecialchars($project['id']) ?>')">Mes tâches</button>
                    <button class="btn-toggle" data-project="<?= htmlspecialchars($project['id']) ?>" data-view="all" onclick="toggleTasksView('<?= htmlspecialchars($project['id']) ?>')">Toutes les tâches</button>
                </div>

                <?php if (!$my_tasks): ?>
                    <p>Aucune tâche ne vous est assignée.</p>
                <?php else: ?>
                    <div class="table-wrapper" data-project="<?= htmlspecialchars($project['id']) ?>" data-table="mine">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tâche</th>
                                    <th>Assigné à</th>
                                    <th>État</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_tasks as $task): ?>
                                    <tr class="my-task" data-task-mode="<?= htmlspecialchars($task['mode'] ?? 'status') ?>">
                                        <td><?= htmlspecialchars($task['title']) ?></td>
                                        <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                        <td>
                                            <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?= $task['progress'] ?? 0 ?>%"></div>
                                                    <span class="progress-text"><?= $task['progress'] ?? 0 ?>%</span>
                                                </div>
                                                <?php if (!$is_done): ?>
                                                    <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                        <input type="range" name="progress" min="0" max="100" value="<?= $task['progress'] ?? 0 ?>" 
                                                               class="progress-slider" onchange="return updateTaskAjax(this.form)" style="width: 100%; cursor: pointer;">
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <form method="post" action="update_task.php">
                                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                    <select name="status" onchange="return updateTaskAjax(this.form)">
                                                        <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>À faire
                                                        </option>
                                                        <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>
                                                            En cours</option>
                                                        <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Terminé
                                                        </option>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="table-wrapper" data-project="<?= htmlspecialchars($project['id']) ?>" data-table="all" style="display: none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Tâche</th>
                                <th>Assigné à</th>
                                <th>Évaluation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($project['tasks'] as $task): ?>
                                <?php $is_my_task = (strtolower($task['assigned_to']) === $email); ?>
                                <tr class="<?= $is_my_task ? 'my-task' : '' ?>" data-task-mode="<?= htmlspecialchars($task['mode'] ?? 'status') ?>">
                                    <td><?= htmlspecialchars($task['title']) ?></td>
                                    <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                    <td>
                                        <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $task['progress'] ?? 0 ?>%"></div>
                                                <span class="progress-text"><?= $task['progress'] ?? 0 ?>%</span>
                                            </div>
                                            <?php if ($is_my_task && !$is_done): ?>
                                                <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                    <input type="range" name="progress" min="0" max="100" value="<?= $task['progress'] ?? 0 ?>" 
                                                           class="progress-slider" onchange="return updateTaskAjax(this.form)" style="width: 100%; cursor: pointer;">
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($is_my_task): ?>
                                                <form method="post" action="update_task.php">
                                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                    <select name="status" onchange="return updateTaskAjax(this.form)">
                                                        <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>À faire
                                                        </option>
                                                        <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>
                                                            En cours</option>
                                                        <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Terminé
                                                        </option>
                                                    </select>
                                                </form>
                                            <?php else: ?>
                                                <?= htmlspecialchars(status_label($task['status'])) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
            <?php if (($project['status'] ?? 'active') !== 'done'): ?>
    <div style="display:flex; gap:10px; margin-top:10px; align-items:center;">
        <a href="modify_project.php?id=<?= htmlspecialchars($project['id']) ?>" 
            class="btn-secondary" 
            style="display:inline-block;">
            Modifier le projet
        </a>
    </div>
<?php endif; ?>

        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
    .mode-toggle { display: flex; align-items: center; }
    .mode-btn { 
        padding: 6px 12px; 
        margin-right: 6px; 
        background: #f0f0f0; 
        border: 1px solid #ccc; 
        border-radius: 4px; 
        cursor: pointer; 
        font-size: 0.9rem;
    }
    .mode-btn.active { 
        background: #2d8cff; 
        color: white; 
        border-color: #2d8cff; 
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Apply task mode display logic to all task rows
    // Each row has data-task-mode="status" or "bar"
    // - For status mode: show task-status-cell, hide task-progress-cell
    // - For bar mode: show task-progress-cell, hide task-status-cell
    // This is done via CSS rules, but we ensure row elements are properly set
});

window.toggleTasksView = function(projectId) {
    // Get both table wrappers
    const myTasksWrapper = document.querySelector('.table-wrapper[data-project="' + projectId + '"][data-table="mine"]');
    const allTasksWrapper = document.querySelector('.table-wrapper[data-project="' + projectId + '"][data-table="all"]');
    const myTasksBtn = document.querySelector('.btn-toggle[data-project="' + projectId + '"][data-view="mine"]');
    const allTasksBtn = document.querySelector('.btn-toggle[data-project="' + projectId + '"][data-view="all"]');
    
    if (!myTasksWrapper || !allTasksWrapper) return;
    
    // Check current state of myTasks wrapper
    if (myTasksWrapper.style.display === 'none') {
        // Currently showing all, switch to mine
        myTasksWrapper.style.display = '';
        allTasksWrapper.style.display = 'none';
        if (myTasksBtn) myTasksBtn.classList.add('active');
        if (allTasksBtn) allTasksBtn.classList.remove('active');
    } else {
        // Currently showing mine, switch to all
        myTasksWrapper.style.display = 'none';
        allTasksWrapper.style.display = '';
        if (myTasksBtn) myTasksBtn.classList.remove('active');
        if (allTasksBtn) allTasksBtn.classList.add('active');
    }
};
</script>