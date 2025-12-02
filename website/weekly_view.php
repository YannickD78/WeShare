<?php
$page_title = 'Vue semaine';
require_once __DIR__ . '/includes/functions.php';
require_login();

// Reset completed recurring tasks if needed
reset_daily_tasks();

$user = current_user();
$email = strtolower($user['email']);

$projects = load_projects();

// Get today's date and calculate the range
$today = new DateTime('today');
$dateStart = (new DateTime('today'))->modify('-2 days');
$dateEnd = (new DateTime('today'))->modify('+9 days');

// Get selected date from URL or use today
$selectedDateStr = $_GET['date'] ?? $today->format('Y-m-d');
try {
    $selectedDate = new DateTime($selectedDateStr);
} catch (Exception $e) {
    $selectedDate = $today;
}

// Validate selected date is within range
if ($selectedDate < $dateStart || $selectedDate > $dateEnd) {
    $selectedDate = $today;
}

// Get the day of week (1=Monday, 7=Sunday)
$dayOfWeek = (int)$selectedDate->format('N');
$dayShorts = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$shortDay = $dayShorts[$dayOfWeek - 1] ?? 'N/A';

// Map numeric day to recurring day values
$dayToRecurring = [
    1 => 'mon', // Monday
    2 => 'tue', // Tuesday
    3 => 'wed', // Wednesday
    4 => 'thu', // Thursday
    5 => 'fri', // Friday
    6 => 'sat', // Saturday
    7 => 'sun'  // Sunday
];
$recurringDay = $dayToRecurring[$dayOfWeek] ?? null;

// Collect all tasks for this day from all projects
$tasksForDay = [];

foreach ($projects as $project) {
    // Check if user is member
    $isMember = false;
    foreach ($project['members'] as $member) {
        if (strtolower($member['email']) === $email) {
            $isMember = true;
            break;
        }
    }
    
    if (!$isMember) {
        continue;
    }
    
    // Filter tasks for this day
    foreach ($project['tasks'] as $task) {
        // Only show tasks assigned to current user
        if (strtolower($task['assigned_to']) !== $email) {
            continue;
        }
        
        // Check if task is for this day
        $isForToday = false;
        
        if ($task['is_recurring'] ?? false) {
            // Check if today's day is in recurring days
            if (in_array($recurringDay, $task['recurring_days'] ?? [])) {
                $isForToday = true;
            }
        }
        
        if ($isForToday) {
            $tasksForDay[] = [
                'task' => $task,
                'project' => $project,
                'projectId' => $project['id']
            ];
        }
    }
}

// Sort tasks by status and completion
usort($tasksForDay, function ($a, $b) {
    $taskA = $a['task'];
    $taskB = $b['task'];
    
    $statusOrder = ['todo' => 0, 'in_progress' => 1, 'done' => 2];
    $orderA = $statusOrder[$taskA['status'] ?? 'todo'] ?? 0;
    $orderB = $statusOrder[$taskB['status'] ?? 'todo'] ?? 0;
    
    return $orderA <=> $orderB;
});

// Calculate statistics - IDENTICAL LOGIC TO stats_view.php
$totalTasks = count($tasksForDay);
$doneTasks = 0;
$totalProgress = 0;
$barTasksCount = 0;

// Build daily breakdown for the selected date to count completed tasks properly
$selectedDateStr = $selectedDate->format('Y-m-d');
$dayOfWeekNum = (int)$selectedDate->format('N');
$dayToRecurring = [
    1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu',
    5 => 'fri', 6 => 'sat', 7 => 'sun'
];
$selectedDayAbbr = $dayToRecurring[$dayOfWeekNum] ?? null;

foreach ($tasksForDay as $item) {
    $task = $item['task'];
    $isBarMode = ($task['mode'] ?? 'status') === 'bar';
    
    // For recurring tasks, use daily_progress; for non-recurring use global progress
    if ($isBarMode) {
        if ($task['is_recurring'] ?? false) {
            $dailyProgress = get_daily_progress($task, $selectedDateStr);
            $totalProgress += $dailyProgress;
        } else {
            $totalProgress += $task['progress'] ?? 0;
        }
        $barTasksCount++;
    }
    
    // Check if task is complete for this specific date
    // Using is_task_complete with date for recurring tasks
    $dateForCheck = ($task['is_recurring'] ?? false) ? $selectedDateStr : null;
    if (is_task_complete($task, $dateForCheck)) {
        $doneTasks++;
    }
}

