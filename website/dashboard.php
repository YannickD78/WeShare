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
usort($created_projects, function($a, $b) {
    return strcmp($a['status'] ?? 'active', $b['status'] ?? 'active');
});

usort($member_projects, function($a, $b) {
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
                <!-- Clôturation -->
                 <?php if (($project['status'] ?? 'active') !== 'done'): ?>
                    <form method="post" action="cloturer_project.php" style="margin-top:8px;">
                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                        <button type="submit" class="btn-secondary"
                                onclick="return confirm('Êtes-vous sûr de vouloir clôturer ce projet ?')">
                            Clôturer le projet
                        </button>
                    </form>
                <?php else: ?>
                    <span style="color: green; font-weight: bold;">(Terminé)</span>
                <?php endif; ?>
                
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

        
                <h4>Tâches</h4>
                <?php if (empty($project['tasks'])): ?>
                    <p>Aucune tâche définie.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tâche</th>
                                    <th>Assigné à</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $is_done = ($project['status'] ?? 'active') === 'done';
                                ?>
                                <?php foreach ($project['tasks'] as $task):
                                    $is_my_task = (strtolower($task['assigned_to']) === $email);
                                    ?>
                                    <tr class="<?= $is_my_task ? 'my-task' : '' ?>">
                                        <td><?= htmlspecialchars($task['title']) ?></td>
                                        <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                        <td>
                                            <?php if ($is_my_task && !$is_done): ?> <!-- si le projet n'est pas terminé, permettre la modification -->
                                                <form method="post" action="update_task.php">
                                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                    <select name="status" onchange="this.form.submit()">
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
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
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
                ?>

                <?php if (!$my_tasks): ?>
                    <p>Aucune tâche ne vous est assignée.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tâche</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_tasks as $task): ?>
                                    <tr class="my-task">
                                        <td><?= htmlspecialchars($task['title']) ?></td>
                                        <td>
                                            <form method="post" action="update_task.php">
                                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                <select name="status" onchange="this.form.submit()">
                                                    <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>À faire
                                                    </option>
                                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>
                                                        En cours</option>
                                                    <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Terminé
                                                    </option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
