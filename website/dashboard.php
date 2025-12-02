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
    <p>Bienvenue sur WeShare. Ici vous pouvez organiser les tâches de colocation, projets d'études, voyages, etc.</p>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="create_project.php" class="btn-primary">+ Créer un nouveau projet</a>
        <a href="weekly_view.php" class="btn-secondary" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; cursor: pointer;">📅 Vue semaine</a>
        <a href="stats_view.php" class="btn-secondary" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; cursor: pointer;">📊 Statistiques</a>
    </div>
</section>

<!-- Recent Tasks Section -->
<section class="project-section">
    <h2>📋 Mes tâches récentes</h2>
    <?php
    // Collect all tasks from all projects (created + member)
    $all_my_tasks = [];
    foreach (array_merge($created_projects, $member_projects) as $project) {
        foreach ($project['tasks'] as $task) {
            if (strtolower($task['assigned_to']) === $email) {
                $all_my_tasks[] = [
                    'task' => $task,
                    'project' => $project,
                ];
            }
        }
    }
    
    // Sort by creation order (most recent first) - we'll show the 10 most recent
    $recent_tasks = array_slice($all_my_tasks, 0, 10);
    ?>
    
    <?php if (!$recent_tasks): ?>
        <p>Aucune tâche ne vous est assignée pour le moment.</p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
        <?php foreach ($recent_tasks as $item): 
            $task = $item['task'];
            $project = $item['project'];
            $isBarMode = ($task['mode'] ?? 'status') === 'bar';
            $isRecurring = $task['is_recurring'] ?? false;
            
            // For recurring tasks, determine the relevant date (today or next occurrence)
            $relevantDate = null;
            if ($isRecurring) {
                $dayMap = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
                $today = new DateTime();
                $currentDayNum = $today->format('w'); // 0=Sun, 1=Mon, etc.
                
                // Find the next occurrence of this task
                $minDaysAhead = 7;
                $nextOccurrence = null;
                foreach ($task['recurring_days'] ?? [] as $day) {
                    $targetDayNum = $dayMap[$day] ?? null;
                    if ($targetDayNum === null) continue;
                    $daysAhead = ($targetDayNum - $currentDayNum + 7) % 7;
                    if ($daysAhead < $minDaysAhead) {
                        $minDaysAhead = $daysAhead;
                        $nextOccurrence = $daysAhead;
                    }
                }
                
                if ($nextOccurrence !== null) {
                    $relevantDate = new DateTime();
                    $relevantDate->modify("+{$nextOccurrence} days");
                    $relevantDate = $relevantDate->format('Y-m-d');
                }
            }
            
            // Determine completion status using is_task_complete function
            $isCompleted = is_task_complete($task, $relevantDate);
            
            // Get display values (daily values for recurring tasks, global for non-recurring)
            if ($isRecurring && $relevantDate) {
                if ($isBarMode) {
                    $displayProgress = get_daily_progress($task, $relevantDate);
                    $displayStatus = null;
                } else {
                    $displayStatus = get_daily_status($task, $relevantDate);
                    $displayProgress = null;
                }
            } else {
                $displayProgress = $task['progress'] ?? 0;
                $displayStatus = $task['status'] ?? 'todo';
            }
            
            $bgColor = $isCompleted ? '#e8f5e9' : '#f5f5f5';
            $borderColor = $isCompleted ? '#4caf50' : '#ddd';
            $statusIcon = $isCompleted ? '✓' : '→';
            $statusColor = $isCompleted ? '#4caf50' : '#ff9800';
        ?>
            <div style="background: <?= $bgColor ?>; border: 2px solid <?= $borderColor ?>; padding: 15px; border-radius: 8px; display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #333;"><?= htmlspecialchars($task['title']) ?></h4>
                        <small style="color: #666; font-size: 0.85em;">📁 <?= htmlspecialchars($project['name']) ?></small>
                        <?php if ($isRecurring): ?>
                            <br><small style="color: #666; font-size: 0.85em;">📅 <?= htmlspecialchars(format_recurring_days($task['recurring_days'] ?? [])) ?></small>
                            <?php if ($relevantDate): ?>
                                <br><small style="color: #0066cc; font-size: 0.85em; font-weight: bold;">📆 <?= htmlspecialchars(date('d/m/Y', strtotime($relevantDate))) ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <span style="font-size: 1.5em; color: <?= $statusColor ?>;"><?= $statusIcon ?></span>
                </div>
                
                <?php if ($isBarMode): ?>
                    <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                        <div style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: <?= $displayProgress ?>%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75em; font-weight: bold;">
                            <?php if ($displayProgress > 10): ?><?= $displayProgress ?>%<?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="padding: 8px 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; text-align: center; font-weight: 500; color: #333;">
                        <?php 
                        $statusLabels = ['todo' => '⏳ À faire', 'in_progress' => '🔄 En cours', 'done' => '✓ Terminé'];
                        echo $statusLabels[$displayStatus] ?? 'À faire';
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="project-section">
    <h2>Projets que j'ai créés</h2>
    <?php if (!$created_projects): ?>
        <p>Vous n’avez pas encore créé de projet.</p>
    <?php else: ?>
        <?php foreach ($created_projects as $project): ?>
            <article class="project-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; gap: 10px;">
                    <h3 style="margin: 0; flex: 1;"><?= htmlspecialchars($project['name']) ?></h3>
                    <div style="display: flex; gap: 6px; flex-shrink: 0;">
                        <?php if (($project['status'] ?? 'active') !== 'done'): ?>
                            <a href="modify_project.php?id=<?= htmlspecialchars($project['id']) ?>" 
                               class="btn-secondary" 
                               style="display: inline-block; padding: 6px 12px; font-size: 0.85em;">
                                ✏️ Modifier
                            </a>
                            <form method="post" action="cloturer_project.php" style="margin:0; display: inline;">
                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                <button type="submit" class="btn-secondary"
                                    onclick="return confirm('Êtes-vous sûr de vouloir clôturer ce projet ?')"
                                    style="display:inline-block; padding: 6px 12px; font-size: 0.85em;">
                                    ✓ Clôturer
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color: green; font-weight: bold; padding: 6px 12px; font-size: 0.85em;">✓ Terminé</span>
                            <form method="post" action="reactiver_project.php" style="margin:0; display: inline;">
                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                <button type="submit" class="btn-secondary"
                                    style="display:inline-block; background-color: #1e90ff; color: white; padding: 6px 12px; font-size: 0.85em;">
                                    🔄 Réactiver
                                </button>
                            </form>
                            <form method="post" action="delete_project.php" style="margin:0; display: inline;">
                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                <button type="submit" class="btn-secondary"
                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce projet ?')"
                                    style="display:inline-block; background-color: #e74c3c; color: white; padding: 6px 12px; font-size: 0.85em;">
                                    🗑️ Supprimer
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
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
                            // Check if this member has accepted (is in their member_projects)
                            $member_has_accepted = false;
                            if (!$is_creator_member) {
                                foreach ($projects as $p) {
                                    if ($p['id'] === $project['id']) {
                                        // Check if member is in this project's members list and it's in THEIR member_projects
                                        foreach ($p['members'] as $m) {
                                            if (strtolower($m['email']) === strtolower($member['email'])) {
                                                // Now check if this member would see this project as a member project
                                                // by checking if they're NOT the creator
                                                if (strtolower($p['creator_email']) !== strtolower($member['email'])) {
                                                    $member_has_accepted = true;
                                                }
                                                break;
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                            ?>
                            <li>
                                <?= htmlspecialchars($member['name']) ?> - <?= htmlspecialchars($member['email']) ?>
                                <?php if (!$is_creator_member && !$member_has_accepted): ?>
                                    <span class="badge badge-pending">Invitation en attente</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>



                <h4>Tâches</h4>
                <?php if (empty($project['tasks'])): ?>
                    <div class="tasks-toggle">
                        <button class="btn-toggle active" data-project="<?= htmlspecialchars($project['id']) ?>" data-view="mine" onclick="toggleTasksView('<?= htmlspecialchars($project['id']) ?>')">Mes tâches</button>
                        <button class="btn-toggle" data-project="<?= htmlspecialchars($project['id']) ?>" data-view="all" onclick="toggleTasksView('<?= htmlspecialchars($project['id']) ?>')">Toutes les tâches</button>
                    </div>
                    <div class="table-wrapper" data-project="<?= htmlspecialchars($project['id']) ?>" data-table="mine">
                        <p>Ce projet n'a pas encore de tâche.</p>
                    </div>
                    <div class="table-wrapper" data-project="<?= htmlspecialchars($project['id']) ?>" data-table="all" style="display: none;">
                        <p>Ce projet n'a pas encore de tâche.</p>
                    </div>
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
                                        <?php $is_my_task = (strtolower($task['assigned_to']) === $email); ?>
                                        <?php if (strtolower($task['assigned_to']) === $email): ?>
                                            <?php
                                            // For recurring tasks, get the relevant date and display values
                                            $relevantDate = null;
                                            $displayStatus = $task['status'] ?? 'todo';
                                            $displayProgress = $task['progress'] ?? 0;
                                            $taskIsCompleted = is_task_complete($task);
                                            
                                            if ($task['is_recurring'] ?? false) {
                                                $relevantDate = get_relevant_date_for_recurring_task($task);
                                                if ($relevantDate) {
                                                    $taskIsCompleted = is_task_complete($task, $relevantDate);
                                                    if (($task['mode'] ?? 'status') === 'bar') {
                                                        $displayProgress = get_daily_progress($task, $relevantDate);
                                                    } else {
                                                        $displayStatus = get_daily_status($task, $relevantDate);
                                                    }
                                                }
                                            }
                                            
                                            $bgColor = $taskIsCompleted ? '#e8f5e9' : 'transparent';
                                            ?>
                                            <tr class="my-task <?= $taskIsCompleted ? 'task-completed' : '' ?>" data-task-id="<?= htmlspecialchars($task['id']) ?>" data-project-id="<?= htmlspecialchars($project['id']) ?>" data-task-mode="<?= htmlspecialchars($task['mode'] ?? 'status') ?>" style="background-color: <?= $bgColor ?>;">
                                                <td>
                                                    <?= htmlspecialchars($task['title']) ?>
                                                    <?php if ($task['is_recurring'] ?? false): ?>
                                                        <br><small style="color: #666; font-size: 0.85em;">📅 <?= htmlspecialchars(format_recurring_days($task['recurring_days'] ?? [])) ?></small>
                                                        <?php if ($relevantDate): ?>
                                                            <br><small style="color: #0066cc; font-size: 0.85em; font-weight: bold;">📆 <?= htmlspecialchars(date('d/m', strtotime($relevantDate))) ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                                <td>
                                                    <?php if ($task['is_recurring'] ?? false): ?>
                                                        <!-- Recurring task: show day selector -->
                                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                                            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                                                <?php 
                                                                $dayNames = ['mon' => 'Lun', 'tue' => 'Mar', 'wed' => 'Mer', 'thu' => 'Jeu', 'fri' => 'Ven', 'sat' => 'Sam', 'sun' => 'Dim'];
                                                                foreach ($task['recurring_days'] ?? [] as $day):
                                                                    ?>
                                                                    <button class="day-btn" data-day="<?= htmlspecialchars($day) ?>" onclick="selectTaskDay(this, '<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>', '<?= htmlspecialchars($task['mode'] ?? 'status') ?>')" style="padding: 6px 10px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: bold;">
                                                                        <?= $dayNames[$day] ?>
                                                                    </button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="task-day-content" style="display: none;">
                                                                <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                                    <div class="progress-bar">
                                                                        <div class="progress-fill" style="width: 0%"></div>
                                                                        <span class="progress-text">0%</span>
                                                                    </div>
                                                                    <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                        <input type="hidden" name="date" value="">
                                                                        <input type="range" name="progress" min="0" max="100" value="0" 
                                                                               class="progress-slider" onchange="<?= $is_done ? 'return false;' : 'return updateTaskAjax(this.form)' ?>" style="width: 100%; cursor: <?= $is_done ? 'default' : 'pointer' ?>;" <?= $is_done ? 'disabled' : '' ?>>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <form method="post" action="update_task.php">
                                                                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                        <input type="hidden" name="date" value="">
                                                                        <select name="status" onchange="<?= $is_done ? 'return false;' : 'return updateTaskAjax(this.form)' ?>" <?= $is_done ? 'disabled' : '' ?>>
                                                                            <option value="todo">À faire</option>
                                                                            <option value="in_progress">En cours</option>
                                                                            <option value="done">Terminé</option>
                                                                        </select>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Non-recurring task -->
                                                        <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                            <div class="progress-bar">
                                                                <div class="progress-fill" style="width: <?= $displayProgress ?>%"></div>
                                                                <span class="progress-text"><?= $displayProgress ?>%</span>
                                                            </div>
                                                            <?php if (!$is_done): ?>
                                                                <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                    <input type="range" name="progress" min="0" max="100" value="<?= $displayProgress ?>" 
                                                                           class="progress-slider" onchange="return updateTaskAjax(this.form)" style="width: 100%; cursor: pointer;">
                                                                </form>
                                                            <?php else: ?>
                                                                <button type="button" class="btn-delete" onclick="return deleteTaskAjax('<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>')" style="margin-top: 8px; padding: 4px 8px; background: #e74c3c; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.85rem;">🗑️ Supprimer</button>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <?php if ($is_my_task && !$is_done): ?>
                                                                <form method="post" action="update_task.php">
                                                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                    <select name="status" onchange="return updateTaskAjax(this.form)">
                                                                        <option value="todo" <?= $displayStatus === 'todo' ? 'selected' : '' ?>>À faire</option>
                                                                        <option value="in_progress" <?= $displayStatus === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                                                        <option value="done" <?= $displayStatus === 'done' ? 'selected' : '' ?>>Terminé</option>
                                                                    </select>
                                                                </form>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars(status_label($displayStatus)) ?>
                                                                <?php if ($taskIsCompleted): ?>
                                                                    <button type="button" class="btn-delete" onclick="return deleteTaskAjax('<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>')" style="margin-left: 8px; padding: 4px 8px; background: #e74c3c; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.85rem;">🗑️</button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
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
                                    <?php 
                                    $is_my_task = (strtolower($task['assigned_to']) === $email);
                                    
                                    // For recurring tasks, get the relevant date and display values
                                    $relevantDate = null;
                                    $displayStatus = $task['status'] ?? 'todo';
                                    $displayProgress = $task['progress'] ?? 0;
                                    $taskIsCompleted = is_task_complete($task);
                                    
                                    if ($task['is_recurring'] ?? false) {
                                        $relevantDate = get_relevant_date_for_recurring_task($task);
                                        if ($relevantDate) {
                                            $taskIsCompleted = is_task_complete($task, $relevantDate);
                                            if (($task['mode'] ?? 'status') === 'bar') {
                                                $displayProgress = get_daily_progress($task, $relevantDate);
                                            } else {
                                                $displayStatus = get_daily_status($task, $relevantDate);
                                            }
                                        }
                                    }
                                    
                                    $bgColor = $taskIsCompleted ? '#e8f5e9' : 'transparent';
                                    ?>
                                    <tr class="<?= $is_my_task ? 'my-task' : '' ?> <?= $taskIsCompleted ? 'task-completed' : '' ?>" data-task-id="<?= htmlspecialchars($task['id']) ?>" data-project-id="<?= htmlspecialchars($project['id']) ?>" data-task-mode="<?= htmlspecialchars($task['mode'] ?? 'status') ?>" style="background-color: <?= $bgColor ?>;">
                                        <td>
                                            <?= htmlspecialchars($task['title']) ?>
                                            <?php if ($task['is_recurring'] ?? false): ?>
                                                <br><small style="color: #666; font-size: 0.85em;">📅 <?= htmlspecialchars(format_recurring_days($task['recurring_days'] ?? [])) ?></small>
                                                <?php if ($relevantDate): ?>
                                                    <br><small style="color: #0066cc; font-size: 0.85em; font-weight: bold;">📆 <?= htmlspecialchars(date('d/m', strtotime($relevantDate))) ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                        <td>
                                            <?php if ($task['is_recurring'] ?? false): ?>
                                                <!-- Recurring task: show day selector -->
                                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                                        <?php 
                                                        $dayNames = ['mon' => 'Lun', 'tue' => 'Mar', 'wed' => 'Mer', 'thu' => 'Jeu', 'fri' => 'Ven', 'sat' => 'Sam', 'sun' => 'Dim'];
                                                        foreach ($task['recurring_days'] ?? [] as $day):
                                                            ?>
                                                            <button class="day-btn" data-day="<?= htmlspecialchars($day) ?>" onclick="selectTaskDay(this, '<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>', '<?= htmlspecialchars($task['mode'] ?? 'status') ?>')" style="padding: 6px 10px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: bold;">
                                                                <?= $dayNames[$day] ?>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="task-day-content" style="display: none;">
                                                        <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                            <div class="progress-bar">
                                                                <div class="progress-fill" style="width: 0%"></div>
                                                                <span class="progress-text">0%</span>
                                                            </div>
                                                            <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                <input type="hidden" name="date" value="">
                                                                <input type="range" name="progress" min="0" max="100" value="0" 
                                                                       class="progress-slider" onchange="<?= $is_my_task && !$is_done ? 'return updateTaskAjax(this.form)' : 'return false;' ?>" style="width: 100%; cursor: <?= $is_my_task && !$is_done ? 'pointer' : 'default' ?>;" <?= (!$is_my_task || $is_done) ? 'disabled' : '' ?>>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="post" action="update_task.php">
                                                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                <input type="hidden" name="date" value="">
                                                                <select name="status" onchange="<?= $is_my_task && !$is_done ? 'return updateTaskAjax(this.form)' : 'return false;' ?>" <?= (!$is_my_task || $is_done) ? 'disabled' : '' ?>>
                                                                    <option value="todo">À faire</option>
                                                                    <option value="in_progress">En cours</option>
                                                                    <option value="done">Terminé</option>
                                                                </select>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <!-- Non-recurring task -->
                                                <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?= $displayProgress ?>%"></div>
                                                        <span class="progress-text"><?= $displayProgress ?>%</span>
                                                    </div>
                                                    <?php if ($is_my_task && !$is_done): ?>
                                                        <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                            <input type="range" name="progress" min="0" max="100" value="<?= $displayProgress ?>" 
                                                                   class="progress-slider" onchange="return updateTaskAjax(this.form)" style="width: 100%; cursor: pointer;">
                                                        </form>
                                                    <?php elseif ($is_my_task && $taskIsCompleted): ?>
                                                        <button type="button" class="btn-delete" onclick="return deleteTaskAjax('<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>')" style="margin-top: 8px; padding: 4px 8px; background: #e74c3c; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.85rem;">🗑️ Supprimer</button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if ($is_my_task && !$is_done): ?>
                                                        <form method="post" action="update_task.php">
                                                            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                            <select name="status" onchange="return updateTaskAjax(this.form)">
                                                                <option value="todo" <?= $displayStatus === 'todo' ? 'selected' : '' ?>>À faire</option>
                                                                <option value="in_progress" <?= $displayStatus === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                                                <option value="done" <?= $displayStatus === 'done' ? 'selected' : '' ?>>Terminé</option>
                                                            </select>
                                                        </form>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars(status_label($displayStatus)) ?>
                                                        <?php if ($taskIsCompleted): ?>
                                                            <button type="button" class="btn-delete" onclick="return deleteTaskAjax('<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>')" style="margin-left: 8px; padding: 4px 8px; background: #e74c3c; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.85rem;">🗑️</button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
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
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; gap: 10px;">
                    <h3 style="margin: 0; flex: 1;"><?= htmlspecialchars($project['name']) ?></h3>
                    <div style="display: flex; gap: 6px; flex-shrink: 0;">
                        <?php if (($project['status'] ?? 'active') !== 'done'): ?>
                            <a href="modify_project.php?id=<?= htmlspecialchars($project['id']) ?>" 
                               class="btn-secondary" 
                               style="display: inline-block; padding: 6px 12px; font-size: 0.85em;">
                                ✏️ Modifier
                            </a>
                        <?php else: ?>
                            <span style="color: green; font-weight: bold; padding: 6px 12px; font-size: 0.85em;">✓ Terminé</span>
                            <?php if (strtolower($project['creator_email']) === $email): ?>
                                <form method="post" action="reactiver_project.php" style="margin:0; display: inline;">
                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                    <button type="submit" class="btn-secondary"
                                        style="display:inline-block; background-color: #1e90ff; color: white; padding: 6px 12px; font-size: 0.85em;">
                                        🔄 Réactiver
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
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
                                <?php foreach ($my_tasks as $task): 
                                    // For recurring tasks, get the relevant date and display values
                                    $is_my_task = true; // We're already filtering by $my_tasks, so this is always true
                                    $relevantDate = null;
                                    $displayStatus = $task['status'] ?? 'todo';
                                    $displayProgress = $task['progress'] ?? 0;
                                    $taskIsCompleted = is_task_complete($task);
                                    
                                    if ($task['is_recurring'] ?? false) {
                                        $relevantDate = get_relevant_date_for_recurring_task($task);
                                        if ($relevantDate) {
                                            $taskIsCompleted = is_task_complete($task, $relevantDate);
                                            if (($task['mode'] ?? 'status') === 'bar') {
                                                $displayProgress = get_daily_progress($task, $relevantDate);
                                            } else {
                                                $displayStatus = get_daily_status($task, $relevantDate);
                                            }
                                        }
                                    }
                                    
                                    $bgColor = $taskIsCompleted ? '#e8f5e9' : 'transparent';
                                ?>
                                    <tr class="my-task <?= $taskIsCompleted ? 'task-completed' : '' ?>" data-task-id="<?= htmlspecialchars($task['id']) ?>" data-project-id="<?= htmlspecialchars($project['id']) ?>" data-task-mode="<?= htmlspecialchars($task['mode'] ?? 'status') ?>" style="background-color: <?= $bgColor ?>;">
                                        <td>
                                            <?= htmlspecialchars($task['title']) ?>
                                            <?php if ($task['is_recurring'] ?? false): ?>
                                                <br><small style="color: #666; font-size: 0.85em;">📅 <?= htmlspecialchars(format_recurring_days($task['recurring_days'] ?? [])) ?></small>
                                                <?php if ($relevantDate): ?>
                                                    <br><small style="color: #0066cc; font-size: 0.85em; font-weight: bold;">📆 <?= htmlspecialchars(date('d/m', strtotime($relevantDate))) ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                        <td>
                                            <?php if ($task['is_recurring'] ?? false): ?>
                                                <!-- Recurring task: show day selector -->
                                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                                        <?php 
                                                        $dayNames = ['mon' => 'Lun', 'tue' => 'Mar', 'wed' => 'Mer', 'thu' => 'Jeu', 'fri' => 'Ven', 'sat' => 'Sam', 'sun' => 'Dim'];
                                                        foreach ($task['recurring_days'] ?? [] as $day):
                                                            ?>
                                                            <button class="day-btn" data-day="<?= htmlspecialchars($day) ?>" onclick="selectTaskDay(this, '<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>', '<?= htmlspecialchars($task['mode'] ?? 'status') ?>')" style="padding: 6px 10px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 0.85em; font-weight: bold;">
                                                                <?= $dayNames[$day] ?>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="task-day-content" style="display: none;">
                                                        <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                            <div class="progress-bar">
                                                                <div class="progress-fill" style="width: 0%"></div>
                                                                <span class="progress-text">0%</span>
                                                            </div>
                                                            <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                <input type="hidden" name="date" value="">
                                                                <input type="range" name="progress" min="0" max="100" value="0" 
                                                                       class="progress-slider" onchange="<?= !$is_done ? 'return updateTaskAjax(this.form)' : 'return false;' ?>" style="width: 100%; cursor: <?= !$is_done ? 'pointer' : 'default' ?>;" <?= $is_done ? 'disabled' : '' ?>>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="post" action="update_task.php">
                                                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                                <input type="hidden" name="date" value="">
                                                                <select name="status" onchange="<?= !$is_done ? 'return updateTaskAjax(this.form)' : 'return false;' ?>" <?= $is_done ? 'disabled' : '' ?>>
                                                                    <option value="todo">À faire</option>
                                                                    <option value="in_progress">En cours</option>
                                                                    <option value="done">Terminé</option>
                                                                </select>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <!-- Non-recurring task -->
                                                <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?= $displayProgress ?>%"></div>
                                                        <span class="progress-text"><?= $displayProgress ?>%</span>
                                                    </div>
                                                    <?php if (!$is_done): ?>
                                                        <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                            <input type="range" name="progress" min="0" max="100" value="<?= $displayProgress ?>" 
                                                                   class="progress-slider" onchange="return updateTaskAjax(this.form)" style="width: 100%; cursor: pointer;">
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if (!$is_done): ?>
                                                        <form method="post" action="update_task.php">
                                                            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                            <select name="status" onchange="return updateTaskAjax(this.form)">
                                                                <option value="todo" <?= $displayStatus === 'todo' ? 'selected' : '' ?>>À faire</option>
                                                                <option value="in_progress" <?= $displayStatus === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                                                <option value="done" <?= $displayStatus === 'done' ? 'selected' : '' ?>>Terminé</option>
                                                            </select>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
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
                            <?php foreach ($project['tasks'] as $task): 
                                $is_my_task = (strtolower($task['assigned_to']) === $email);
                                
                                // For recurring tasks, get the relevant date and display values
                                $relevantDate = null;
                                $displayStatus = $task['status'] ?? 'todo';
                                $displayProgress = $task['progress'] ?? 0;
                                $taskIsCompleted = is_task_complete($task);
                                
                                if ($task['is_recurring'] ?? false) {
                                    $relevantDate = get_relevant_date_for_recurring_task($task);
                                    if ($relevantDate) {
                                        $taskIsCompleted = is_task_complete($task, $relevantDate);
                                        if (($task['mode'] ?? 'status') === 'bar') {
                                            $displayProgress = get_daily_progress($task, $relevantDate);
                                        } else {
                                            $displayStatus = get_daily_status($task, $relevantDate);
                                        }
                                    }
                                }
                                
                                $bgColor = $taskIsCompleted ? '#e8f5e9' : 'transparent';
                            ?>
                                <tr class="<?= $is_my_task ? 'my-task' : '' ?> <?= $taskIsCompleted ? 'task-completed' : '' ?>" data-task-id="<?= htmlspecialchars($task['id']) ?>" data-project-id="<?= htmlspecialchars($project['id']) ?>" data-task-mode="<?= htmlspecialchars($task['mode'] ?? 'status') ?>" style="background-color: <?= $bgColor ?>;">
                                    <td>
                                        <?= htmlspecialchars($task['title']) ?>
                                        <?php if ($task['is_recurring'] ?? false): ?>
                                            <br><small style="color: #666; font-size: 0.85em;">📅 <?= htmlspecialchars(format_recurring_days($task['recurring_days'] ?? [])) ?></small>
                                            <?php if ($relevantDate): ?>
                                                <br><small style="color: #0066cc; font-size: 0.85em; font-weight: bold;">📆 <?= htmlspecialchars(date('d/m', strtotime($relevantDate))) ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($task['assigned_to'] ?: 'Non assigné') ?></td>
                                    <td>
                                        <?php if (($task['mode'] ?? 'status') === 'bar'): ?>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $displayProgress ?>%"></div>
                                                <span class="progress-text"><?= $displayProgress ?>%</span>
                                            </div>
                                            <?php if ($is_my_task && !$is_done): ?>
                                                <form method="post" action="update_task.php" style="margin-top: 8px;">
                                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                    <input type="range" name="progress" min="0" max="100" value="<?= $displayProgress ?>" 
                                                           class="progress-slider" onchange="return updateTaskAjax(this.form)" style="width: 100%; cursor: pointer;">
                                                </form>
                                            <?php elseif ($is_my_task && $taskIsCompleted): ?>
                                                <button type="button" class="btn-delete" onclick="return deleteTaskAjax('<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>')" style="margin-top: 8px; padding: 4px 8px; background: #e74c3c; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.85rem;">🗑️ Supprimer</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($is_my_task && !$is_done): ?>
                                                <form method="post" action="update_task.php">
                                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']) ?>">
                                                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                    <select name="status" onchange="return updateTaskAjax(this.form)">
                                                        <option value="todo" <?= $displayStatus === 'todo' ? 'selected' : '' ?>>À faire</option>
                                                        <option value="in_progress" <?= $displayStatus === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                                        <option value="done" <?= $displayStatus === 'done' ? 'selected' : '' ?>>Terminé</option>
                                                    </select>
                                                </form>
                                            <?php else: ?>
                                                <?= htmlspecialchars(status_label($displayStatus)) ?>
                                                <?php if ($taskIsCompleted): ?>
                                                    <button type="button" class="btn-delete" onclick="return deleteTaskAjax('<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>')" style="margin-left: 8px; padding: 4px 8px; background: #e74c3c; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.85rem;">🗑️</button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
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