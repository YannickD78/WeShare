<?php
$page_title = 'Statistiques hebdomadaires';
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$email = strtolower($user['email']);

$projects = get_full_user_projects($user['id']);

// Get today and calculate week
$today = new DateTime('today');
$weekStart = (clone $today)->modify('-' . ($today->format('N') - 1) . ' days'); // Monday of this week
$weekEnd = (clone $weekStart)->modify('+6 days'); // Sunday

// Collect all tasks for the week
$weekTasks = [];
$dailyBreakdown = [];

// Initialize daily breakdown
for ($i = 0; $i < 7; $i++) {
    $date = (clone $weekStart)->modify("+$i days");
    $dateStr = $date->format('Y-m-d');
    $dailyBreakdown[$dateStr] = [
        'total' => 0,
        'done' => 0,
        'in_progress' => 0,
        'todo' => 0,
        'avg_progress' => 0,
        'day_name' => $date->format('l')
    ];
}

$dayToRecurring = [
    1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu',
    5 => 'fri', 6 => 'sat', 7 => 'sun'
];

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
        if (!($task['is_recurring'] ?? false)) continue;
        
        // Add this task to each day it's recurring
        for ($i = 0; $i < 7; $i++) {
            $date = (clone $weekStart)->modify("+$i days");
            $dayOfWeek = (int)$date->format('N');
            $recurringDay = $dayToRecurring[$dayOfWeek] ?? null;
            $dateStr = $date->format('Y-m-d');
            
            if (in_array($recurringDay, $task['recurring_days'] ?? [])) {
                $dailyBreakdown[$dateStr]['total']++;
                
                // Get daily progress for this task on this specific date
                $dailyProgress = get_daily_progress($task, $dateStr);
                
                // Check if complete for this specific day/date
                if ($dailyProgress >= 100) {
                    $dailyBreakdown[$dateStr]['done']++;
                } else if ($dailyProgress >= 50) {
                    $dailyBreakdown[$dateStr]['in_progress']++;
                } else {
                    $dailyBreakdown[$dateStr]['todo']++;
                }
                
                if (($task['mode'] ?? 'status') === 'bar') {
                    $dailyBreakdown[$dateStr]['avg_progress'] += $dailyProgress;
                }
            }
        }
        
        $weekTasks[] = $task;
    }
}

// Calculate week stats
$weekStats = [
    'total' => count($weekTasks),
    'unique_days_with_tasks' => 0,
    'busiest_day' => null,
    'busiest_day_count' => 0,
    'avg_tasks_per_day' => 0,
    'total_completion' => 0,
];

foreach ($dailyBreakdown as $dateStr => $data) {
    if ($data['total'] > 0) {
        $weekStats['unique_days_with_tasks']++;
        if ($data['total'] > $weekStats['busiest_day_count']) {
            $weekStats['busiest_day_count'] = $data['total'];
            $weekStats['busiest_day'] = $dateStr;
        }
    }
    if ($data['total'] > 0 && ($data['avg_progress'] > 0 || $data['done'] > 0)) {
        $progress = $data['total'] > 0 ? round($data['avg_progress'] / max(1, count(array_filter($weekTasks, function($t) use ($data) {
            return ($t['mode'] ?? 'status') === 'bar';
        })))) : 0;
        $weekStats['total_completion'] += $data['done'];
    }
}

