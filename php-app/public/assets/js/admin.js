document.addEventListener('DOMContentLoaded', () => {
    checkAdminAccess();
});

function showNotification(message, isError = false) {
    const notif = document.getElementById('notificationArea');
    notif.innerText = message;
    notif.className = `mb-6 p-4 rounded-lg text-sm font-medium ${isError ? 'bg-red-900/50 text-red-200 border border-red-500' : 'bg-green-900/50 text-green-200 border border-green-500'}`;
    notif.classList.remove('hidden');
    setTimeout(() => notif.classList.add('hidden'), 5000);
}

async function checkAdminAccess() {
    const token = localStorage.getItem('jwt_token');
    const role = localStorage.getItem('user_role');

    if (!token || role !== 'admin') {
        alert('Доступ заборонено. Потрібні права адміністратора.');
        window.location.href = 'dashboard.php';
        return;
    }

    await loadStats();
    await loadUsers();
}

async function apiRequest(endpoint, method = 'GET', body = null) {
    const token = localStorage.getItem('jwt_token');
    const options = {
        method: method,
        headers: { 'Authorization': `Bearer ${token}` }
    };
    if (body) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }
    
    try {
        const response = await fetch(endpoint, options);
        const data = await response.json();
        
        if (!response.ok) {
            if (response.status === 401 || response.status === 403) {
                alert('Сесія закінчилась або недостатньо прав.');
                window.location.href = 'dashboard.php';
                throw new Error("Unauthorized");
            }
            throw new Error(data.error || 'API Error');
        }
        return data;
    } catch (error) {
        showNotification(error.message, true);
        throw error;
    }
}

async function loadStats() {
    try {
        const stats = await apiRequest('/api/admin/stats');
        document.getElementById('statUsers').innerText = stats.total_users;
        document.getElementById('statTasks').innerText = stats.total_analyses_run;
        document.getElementById('statData').innerText = stats.market_data_points;
    } catch (e) {
        console.error("Failed to load stats", e);
    }
}

async function loadUsers() {
    try {
        const users = await apiRequest('/api/admin/users');
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = '';
        
        users.forEach(user => {
            const date = new Date(user.created_at).toLocaleString('uk-UA');
            
            let roleBadge = '';
            if (user.role === 'admin') {
                roleBadge = `<span class="bg-red-500/20 text-red-400 px-2 py-1 rounded text-xs font-bold uppercase border border-red-500/30">Admin</span>`;
            } else if (user.role === 'pro') {
                roleBadge = `<span class="bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded text-xs font-bold uppercase border border-yellow-500/30">Pro</span>`;
            } else {
                roleBadge = `<span class="bg-gray-600/50 text-gray-300 px-2 py-1 rounded text-xs font-bold uppercase border border-gray-500/50">Standard</span>`;
            }

            let actionBtn = `
                <div class="flex justify-end gap-3">
                    <button onclick="openEditModal(${user.id}, '${user.role}')" class="text-blue-400 hover:text-blue-300 font-medium transition flex items-center gap-1" title="Редагувати роль">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                    <button onclick="deleteUser(${user.id})" class="text-red-400 hover:text-red-300 font-medium transition flex items-center gap-1" title="Видалити">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            `;
            
            const currentUserId = parseJwt(localStorage.getItem('jwt_token')).sub;
            if (user.id == currentUserId) {
                actionBtn = `<span class="text-gray-500 italic text-xs">Це ви</span>`;
            }

            tbody.innerHTML += `
                <tr class="hover:bg-gray-750 transition-colors">
                    <td class="px-6 py-4 font-mono text-gray-400">${user.id}</td>
                    <td class="px-6 py-4 font-medium text-white">${user.email}</td>
                    <td class="px-6 py-4">${roleBadge}</td>
                    <td class="px-6 py-4 text-gray-400 text-sm">${date}</td>
                    <td class="px-6 py-4 text-right">${actionBtn}</td>
                </tr>
            `;
        });
    } catch (e) {
        console.error("Failed to load users", e);
    }
}

async function deleteUser(userId) {
    if (!confirm(`Ви впевнені, що хочете видалити користувача ID ${userId}? Всі його бектести також будуть видалені.`)) {
        return;
    }

    try {
        const result = await apiRequest(`/api/admin/users/${userId}`, 'DELETE');
        showNotification(result.message);
        loadStats();
        loadUsers();
    } catch (e) {}
}

function openEditModal(userId, currentRole) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editUserRole').value = currentRole;
    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}

async function saveUserEdit() {
    const userId = document.getElementById('editUserId').value;
    const newRole = document.getElementById('editUserRole').value;

    try {
        const result = await apiRequest(`/api/admin/users/${userId}`, 'PUT', { role: newRole });
        showNotification(result.message);
        closeEditModal();
        loadUsers();
    } catch (e) {}
}

function logout() {
    apiRequest('/api/auth/logout', 'POST').catch(e => console.error(e));
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('user_role');
    window.location.href = 'dashboard.php';
}

function parseJwt(token) {
    try {
        return JSON.parse(atob(token.split('.')[1]));
    } catch (e) {
        return null;
    }
}