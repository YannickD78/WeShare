function addMemberRow() {
    const container = document.getElementById('members-container');
    if (!container) return;

    const div = document.createElement('div');
    div.className = 'member-row';
    div.innerHTML = `
        <input type="text" name="member_name[]" placeholder="Nom du membre" data-temp="true">
        <input type="email" name="member_email[]" placeholder="Email du membre" data-temp="true">
        <button type="button" class="btn-secondary btn-confirm" onclick="confirmMember(this)">✓ Confirmer</button>
    `;
    container.appendChild(div);
    updateTaskAssigneeSelects();
}

function confirmMember(button) {
    const row = button.closest('.member-row');
    if (!row) return;

    const nameInput = row.querySelector('input[name="member_name[]"]');
    const emailInput = row.querySelector('input[name="member_email[]"]');
    const name = nameInput?.value.trim();
    const email = emailInput?.value.trim();

    if (!email || !name) {
        alert('Veuillez remplir le nom et l\'email du membre');
        return;
    }

    // Marquer comme confirmé
    nameInput.removeAttribute('data-temp');
    emailInput.removeAttribute('data-temp');
    
    // Remplacer le bouton confirmer par un bouton supprimer
    button.innerHTML = '✕ Supprimer';
    button.className = 'btn-secondary btn-remove';
    button.onclick = function() { removeMemberRow(this); };

    // Mettre à jour les options dans les sélecteurs de tâches
    updateTaskAssigneeSelects();
}

function removeMemberRow(button) {
    const row = button.closest('.member-row');
    if (row) {
        row.remove();
        updateTaskAssigneeSelects();
    }
}

