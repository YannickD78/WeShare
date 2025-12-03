<?php
$page_title = 'Vue jour détaillée';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$email = strtolower($user['email']);

$projects = get_full_user_projects($user['id']);

// Get selected date or use today
$selectedDateStr = $_GET['date'] ?? date('Y-m-d');
try {
    $selectedDate = new DateTime($selectedDateStr);
} catch (Exception $e) {
    $selectedDate = new DateTime('today');
}

$today = new DateTime('today');
$dayOfWeek = (int)$selectedDate->format('N');
$dayShorts = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$shortDay = $dayShorts[$dayOfWeek - 1] ?? 'N/A';

$dayToRecurring = [
    1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu',
    5 => 'fri', 6 => 'sat', 7 => 'sun'
];
$recurringDay = $dayToRecurring[$dayOfWeek] ?? null;

// Collect all tasks for this day
$tasksForDay = [];
$projectsByTask = [];

foreach ($projects as $project) {
    $isMember = false;
    foreach ($project['members'] as $member) {
        if (strtolower($member['email']) === $email) {
            $isMember = true;
            break;
        }
    }
    
    if (!$isMember) continue;
    
    foreach ($project['tasks'] as $task) {
        if (strtolower($task['assigned_to']) !== $email) continue;
        
        $isForToday = false;
        if ($task['is_recurring'] ?? false) {
            if (in_array($recurringDay, $task['recurring_days'] ?? [])) {
                $isForToday = true;
            }
        }
        
        if ($isForToday) {
            $tasksForDay[] = $task;
            $projectsByTask[$task['id']] = $project;
        }
    }
}

// Calculate detailed statistics
$stats = [
    'total' => count($tasksForDay),
    'done' => 0,
    'in_progress' => 0,
    'todo' => 0,
    'bar_tasks' => 0,
    'status_tasks' => 0,
    'avg_progress' => 0,
    'total_progress' => 0,
];

$selectedDateStr = $selectedDate->format('Y-m-d');

foreach ($tasksForDay as $task) {
    $isBarMode = ($task['mode'] ?? 'status') === 'bar';
    
    if ($isBarMode) {
        $stats['bar_tasks']++;
        // For recurring tasks, use daily progress; for non-recurring, use global progress
        $dayProgress = ($task['is_recurring'] ?? false) 
            ? get_daily_progress($task, $selectedDateStr)
            : ($task['progress'] ?? 0);
        $stats['total_progress'] += $dayProgress;
    } else {
        $stats['status_tasks']++;
    }
    
    // For recurring tasks, check daily_progress for this specific date
    // For non-recurring tasks, check the global status/progress
    if (($task['is_recurring'] ?? false)) {
        $dailyProgress = get_daily_progress($task, $selectedDateStr);
        if ($dailyProgress >= 100) {
            $stats['done']++;
        } else if ($dailyProgress >= 50) {
            $stats['in_progress']++;
        } else {
            $stats['todo']++;
        }
    } else {
        // Non-recurring task: count by global status
        $stats[$task['status'] ?? 'todo']++;
    }
}

if ($stats['bar_tasks'] > 0) {
    $stats['avg_progress'] = round($stats['total_progress'] / $stats['bar_tasks']);
}

