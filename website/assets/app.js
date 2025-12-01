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
    const progressField = form.querySelector('input[name="progress"]');
    
    // Send via fetch with AJAX header
    fetch('update_task.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        // On success, update the progress bar display if it's a progress field
        if (progressField && data.success) {
            const progressValue = progressField.value;
            // Find the progress bar in the parent td
            const td = form.closest('td');
            if (td) {
                const progressBar = td.querySelector('.progress-bar');
                if (progressBar) {
                    const progressFill = progressBar.querySelector('.progress-fill');
                    const progressText = progressBar.querySelector('.progress-text');
                    if (progressFill) {
                        progressFill.style.width = progressValue + '%';
                    }
                    if (progressText) {
                        progressText.textContent = progressValue + '%';
                    }
                }
            }
        }
        // Status updates are already reflected in the DOM
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