function updateTaskAssigneeSelects() {
    // Récupérer tous les membres confirmés
    const members = [];
    const memberRows = document.querySelectorAll('.member-row');
    
    memberRows.forEach(row => {
        const nameInput = row.querySelector('input[name="member_name[]"]');
        const emailInput = row.querySelector('input[name="member_email[]"]');
        
        // Ne prendre que les membres confirmés (sans data-temp)
        if (nameInput && emailInput && !nameInput.hasAttribute('data-temp')) {
            members.push({
                name: nameInput.value,
                email: emailInput.value
            });
        }
    });

    // Mettre à jour les sélecteurs dans les tâches
    const taskRows = document.querySelectorAll('.task-row');
    taskRows.forEach(row => {
        const select = row.querySelector('select[name="task_assigned_to[]"]');
        if (select) {
            const currentValue = select.value;
            select.innerHTML = '<option value="">-- Non assigné --</option>';
            
            members.forEach(member => {
                const option = document.createElement('option');
                option.value = member.email;
                option.textContent = member.name;
                if (member.email === currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }
    });
}

function addTaskRow() {
    const container = document.getElementById('tasks-container');
    if (!container) return;

    // Récupérer la liste des membres confirmés
    const members = [];
    const memberRows = document.querySelectorAll('.member-row');
    
    memberRows.forEach(row => {
        const nameInput = row.querySelector('input[name="member_name[]"]');
        const emailInput = row.querySelector('input[name="member_email[]"]');
        
        if (nameInput && emailInput && !nameInput.hasAttribute('data-temp')) {
            members.push({
                name: nameInput.value,
                email: emailInput.value
            });
        }
    });

    const div = document.createElement('div');
    div.className = 'task-row';
    
    let selectHTML = '<select name="task_assigned_to[]"><option value="">-- Non assigné --</option>';
    members.forEach(member => {
        selectHTML += `<option value="${member.email}">${member.name}</option>`;
    });
    selectHTML += '</select>';
    
    div.innerHTML = `
        <input type="text" name="task_title[]" placeholder="Titre de la tâche">
        ${selectHTML}
        <select name="task_mode[]" class="task-mode-select" title="Mode d'évaluation">
            <option value="status">Statut</option>
            <option value="bar">Barre</option>
        </select>
        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
            <input type="checkbox" name="task_recurring[]" class="task-recurring-check" onchange="toggleRecurringDays(this)">
            <span>Hebdomadaire</span>
        </label>
        <button type="button" class="btn-secondary btn-remove" onclick="removeTaskRow(this)">✕ Supprimer</button>
    `;
    container.appendChild(div);
}

function removeMemberRow(button) {
    const row = button.closest('.member-row');
    if (row) {
        row.remove();
        updateTaskAssigneeSelects();
    }
}

function removeTaskRow(button) {
    const row = button.closest('.task-row');
    if (row) {
        row.remove();
    }
}

function toggleRecurringDays(checkbox) {
    const taskRow = checkbox.closest('.task-row');
    if (!taskRow) return;
    
    // Get the task index to create proper indexed names
    const taskIndex = Array.from(document.querySelectorAll('.task-row')).indexOf(taskRow);
    
    let recurringDaysContainer = taskRow.querySelector('.recurring-days-container');
    
    if (checkbox.checked) {
        if (!recurringDaysContainer) {
            // Create the recurring days selector
            recurringDaysContainer = document.createElement('div');
            recurringDaysContainer.className = 'recurring-days-container';
            recurringDaysContainer.style.cssText = 'display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; padding: 8px; background: #f9f9f9; border-radius: 4px; width: 100%;';
            
            const days = [
                { label: 'Lun', value: 'mon' },
                { label: 'Mar', value: 'tue' },
                { label: 'Mer', value: 'wed' },
                { label: 'Jeu', value: 'thu' },
                { label: 'Ven', value: 'fri' },
                { label: 'Sam', value: 'sat' },
                { label: 'Dim', value: 'sun' }
            ];
            
            days.forEach(day => {
                const label = document.createElement('label');
                label.style.cssText = 'display: flex; align-items: center; gap: 4px; cursor: pointer;';
                label.innerHTML = `
                    <input type="checkbox" name="task_recurring_days[${taskIndex}][]" value="${day.value}">
                    <span>${day.label}</span>
                `;
                recurringDaysContainer.appendChild(label);
            });
            
            taskRow.appendChild(recurringDaysContainer);
        } else {
            recurringDaysContainer.style.display = 'flex';
        }
    } else {
        if (recurringDaysContainer) {
            recurringDaysContainer.style.display = 'none';
        }
    }
}

function addNewMemberRow() {
    const container = document.getElementById('members-new-container');
    if (!container) {
        // Si on n'est pas dans create_project, utiliser la méthode addMemberRow normale
        addMemberRow();
        return;
    }

    const div = document.createElement('div');
    div.className = 'member-row';
    div.innerHTML = `
        <input type="text" name="member_name[]" placeholder="Nom du membre" data-temp="true">
        <input type="email" name="member_email[]" placeholder="Email du membre" data-temp="true">
        <button type="button" class="btn-secondary btn-confirm" onclick="confirmMember(this)">✓ Confirmer</button>
    `;
    container.appendChild(div);
}

function toggleInviteLink() {
    const box = document.getElementById('invite-link-box');
    if (!box) return;

    if (box.style.display === 'none' || box.style.display === '') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

function toggleTasksView(projectId) {
    const allTasksBtn = document.querySelector(`[data-project="${projectId}"][data-view="all"]`);
    const myTasksBtn = document.querySelector(`[data-project="${projectId}"][data-view="mine"]`);
    const allTasksTable = document.querySelector(`.table-wrapper[data-project="${projectId}"][data-table="all"]`);
    const myTasksTable = document.querySelector(`.table-wrapper[data-project="${projectId}"][data-table="mine"]`);

    if (!allTasksTable || !myTasksTable) return;

    if (allTasksTable.style.display === 'none' || allTasksTable.style.display === '') {
        allTasksTable.style.display = 'block';
        myTasksTable.style.display = 'none';
        if (allTasksBtn) allTasksBtn.classList.add('active');
        if (myTasksBtn) myTasksBtn.classList.remove('active');
    } else {
        myTasksTable.style.display = 'block';
        allTasksTable.style.display = 'none';
        if (myTasksBtn) myTasksBtn.classList.add('active');
        if (allTasksBtn) allTasksBtn.classList.remove('active');
    }
};

// Update task via AJAX without page reload
window.updateTaskAjax = function(form) {
    // Create FormData
    const formData = new FormData(form);
    const projectId = formData.get('project_id');
    const taskId = formData.get('task_id');
    const status = formData.get('status');
    const progress = formData.get('progress');
    let date = formData.get('date');
    
    // Check if this is a recurring task (date field exists but is empty, and there are day-buttons nearby)
    const dateInput = form.querySelector('input[name="date"]');
    const taskRow = form.closest('tr');
    const dayButtons = taskRow ? taskRow.querySelectorAll('.day-btn') : [];
    
    if (dateInput && !date && dayButtons.length > 0) {
        // This is a recurring task without a selected date
        // Auto-fill the closest day
        const dayMap = {'sun': 0, 'mon': 1, 'tue': 2, 'wed': 3, 'thu': 4, 'fri': 5, 'sat': 6};
        const today = new Date();
        const currentDay = today.getDay();
        
        let closestButton = null;
        let minDaysAhead = Infinity;
        
        dayButtons.forEach(btn => {
            const dayAttr = btn.getAttribute('data-day');
            const targetDayNum = dayMap[dayAttr];
            let daysToAdd = (targetDayNum - currentDay + 7) % 7;
            
            if (daysToAdd < minDaysAhead) {
                minDaysAhead = daysToAdd;
                closestButton = btn;
            }
        });
        
        if (closestButton) {
            const dayAttr = closestButton.getAttribute('data-day');
            const targetDayNum = dayMap[dayAttr];
            let daysToAdd = (targetDayNum - currentDay + 7) % 7;
            const targetDate = new Date(today);
            targetDate.setDate(today.getDate() + daysToAdd);
            
            const year = targetDate.getFullYear();
            const month = String(targetDate.getMonth() + 1).padStart(2, '0');
            const dateNum = String(targetDate.getDate()).padStart(2, '0');
            date = `${year}-${month}-${dateNum}`;
            
            // Update both the form input and the FormData
            dateInput.value = date;
            formData.set('date', date);
        }
    }
    
    console.log('Envoi AJAX:', {
        project_id: projectId,
        task_id: taskId,
        status: status,
        progress: progress,
        date: date
    });
    
    // Send via fetch with AJAX header
    fetch('update_task.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Réponse reçue:', response.status);
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        console.log('Données JSON reçues:', data);
        
        if (data.success) {
            // Update all matching task rows in both "Mes tâches" and "Toutes les tâches" tables
            const allTaskRows = document.querySelectorAll(`tr[data-task-id="${taskId}"][data-project-id="${projectId}"]`);
            
            allTaskRows.forEach(row => {
                const td = row.querySelector('td:last-child'); // Évaluation column
                if (td && status) {
                    // Status update
                    const select = td.querySelector('select');
                    if (select) {
                        select.value = status;
                        console.log('Status updated in row:', status);
                    }
                    
                    // Update row background color if task is now done
                    if (status === 'done') {
                        row.style.backgroundColor = '#e8f5e9';
                        row.classList.add('task-completed');
                    } else {
                        row.style.backgroundColor = 'transparent';
                        row.classList.remove('task-completed');
                    }
                } else if (td && progress !== undefined) {
                    // Progress update
                    const progressBar = td.querySelector('.progress-bar');
                    if (progressBar) {
                        const progressFill = progressBar.querySelector('.progress-fill');
                        const progressText = progressBar.querySelector('.progress-text');
                        if (progressFill) {
                            progressFill.style.width = progress + '%';
                        }
                        if (progressText) {
                            progressText.textContent = progress + '%';
                        }
                        console.log('Progress updated in row:', progress + '%');
                    }
                    
                    // Update row background color if task is now complete (100%)
                    if (progress >= 100) {
                        row.style.backgroundColor = '#e8f5e9';
                        row.classList.add('task-completed');
                    } else {
                        row.style.backgroundColor = 'transparent';
                        row.classList.remove('task-completed');
                    }
                }
            });
        }
    })
    .catch(error => {
        console.error('Error updating task:', error);
        alert('Erreur lors de la mise à jour. Veuillez réessayer.');
    });
    
    return false;
};

// Initialiser les dropdowns avec le créateur au chargement
document.addEventListener('DOMContentLoaded', function() {
    updateTaskAssigneeSelects();
});

// Delete task via AJAX
window.deleteTaskAjax = function(projectId, taskId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')) {
        return false;
    }
    
    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('task_id', taskId);
    
    console.log('Suppression tâche:', { project_id: projectId, task_id: taskId });
    
    fetch('delete_task.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Réponse reçue:', response.status);
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        console.log('Données JSON reçues:', data);
        
        if (data.success) {
            // Remove all matching task rows from both tables
            const allTaskRows = document.querySelectorAll(`tr[data-task-id="${taskId}"][data-project-id="${projectId}"]`);
            allTaskRows.forEach(row => {
                row.remove();
            });
            console.log('Tâche supprimée');
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting task:', error);
        alert('Erreur lors de la suppression. Veuillez réessayer.');
    });
    
    return false;
};

