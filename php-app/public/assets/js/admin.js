document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('jwt_token');
    // Перевірка токена та ролі адміністратора
    if (!token || localStorage.getItem('user_role') !== 'admin') {
        alert('Access denied. Administrator privileges required.');
        window.location.href = 'dashboard';
        return;
    }
    loadStats();
    loadUsers();
});

let loadChartInstance = null;

function showNotification(message, isError = false) {
    const notif = document.getElementById('notificationArea');
    if (!notif) return;
    notif.innerText = message;
    notif.className = `mb-6 p-4 rounded-lg text-sm font-medium ${isError ? 'bg-red-900/50 text-red-200 border border-red-500' : 'bg-green-900/50 text-green-200 border border-green-500'}`;
    notif.classList.remove('hidden');
    setTimeout(() => notif.classList.add('hidden'), 5000);
}

async function apiRequest(endpoint, method = 'GET', body = null) {
    const options = { 
        method, 
        headers: { 'Authorization': `Bearer ${localStorage.getItem('jwt_token')}` } 
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
                alert('Session expired or access denied.');
                window.location.href = 'dashboard';
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
        
        // Оновлення верхніх віджетів з цифрами
        const usersEl = document.getElementById('statUsers');
        const tasksEl = document.getElementById('statTasks');
        const dataEl = document.getElementById('statData');
        
        if (usersEl) usersEl.innerText = stats.total_users;
        if (tasksEl) tasksEl.innerText = stats.total_analyses_run;
        if (dataEl) dataEl.innerText = stats.market_data_points;
        
        // Малювання графіка, якщо є дані
        if (stats.weekly_chart) {
            renderLoadChart(stats.weekly_chart);
        }
    } catch (e) {
        console.error("Failed to load statistics:", e);
    }
}

function renderLoadChart(chartData) {
    const canvas = document.getElementById('serverLoadChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Знищуємо попередній графік перед малюванням нового (щоб уникнути багів при оновленні)
    if (loadChartInstance) {
        loadChartInstance.destroy();
    }

    loadChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.days,
            datasets: [
                {
                    label: 'Усі запити (Total)',
                    data: chartData.total,
                    backgroundColor: 'rgba(59, 130, 246, 0.4)', // Tailwind Blue-500
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'Успішні (Completed)',
                    data: chartData.completed,
                    backgroundColor: 'rgba(34, 197, 94, 0.6)', // Tailwind Green-500
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: '#9ca3af' } // gray-400
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(17, 24, 39, 0.95)', // gray-900
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: '#374151',
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(55, 65, 81, 0.5)' }, // gray-700
                    ticks: { color: '#9ca3af', stepSize: 1 }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#9ca3af' }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            }
        }
    });
}

async function loadUsers() {
    try {
        const users = await apiRequest('/api/admin/users');
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        const currentUserId = JSON.parse(atob(localStorage.getItem('jwt_token').split('.')[1])).sub;
        
        tbody.innerHTML = users.map(user => {
            const date = new Date(user.created_at).toLocaleString('uk-UA');
            const roleBadge = user.role === 'admin' ? 'bg-red-500/20 text-red-400 border-red-500/30' : 
                              user.role === 'pro' ? 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30' : 
                              'bg-gray-600/50 text-gray-300 border-gray-500/50';
            
            const actions = user.id == currentUserId ? `<span class="text-gray-500 italic text-xs">Ви</span>` : `
                <button onclick="openEditModal(${user.id}, '${user.role}')" class="text-blue-400 hover:text-blue-300 mr-3 transition font-semibold">Редагувати</button>
                <button onclick="deleteUser(${user.id})" class="text-red-400 hover:text-red-300 transition font-semibold">Видалити</button>
            `;

            return `
                <tr class="hover:bg-gray-750 transition-colors border-b border-gray-800">
                    <td class="px-6 py-4 font-mono text-gray-400">${user.id}</td>
                    <td class="px-6 py-4 font-medium text-white">${user.email}</td>
                    <td class="px-6 py-4"><span class="px-2 py-1 rounded text-xs font-bold uppercase border ${roleBadge}">${user.role}</span></td>
                    <td class="px-6 py-4 text-gray-400 text-sm">${date}</td>
                    <td class="px-6 py-4 text-right">${actions}</td>
                </tr>`;
        }).join('');
    } catch (e) {
        console.error("Failed to load users:", e);
    }
}

async function deleteUser(id) {
    if (!confirm(`Видалити користувача з ID ${id}? Цю дію неможливо скасувати.`)) return;
    try {
        const res = await apiRequest(`/api/admin/users/${id}`, 'DELETE');
        showNotification(res.message);
        loadStats(); 
        loadUsers();
    } catch (e) {}
}

function openEditModal(id, role) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUserRole').value = role;
    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditModal() { 
    document.getElementById('editUserModal').classList.add('hidden'); 
}

async function saveUserEdit() {
    const id = document.getElementById('editUserId').value;
    const role = document.getElementById('editUserRole').value;
    try {
        const res = await apiRequest(`/api/admin/users/${id}`, 'PUT', { role });
        showNotification(res.message);
        closeEditModal(); 
        loadUsers();
    } catch (e) {}
}

function logout() {
    localStorage.clear();
    window.location.href = 'dashboard';
}