$avgProgress = $barTasksCount > 0 ? round($totalProgress / $barTasksCount) : 0;
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section style="padding: 20px; max-width: 1200px; margin: 0 auto;">
    <h1>📅 Vue semaine</h1>
    
    <!-- Current date info -->
    <div style="background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
        <h2 style="margin: 0; font-size: 1.5em;">
            <?= strtoupper($shortDay) ?> <?= $selectedDate->format('d ') ?>
            <?php 
            $months = [
                1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
                5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
                9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
            ];
            echo $months[(int)$selectedDate->format('m')];
            ?>
            <?= $selectedDate->format('Y') ?>
        </h2>
        <?php if ($selectedDate->format('Y-m-d') === $today->format('Y-m-d')): ?>
            <p style="color: #007bff; font-weight: bold;">Aujourd'hui</p>
        <?php endif; ?>
        <div style="margin-top: 10px;">
            <a href="day_view.php?date=<?= $selectedDate->format('Y-m-d') ?>" class="btn-secondary" style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Vue jour détaillée →</a>
        </div>
    </div>

    <!-- Calendar -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <h3>Semaine</h3>
        <div style="display: flex; gap: 8px; overflow-x: auto; padding: 10px 0;">
            <?php
            $current = clone $dateStart;
            while ($current <= $dateEnd) {
                $date = $current->format('Y-m-d');
                $isSelected = $date === $selectedDate->format('Y-m-d');
                $isToday = $date === $today->format('Y-m-d');
                
                $dayNum = $current->format('d');
                $dayName = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
                $dayShort = $dayName[(int)$current->format('N') - 1];
                
                $bgColor = $isSelected ? '#007bff' : ($isToday ? '#e8f4f8' : '#f9f9f9');
                $textColor = $isSelected ? 'white' : 'black';
                $borderColor = $isToday ? '2px solid #007bff' : '1px solid #ddd';
                
                echo '<a href="?date=' . $date . '" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; padding: 10px 15px; background: ' . $bgColor . '; border: ' . $borderColor . '; border-radius: 6px; cursor: pointer; color: ' . $textColor . '; font-weight: ' . ($isToday ? 'bold' : 'normal') . '; min-width: 70px; text-align: center;">';
                echo '<small>' . strtoupper($dayShort) . '</small>';
                echo '<strong style="font-size: 1.2em;">' . $dayNum . '</strong>';
                echo '</a>';
                
                $current->modify('+1 day');
            }
            ?>
        </div>
    </div>

    <!-- Statistics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold;"><?= $totalTasks ?></div>
            <div style="font-size: 0.9em; opacity: 0.9;">Tâches du jour</div>
        </div>
        
        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold;"><?= $doneTasks ?> / <?= $totalTasks ?></div>
            <div style="font-size: 0.9em; opacity: 0.9;">Complétées</div>
        </div>
        
        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; font-weight: bold;"><?= $avgProgress ?>%</div>
            <div style="font-size: 0.9em; opacity: 0.9;">Progression moyenne</div>
        </div>
    </div>

    <!-- Tasks for the day -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
        <h3>Tâches du <?= strtoupper($shortDay) ?></h3>
        
        <?php if (empty($tasksForDay)): ?>
            <p style="color: #999; text-align: center; padding: 40px 20px;">
                ✨ Aucune tâche prévue pour ce jour. Profitez-en !
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 15px;">
                <?php foreach ($tasksForDay as $item): 
                    $task = $item['task'];
                    $project = $item['project'];
                    $isBarMode = ($task['mode'] ?? 'status') === 'bar';
                    $dateForCheck = ($task['is_recurring'] ?? false) ? $selectedDate->format('Y-m-d') : null;
                    $isComplete = is_task_complete($task, $dateForCheck);
                    $statusColors = [
                        'todo' => '#e8f4f8',
                        'in_progress' => '#fff4e8',
                        'done' => '#e8f8e8'
                    ];
                    $statusBgColor = $statusColors[$task['status'] ?? 'todo'] ?? '#f9f9f9';
                ?>
                    <div style="background: <?= $isComplete ? '#e8f8e8' : $statusBgColor ?>; border-left: 4px solid <?= $isComplete ? '#4caf50' : '#007bff' ?>; padding: 15px; border-radius: 6px;" data-task-id="<?= htmlspecialchars($task['id']) ?>" data-date="<?= htmlspecialchars($selectedDate->format('Y-m-d')) ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                            <div>
                                <h4 style="margin: 0 0 5px 0; font-size: 1.1em;"><?= htmlspecialchars($task['title']) ?></h4>
                                <small style="color: #666;">
                                    Projet: <strong><?= htmlspecialchars($project['name']) ?></strong>
                                </small>
                            </div>
                            <span style="background: <?= $isComplete ? '#4caf50' : '#007bff' ?>; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; white-space: nowrap;">
                                <?php 
                                    $dailyStatus = ($task['is_recurring'] ?? false) ? get_daily_status($task, $selectedDate->format('Y-m-d')) : $task['status'];
                                    echo htmlspecialchars(status_label($dailyStatus));
                                ?>
                            </span>
                        </div>

                        <?php if ($isBarMode): ?>
                            <div style="margin-top: 10px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <small style="color: #666;">Progression</small>
                                    <strong class="task-progress-text"><?= get_daily_progress($task, $selectedDate->format('Y-m-d')) ?>%</strong>
                                </div>
                                <div style="background: #ddd; height: 24px; border-radius: 12px; overflow: hidden;">
                                    <div class="task-progress-bar" style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: <?= get_daily_progress($task, $selectedDate->format('Y-m-d')) ?>%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8em; font-weight: bold;">
                                        <?php 
                                        $dailyProgress = get_daily_progress($task, $selectedDate->format('Y-m-d'));
                                        if ($dailyProgress > 10): ?>
                                            <?= $dailyProgress ?>%
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 12px; display: flex; gap: 10px;">
                            <a href="day_view.php?date=<?= $selectedDate->format('Y-m-d') ?>" class="btn-secondary" style="display: inline-block; padding: 6px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;">Voir jour</a>
                            <?php 
                            $dailyProgress = get_daily_progress($task, $selectedDate->format('Y-m-d'));
                            $isDayComplete = $dailyProgress >= 100;
                            if ($isBarMode && !$isDayComplete): ?>
                                <button type="button" class="btn-increment-day" onclick="updateDailyTaskProgress('<?= htmlspecialchars($item['projectId']) ?>', '<?= htmlspecialchars($task['id']) ?>', '<?= htmlspecialchars($selectedDate->format('Y-m-d')) ?>', 'increment', 25)" style="padding: 6px 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em;">+25%</button>
                            <?php elseif ($isBarMode && $isDayComplete): ?>
                                <button disabled style="padding: 6px 12px; background: #ccc; color: white; border: none; border-radius: 4px; cursor: not-allowed; font-size: 0.9em;">✓ Complétée (<?= $dailyProgress ?>%)</button>
                            <?php elseif (!$isBarMode && !$isComplete): ?>
                                <button class="btn-secondary" onclick="updateTaskStatus('<?= htmlspecialchars($item['projectId']) ?>', '<?= htmlspecialchars($task['id']) ?>', 'done', this<?php if ($task['is_recurring'] ?? false): ?>, '<?= htmlspecialchars($selectedDate->format('Y-m-d')) ?>'<?php endif; ?>)" style="padding: 6px 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em;">✓ Marquer complète</button>
                            <?php elseif ($isComplete): ?>
                                <button disabled style="padding: 6px 12px; background: #ccc; color: white; border: none; border-radius: 4px; cursor: not-allowed; font-size: 0.9em;">✓ Complétée</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Weekly overview -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-top: 20px;">
        <h3>Aperçu de la semaine</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
            <?php
            $weekStart = (new DateTime('today'))->modify('-2 days');
            for ($i = 0; $i < 12; $i++) {
                $date = clone $weekStart;
                $date->modify("+$i days");
                $dateStr = $date->format('Y-m-d');
                $dayNum = $date->format('d');
                $dayName = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
                $dayShort = $dayName[(int)$date->format('N') - 1];
                $isToday = $dateStr === $today->format('Y-m-d');
                
                // Count tasks for this date
                $dayOfWeek = (int)$date->format('N');
                $recurringDay = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'][$dayOfWeek] ?? null;
                
                $tasksCount = 0;
                $doneCount = 0;
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
                        if ($task['is_recurring'] ?? false) {
                            if (in_array($recurringDay, $task['recurring_days'] ?? [])) {
                                $tasksCount++;
                                // For recurring tasks, pass the date to check specific day completion
                                if (is_task_complete($task, $dateStr)) {
                                    $doneCount++;
                                }
                            }
                        }
                    }
                }
                
                $isSelected = $dateStr === $selectedDate->format('Y-m-d');
                $bgColor = $isSelected ? '#007bff' : ($isToday ? '#e8f4f8' : 'white');
                $textColor = $isSelected ? 'white' : 'black';
                $border = $isToday ? '2px solid #007bff' : '1px solid #ddd';
                
                echo '<a href="?date=' . $dateStr . '" style="text-decoration: none; padding: 12px; background: ' . $bgColor . '; border: ' . $border . '; border-radius: 6px; color: ' . $textColor . '; text-align: center; cursor: pointer;">';
                echo '<div style="font-weight: bold; font-size: 0.9em;">' . strtoupper($dayShort) . '</div>';
                echo '<div style="font-size: 1.3em; font-weight: bold;">' . $dayNum . '</div>';
                echo '<div style="font-size: 0.85em; margin-top: 5px;">' . $doneCount . '/' . $tasksCount . '</div>';
                echo '</a>';
            }
            ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
    .btn-secondary {
        background: #6c757d;
        color: white;
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9em;
        text-decoration: none;
        display: inline-block;
    }
    .btn-secondary:hover {
        background: #5a6268;
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="assets/app.js"></script>