if ($weekStats['unique_days_with_tasks'] > 0) {
    $weekStats['avg_tasks_per_day'] = round($weekStats['total'] / 7);
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section style="padding: 20px; max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📊 Statistiques hebdomadaires</h1>
        <a href="weekly_view.php" class="btn-secondary" style="display: inline-block; padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">← Vue semaine</a>
    </div>

    <!-- Week Info -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; margin-bottom: 30px;">
        <h2 style="margin: 0; font-size: 1.3em;">Semaine du <?= $weekStart->format('d/m/Y') ?> au <?= $weekEnd->format('d/m/Y') ?></h2>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Analyse de votre charge de travail hebdomadaire</p>
    </div>

    <!-- Key Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2.5em; font-weight: bold;"><?= $weekStats['total'] ?></div>
            <div style="font-size: 0.95em; opacity: 0.9; margin-top: 5px;">Tâches hebdomadaires</div>
        </div>

        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2.5em; font-weight: bold;"><?= $weekStats['unique_days_with_tasks'] ?></div>
            <div style="font-size: 0.95em; opacity: 0.9; margin-top: 5px;">Jours avec tâches</div>
        </div>

        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2.5em; font-weight: bold;"><?= $weekStats['avg_tasks_per_day'] ?></div>
            <div style="font-size: 0.95em; opacity: 0.9; margin-top: 5px;">Moyenne par jour</div>
        </div>

        <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <div style="font-size: 2.5em; font-weight: bold;">
                <?php
                $completedThisWeek = 0;
                foreach ($dailyBreakdown as $data) {
                    $completedThisWeek += $data['done'];
                }
                echo $completedThisWeek;
                ?>
            </div>
            <div style="font-size: 0.95em; opacity: 0.9; margin-top: 5px;">Tâches terminées</div>
        </div>
    </div>

    <!-- Daily Breakdown Chart -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
        <h3 style="margin-top: 0;">Répartition par jour</h3>
        <div style="display: grid; gap: 15px;">
            <?php
            $dayNames = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
            $dayShorts = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
            $idx = 0;
            foreach ($dailyBreakdown as $dateStr => $data):
                $dayName = $dayShorts[$idx] ?? 'N/A';
                $isToday = $dateStr === $today->format('Y-m-d');
                $bgColor = $isToday ? '#e8f4f8' : '#f9f9f9';
                $maxTasks = max(array_map(function($d) { return $d['total']; }, $dailyBreakdown));
                $barWidth = $maxTasks > 0 ? ($data['total'] / $maxTasks) * 100 : 0;
                $idx++;
            ?>
                <div style="background: <?= $bgColor ?>; padding: 15px; border-radius: 6px; border-left: 4px solid <?= $isToday ? '#007bff' : '#ddd' ?>;">
                    <div style="display: grid; grid-template-columns: 80px 1fr auto; gap: 15px; align-items: center;">
                        <div style="font-weight: bold; font-size: 1.1em;"><?= $dayName ?></div>
                        <div>
                            <div style="background: #eee; height: 24px; border-radius: 12px; overflow: hidden; position: relative;">
                                <div style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: <?= $barWidth ?>%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.8em;">
                                    <?php if ($barWidth > 15): ?>
                                        <?= $data['total'] ?> tâche<?= $data['total'] > 1 ? 's' : '' ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($barWidth <= 15 && $data['total'] > 0): ?>
                                    <span style="position: absolute; left: <?= $barWidth + 5 ?>%; top: 50%; transform: translateY(-50%); font-weight: bold; color: #666; font-size: 0.8em;"><?= $data['total'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 100px;">
                            <div style="font-size: 0.85em; color: #666;">
                                ✓ <?= $data['done'] ?> | ⚙️ <?= $data['in_progress'] ?> | 📋 <?= $data['todo'] ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Busiest Day Alert -->
    <?php if ($weekStats['busiest_day']): ?>
        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h3 style="margin: 0 0 10px 0;">⚠️ Jour le plus chargé</h3>
            <p style="margin: 0;">
                <?php
                $busiestDate = new DateTime($weekStats['busiest_day']);
                $dayNames = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
                $dayName = ucfirst($dayNames[(int)$busiestDate->format('N') - 1]);
                echo $dayName . ' ' . $busiestDate->format('d/m/Y') . ' - ' . $weekStats['busiest_day_count'] . ' tâche' . ($weekStats['busiest_day_count'] > 1 ? 's' : '') . ' prévues';
                ?>
            </p>
            <a href="day_view.php?date=<?= $weekStats['busiest_day'] ?>" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: rgba(255,255,255,0.3); color: white; text-decoration: none; border-radius: 4px; border: 2px solid white;">Voir le détail →</a>
        </div>
    <?php endif; ?>

    <!-- Tasks by Project -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
        <h3 style="margin-top: 0;">Tâches hebdomadaires par projet</h3>
        <div style="display: grid; gap: 12px;">
            <?php
            $tasksByProject = [];
            foreach ($projects as $project) {
                $isMember = false;
                foreach ($project['members'] as $member) {
                    if (strtolower($member['email']) === $email) {
                        $isMember = true;
                        break;
                    }
                }
                
                if (!$isMember) continue;
                
                $projectTasks = [];
                foreach ($project['tasks'] as $task) {
                    if (strtolower($task['assigned_to']) === $email && ($task['is_recurring'] ?? false)) {
                        $projectTasks[] = $task;
                    }
                }
                
                if (!empty($projectTasks)) {
                    $tasksByProject[$project['name']] = $projectTasks;
                }
            }
            
            if (empty($tasksByProject)):
            ?>
                <p style="color: #999; text-align: center; padding: 20px;">Aucune tâche hebdomadaire</p>
            <?php
            else:
                foreach ($tasksByProject as $projectName => $tasks):
            ?>
                <div style="background: #f9f9f9; padding: 12px; border-radius: 6px;">
                    <strong><?= htmlspecialchars($projectName) ?></strong> 
                    <span style="float: right; background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.85em;"><?= count($tasks) ?> tâche<?= count($tasks) > 1 ? 's' : '' ?></span>
                    <div style="clear: both; margin-top: 8px; font-size: 0.9em; color: #666;">
                        <?php foreach ($tasks as $task): ?>
                            <div style="margin: 4px 0;">• <?= htmlspecialchars($task['title']) ?> 
                                <span style="color: #999;">(<?= htmlspecialchars(format_recurring_days($task['recurring_days'] ?? [])) ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php
                endforeach;
            endif;
            ?>
        </div>
    </div>

    <!-- Tips Section -->
    <div style="background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%); color: #333; padding: 20px; border-radius: 8px;">
        <h3 style="margin-top: 0;">💡 Conseils pour optimiser votre semaine</h3>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>Répartissez vos tâches hebdomadaires pour éviter les pics de charge</li>
            <li>Utilisez la vue jour pour vous concentrer sur les tâches du jour</li>
            <li>Mettez à jour régulièrement votre progression pour une meilleure vue d'ensemble</li>
            <li>Ajustez les jours récurrents de vos tâches si la charge est inégale</li>
        </ul>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