$completion_percentage = $stats['total'] > 0 ? round(($stats['done'] / $stats['total']) * 100) : 0;
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section style="padding: 20px; max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📆 Détail du jour</h1>
        <a href="weekly_view.php" class="btn-secondary" style="display: inline-block; padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">← Retour Vue semaine</a>
    </div>

    <!-- Date Header -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; text-align: center;">
        <h2 style="margin: 0; font-size: 2em;">
            <?php
            $dayNames = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
            $months = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 
                       7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];
            $dayName = $dayNames[$dayOfWeek - 1] ?? 'Jour inconnu';
            $monthName = $months[(int)$selectedDate->format('m')] ?? 'Mois inconnu';
            echo $dayName . ' ' . $selectedDate->format('d') . ' ' . $monthName . ' ' . $selectedDate->format('Y');
            ?>
        </h2>
        <?php if ($selectedDate->format('Y-m-d') === $today->format('Y-m-d')): ?>
            <p style="margin: 10px 0 0 0; font-size: 1.1em; opacity: 0.9;">Aujourd'hui</p>
        <?php endif; ?>
    </div>

    <!-- Key Statistics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 3em; font-weight: bold;"><?= $stats['total'] ?></div>
            <div style="font-size: 1em; opacity: 0.9; margin-top: 5px;">Tâches du jour</div>
        </div>

        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 3em; font-weight: bold;"><?= $completion_percentage ?>%</div>
            <div style="font-size: 1em; opacity: 0.9; margin-top: 5px;">Complétées (<?= $stats['done'] ?>/<?= $stats['total'] ?>)</div>
        </div>

        <?php if ($stats['bar_tasks'] > 0): ?>
        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 3em; font-weight: bold;"><?= $stats['avg_progress'] ?>%</div>
            <div style="font-size: 1em; opacity: 0.9; margin-top: 5px;">Progression moyenne</div>
        </div>
        <?php endif; ?>

        <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 3em; font-weight: bold;"><?= $stats['in_progress'] ?></div>
            <div style="font-size: 1em; opacity: 0.9; margin-top: 5px;">En cours</div>
        </div>
    </div>

    <!-- Task Distribution -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
        <h3>Distribution des statuts</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <?php
            $statusInfo = [
                'todo' => ['label' => 'À faire', 'color' => '#e8f4f8', 'icon' => '📋'],
                'in_progress' => ['label' => 'En cours', 'color' => '#fff4e8', 'icon' => '⚙️'],
                'done' => ['label' => 'Terminées', 'color' => '#e8f8e8', 'icon' => '✓']
            ];
            foreach ($statusInfo as $status => $info):
            ?>
                <div style="background: <?= $info['color'] ?>; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid; border-color: <?= $info['color'] ?>;">
                    <div style="font-size: 2em;"><?= $info['icon'] ?></div>
                    <div style="font-size: 2em; font-weight: bold; margin: 10px 0;"><?= $stats[$status] ?></div>
                    <div style="color: #666; font-size: 0.9em;"><?= $info['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tasks List -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
        <h3>Tâches détaillées</h3>
        
        <?php if (empty($tasksForDay)): ?>
            <p style="color: #999; text-align: center; padding: 40px;">
                ✨ Aucune tâche prévue pour ce jour. Vos tâches hebdomadaires apparaîtront ici.
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 15px;">
                <?php foreach ($tasksForDay as $task):
                    $project = $projectsByTask[$task['id']] ?? [];
                    $isBarMode = ($task['mode'] ?? 'status') === 'bar';
                    $dateForCheck = ($task['is_recurring'] ?? false) ? $selectedDate->format('Y-m-d') : null;
                    $isComplete = is_task_complete($task, $dateForCheck);
                    
                    // Get daily status for recurring tasks, global status for non-recurring
                    $displayStatus = ($task['is_recurring'] ?? false) 
                        ? get_daily_status($task, $selectedDate->format('Y-m-d'))
                        : ($task['status'] ?? 'todo');
                ?>
                    <div style="background: <?= $isComplete ? '#e8f8e8' : '#f9f9f9' ?>; border-left: 5px solid <?= $isComplete ? '#28a745' : '#007bff' ?>; padding: 15px; border-radius: 6px;" data-task-id="<?= htmlspecialchars($task['id']) ?>" data-date="<?= htmlspecialchars($selectedDate->format('Y-m-d')) ?>">
                        <div style="display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start;">
                            <div>
                                <h4 style="margin: 0 0 8px 0; font-size: 1.1em;"><?= htmlspecialchars($task['title']) ?></h4>
                                <p style="margin: 0; color: #666; font-size: 0.9em;">
                                    <strong>Projet:</strong> <?= htmlspecialchars($project['name'] ?? 'N/A') ?>
                                </p>
                                <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9em;">
                                    <strong>Mode:</strong> <?= $isBarMode ? 'Progression' : 'Statut' ?>
                                </p>
                            </div>

                            <div class="task-status-badge" style="background: <?= $isComplete ? '#28a745' : '#007bff' ?>; color: white; padding: 8px 16px; border-radius: 20px; white-space: nowrap; text-align: center;">
                                <strong><?= htmlspecialchars(status_label($displayStatus)) ?></strong>
                            </div>
                        </div>

                        <?php if ($isBarMode): ?>
                            <div style="margin-top: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="font-size: 0.9em; color: #666;">Progression</span>
                                    <strong class="task-progress-text"><?= get_daily_progress($task, $selectedDate->format('Y-m-d')) ?>%</strong>
                                </div>
                                <div style="background: #ddd; height: 30px; border-radius: 15px; overflow: hidden;">
                                    <div class="task-progress-bar" style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: <?= get_daily_progress($task, $selectedDate->format('Y-m-d')) ?>%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        <?php $progress = get_daily_progress($task, $selectedDate->format('Y-m-d')); if ($progress > 15): ?>
                                            <?= $progress ?>%
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php 
                            $dailyProgress = get_daily_progress($task, $selectedDate->format('Y-m-d'));
                            $isDayComplete = $dailyProgress >= 100;
                            if ($isBarMode && !$isDayComplete): ?>
                                <button type="button" class="btn-increment-day" onclick="updateDailyTaskProgress('<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>', '<?= htmlspecialchars($selectedDate->format('Y-m-d')) ?>', 'increment', 10)" style="padding: 6px 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">+10%</button>
                                <button type="button" class="btn-complete-day" onclick="updateDailyTaskProgress('<?= htmlspecialchars($project['id']) ?>', '<?= htmlspecialchars($task['id']) ?>', '<?= htmlspecialchars($selectedDate->format('Y-m-d')) ?>', 'complete')" style="padding: 6px 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">✓ Terminer</button>
                            <?php elseif ($isBarMode && $isDayComplete): ?>
                                <button disabled style="padding: 6px 12px; background: #ccc; color: white; border: none; border-radius: 4px; cursor: not-allowed; font-size: 0.85em;">✓ Complétée (<?= $dailyProgress ?>%)</button>
                            <?php elseif (!$isBarMode && $displayStatus !== 'done'): ?>
                                <?php if ($displayStatus === 'todo'): ?>
                                    <button onclick="updateTaskStatus('<?= htmlspecialchars($project['id'] ?? '') ?>', '<?= htmlspecialchars($task['id']) ?>', 'in_progress', this<?php if ($task['is_recurring'] ?? false): ?>, '<?= htmlspecialchars($selectedDate->format('Y-m-d')) ?>'<?php endif; ?>)" style="padding: 6px 12px; background: #fd7e14; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">▶ Commencer</button>
                                <?php endif; ?>
                                <button onclick="updateTaskStatus('<?= htmlspecialchars($project['id'] ?? '') ?>', '<?= htmlspecialchars($task['id']) ?>', 'done', this<?php if ($task['is_recurring'] ?? false): ?>, '<?= htmlspecialchars($selectedDate->format('Y-m-d')) ?>'<?php endif; ?>)" style="padding: 6px 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">✓ Terminer</button>
                            <?php else: ?>
                                <button disabled style="padding: 6px 12px; background: #ccc; color: white; border: none; border-radius: 4px; cursor: not-allowed; font-size: 0.85em;">✓ Complétée</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation -->
    <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: center;">
        <?php
        $prevDate = (clone $selectedDate)->modify('-1 day');
        $nextDate = (clone $selectedDate)->modify('+1 day');
        ?>
        <a href="day_view.php?date=<?= $prevDate->format('Y-m-d') ?>" class="btn-secondary" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">← Jour précédent</a>
        <a href="day_view.php" class="btn-secondary" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Aujourd'hui</a>
        <a href="day_view.php?date=<?= $nextDate->format('Y-m-d') ?>" class="btn-secondary" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Jour suivant →</a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="assets/app.js"></script>