document.addEventListener('DOMContentLoaded', function() {
// Fonction pour basculer entre "Mes tâches" et "Toutes les tâches"
function toggleTasksView(projectId, view) {
const allTasksBtn = document.querySelector(`.btn-toggle[data-project="${projectId}"][data-view="all"]`);
const myTasksBtn = document.querySelector(`.btn-toggle[data-project="${projectId}"][data-view="mine"]`);
const allTasksTable = document.querySelector(`.table-wrapper[data-project="${projectId}"][data-table="all"]`);
const myTasksTable = document.querySelector(`.table-wrapper[data-project="${projectId}"][data-table="mine"]`);


    if (!allTasksTable || !myTasksTable) return;

    if (view === 'all') {
        allTasksTable.style.display = 'block';
        myTasksTable.style.display = 'none';
        allTasksBtn.classList.add('active');
        myTasksBtn.classList.remove('active');
    } else {
        myTasksTable.style.display = 'block';
        allTasksTable.style.display = 'none';
        myTasksBtn.classList.add('active');
        allTasksBtn.classList.remove('active');
    }
}

// Attacher les événements à tous les boutons toggle
document.querySelectorAll('.btn-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const projectId = this.dataset.project;
        const view = this.dataset.view;
        toggleTasksView(projectId, view);
    });
});

