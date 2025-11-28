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
        <button type="button" class="btn-secondary btn-remove" onclick="removeTaskRow(this)">✕ Supprimer</button>
    `;
    container.appendChild(div);
}

function removeMemberRow(button) {
    const row = button.closest('.member-row');
    if (row) {
        row.remove();
    }
}

function removeTaskRow(button) {
    const row = button.closest('.task-row');
    if (row) {
        row.remove();
    }
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
    const allTasksTable = document.querySelector(`[data-project="${projectId}"][data-table="all"]`);
    const myTasksTable = document.querySelector(`[data-project="${projectId}"][data-table="mine"]`);

    if (!allTasksTable || !myTasksTable) return;

    if (allTasksTable.style.display === 'none' || allTasksTable.style.display === '') {
        // Afficher toutes les tâches
        allTasksTable.style.display = 'block';
        myTasksTable.style.display = 'none';
        if (allTasksBtn) allTasksBtn.classList.add('active');
        if (myTasksBtn) myTasksBtn.classList.remove('active');
    } else {
        // Afficher mes tâches
        allTasksTable.style.display = 'none';
        myTasksTable.style.display = 'block';
        if (allTasksBtn) allTasksBtn.classList.remove('active');
        if (myTasksBtn) myTasksBtn.classList.add('active');
    }
}