// Initialisation : afficher "Mes tâches" par défaut
document.querySelectorAll('.tasks-toggle').forEach(container => {
    const projectId = container.querySelector('.btn-toggle').dataset.project;
    toggleTasksView(projectId, 'mine');
});


});

/**
 * Update task progress for a specific day via AJAX
 * Used in weekly_view.php and day_view.php
 * Accepts either day abbreviation (mon-sun) or full date (YYYY-MM-DD)
 */
function updateDailyTaskProgress(projectId, taskId, dayOrDate, action, value = null) {
    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('task_id', taskId);
    formData.append('day', dayOrDate);
    formData.append('action', action);
    if (value !== null) {
        formData.append('value', value);
    }
    
    fetch('update_task_daily.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Find the container with matching task-id and date/day
            let container = document.querySelector(`[data-task-id="${taskId}"][data-date="${dayOrDate}"]`);
            if (!container) {
                container = document.querySelector(`[data-task-id="${taskId}"][data-day="${dayOrDate}"]`);
            }
            
            // Fallback: search by just task-id and manually check date
            if (!container) {
                const allContainers = document.querySelectorAll(`[data-task-id="${taskId}"]`);
                for (let c of allContainers) {
                    const dataDate = c.getAttribute('data-date');
                    const dataDay = c.getAttribute('data-day');
                    if (dataDate === dayOrDate || dataDay === dayOrDate) {
                        container = c;
                        break;
                    }
                }
            }
            
            if (container) {
                // Update the UI within this container
                const progressBar = container.querySelector('.task-progress-bar');
                const progressText = container.querySelector('.task-progress-text');
                const incrementBtn = container.querySelector('.btn-increment-day');
                const completeBtn = container.querySelector('.btn-complete-day');
                
                if (progressBar) {
                    progressBar.style.width = data.progress + '%';
                    progressBar.textContent = data.progress > 15 ? data.progress + '%' : '';
                }
                if (progressText) {
                    progressText.textContent = data.progress + '%';
                }
                
                // If complete, disable buttons and update UI
                if (data.is_complete) {
                    // Update container background and border color
                    container.style.background = '#e8f8e8';
                    container.style.borderLeft = '4px solid #4caf50';
                    
                    if (incrementBtn) {
                        incrementBtn.disabled = true;
                        incrementBtn.style.opacity = '0.5';
                        incrementBtn.style.cursor = 'not-allowed';
                    }
                    if (completeBtn) {
                        completeBtn.disabled = true;
                        completeBtn.textContent = '✓ Complétée (' + data.progress + '%)';
                        completeBtn.style.opacity = '1';
                        completeBtn.style.cursor = 'not-allowed';
                    }
                }
                
                console.log('Progress updated successfully:', { taskId, dayOrDate, progress: data.progress });
            } else {
                console.warn('Container not found for task', taskId, 'date/day', dayOrDate);
                console.log('Available containers:', document.querySelectorAll('[data-task-id]').length);
            }
        } else {
            alert('Erreur: ' + (data.error || 'Mise à jour échouée'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de la mise à jour');
    });
}

/**
 * Update task status via AJAX (Commencer, Terminer, Marquer complète)
 * @param {string} projectId - Project ID
 * @param {string} taskId - Task ID
 * @param {string} status - New status (todo, in_progress, done)
 * @param {HTMLElement} button - The button element clicked
 * @param {string} date - Optional: Date in YYYY-MM-DD format for recurring tasks
 */
function updateTaskStatus(projectId, taskId, status, button, date = null) {
    if (!button) {
        console.error('Button element not provided');
        return;
    }

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('task_id', taskId);
    formData.append('status', status);
    if (date) {
        formData.append('date', date);
    }

    fetch('update_task.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Find the task container div
            const taskContainer = button.closest('[data-task-id]');
            if (!taskContainer) {
                console.error('Task container not found');
                return;
            }
            
            // Update the status badge
            const statusSpan = taskContainer.querySelector('span[style*="background"]');
            if (statusSpan) {
                const statusLabels = {
                    'todo': '⏳ À faire',
                    'in_progress': '🔄 En cours',
                    'done': '✓ Terminé'
                };
                statusSpan.textContent = statusLabels[status] || status;
                if (status === 'done') {
                    statusSpan.style.background = '#4caf50';
                } else {
                    statusSpan.style.background = '#007bff';
                }
            }
            
            // Update the background color of the task container
            if (status === 'done') {
                taskContainer.style.background = '#e8f8e8';
                taskContainer.style.borderLeft = '4px solid #4caf50';
            }
            
            // Update button state based on new status
            const parent = button.parentElement;
            
            if (status === 'done') {
                // Replace all action buttons with disabled "Complétée" button
                parent.innerHTML = '<button disabled style="padding: 6px 12px; background: #ccc; color: white; border: none; border-radius: 4px; cursor: not-allowed; font-size: 0.85em;">✓ Complétée</button>';
            } else if (status === 'in_progress') {
                // Hide "Commencer" button, keep "Terminer" button
                button.style.display = 'none';
            }
            
            // Optional: show a success message
            console.log('Task status updated to: ' + status);
        } else {
            alert('Erreur: ' + (data.error || 'Mise à jour échouée'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de la mise à jour du statut');
    });
}

/**
 * Handle day selection for recurring tasks in dashboard
 * Shows/hides the task content form based on selected day
 * Loads the stored value for that day from daily_progress
 * 
 * If autoSelect is true, automatically selects the closest day (today or next occurrence)
 */
function selectTaskDay(button, projectId, taskId, taskMode, autoSelect = false) {
    // Get parent container (the div with all day buttons and content)
    const container = button.parentElement.parentElement;
    
    // Remove active state from all day buttons
    container.querySelectorAll('.day-btn').forEach(btn => {
        btn.style.background = '#f0f0f0';
        btn.style.color = '#333';
        btn.style.borderColor = '#ccc';
    });
    
    // Add active state to clicked button
    button.style.background = '#667eea';
    button.style.color = 'white';
    button.style.borderColor = '#667eea';
    
    // Show the task content div
    const contentDiv = container.querySelector('.task-day-content');
    if (contentDiv) {
        contentDiv.style.display = 'block';
        
        // Get the selected day
        const day = button.getAttribute('data-day');
        
        // Calculate the date for this day of week
        const dayMap = {'sun': 0, 'mon': 1, 'tue': 2, 'wed': 3, 'thu': 4, 'fri': 5, 'sat': 6};
        const targetDayNum = dayMap[day];
        const today = new Date();
        const currentDay = today.getDay();
        
        // Days to add to reach the target day
        let daysToAdd = (targetDayNum - currentDay + 7) % 7;
        if (daysToAdd === 0) {
            daysToAdd = 0; // Today is the target day
        }
        
        const targetDate = new Date(today);
        targetDate.setDate(today.getDate() + daysToAdd);
        
        // Format as YYYY-MM-DD
        const year = targetDate.getFullYear();
        const month = String(targetDate.getMonth() + 1).padStart(2, '0');
        const dateNum = String(targetDate.getDate()).padStart(2, '0');
        const dateStr = `${year}-${month}-${dateNum}`;
        
        // Update the hidden date input in the form(s)
        contentDiv.querySelectorAll('input[name="date"]').forEach(input => {
            input.value = dateStr;
        });
        
        // Now fetch the current value for this date from daily_progress
        fetch('get_task_daily_progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `project_id=${projectId}&task_id=${taskId}&date=${dateStr}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update progress bar if in bar mode
                const progressFill = contentDiv.querySelector('.progress-fill');
                const progressText = contentDiv.querySelector('.progress-text');
                const progressInput = contentDiv.querySelector('input[name="progress"]');
                if (progressFill && progressText && progressInput) {
                    const progress = data.progress || 0;
                    progressFill.style.width = progress + '%';
                    progressText.textContent = progress > 15 ? progress + '%' : '';
                    progressInput.value = progress;
                }
                
                // Update status select if in status mode
                const statusSelect = contentDiv.querySelector('select[name="status"]');
                if (statusSelect && data.status) {
                    statusSelect.value = data.status;
                }
            }
        })
        .catch(error => {
            console.warn('Could not load task progress:', error);
            // Gracefully continue with default values
        });
    }
}

/**
 * Auto-select the closest day for a recurring task in dashboard
 * Finds today or the next occurrence and selects it
 */
function autoSelectClosestDay(container) {
    const buttons = container.querySelectorAll('.day-btn');
    if (buttons.length === 0) return;
    
    const dayMap = {'sun': 0, 'mon': 1, 'tue': 2, 'wed': 3, 'thu': 4, 'fri': 5, 'sat': 6};
    const today = new Date();
    const currentDay = today.getDay();
    
    let closestButton = null;
    let minDaysAhead = Infinity;
    
    // Find the button for today, or the closest future day
    buttons.forEach(btn => {
        const day = btn.getAttribute('data-day');
        const targetDayNum = dayMap[day];
        let daysToAdd = (targetDayNum - currentDay + 7) % 7;
        
        // Today gets priority (0 days ahead)
        if (daysToAdd < minDaysAhead) {
            minDaysAhead = daysToAdd;
            closestButton = btn;
        }
    });
    
    // Auto-click the closest button
    if (closestButton) {
        const projectId = closestButton.getAttribute('data-project-id') || 
                         closestButton.closest('[data-project-id]')?.getAttribute('data-project-id');
        const taskId = closestButton.getAttribute('data-task-id') || 
                      closestButton.closest('[data-task-id]')?.getAttribute('data-task-id');
        const taskMode = closestButton.getAttribute('data-task-mode') || 
                        closestButton.closest('[data-task-mode]')?.getAttribute('data-task-mode') || 'status';
        
        // Get the actual project_id and task_id from the form if available
        const form = closestButton.closest('[class*="task-day"]')?.querySelector('form');
        if (form) {
            const projectIdInput = form.querySelector('input[name="project_id"]');
            const taskIdInput = form.querySelector('input[name="task_id"]');
            if (projectIdInput) selectTaskDay(closestButton, projectIdInput.value, taskIdInput?.value, taskMode);
        } else {
            selectTaskDay(closestButton, projectId, taskId, taskMode);
        }
    }
}

// Auto-select closest day when page loads
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.day-btn').forEach(btn => {
        // Only auto-select if it's the first day-btn in its container group
        const parentContainer = btn.parentElement;
        if (parentContainer.querySelector('.day-btn') === btn) {
            autoSelectClosestDay(parentContainer);
        }
    });

    // Auto-fill date for forms in recurring tasks on dashboard tables
    document.querySelectorAll('input[name="date"][value=""]').forEach(dateInput => {
        // Check if this date input is in a row with day-buttons
        const form = dateInput.closest('form');
        if (!form) return;

        const taskRow = form.closest('tr');
        if (!taskRow) return;

        const dayButtons = taskRow.querySelectorAll('.day-btn');
        if (dayButtons.length === 0) return;

        // This is a recurring task - calculate the closest day date
        const dayMap = {'sun': 0, 'mon': 1, 'tue': 2, 'wed': 3, 'thu': 4, 'fri': 5, 'sat': 6};
        const today = new Date();
        const currentDay = today.getDay();

        let closestButton = null;
        let minDaysAhead = Infinity;

        dayButtons.forEach(btn => {
            const dayAttr = btn.getAttribute('data-day');
            const targetDayNum = dayMap[dayAttr];
            const daysToAdd = (targetDayNum - currentDay + 7) % 7;

            if (daysToAdd < minDaysAhead) {
                minDaysAhead = daysToAdd;
                closestButton = btn;
            }
        });

        if (closestButton && minDaysAhead < Infinity) {
            const targetDate = new Date(today);
            targetDate.setDate(today.getDate() + minDaysAhead);

            const year = targetDate.getFullYear();
            const month = String(targetDate.getMonth() + 1).padStart(2, '0');
            const dateNum = String(targetDate.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${dateNum}`;

            dateInput.value = dateStr;
            console.log(`Auto-filled date for task: ${dateStr}`);
        }
    });
});
