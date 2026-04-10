<?php
// MVC View: Administration Panel
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TradeBench | Admin Panel</title>
    <!-- Styles & Logic are perfectly separated from the View -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="admin-nav">
        <div class="flex items-center gap-4">
            <h1 class="title-main" style="color:var(--danger);margin:0;">TradeBench <span style="color:var(--text-muted)">Admin</span></h1>
            <span class="badge badge-admin">Superuser</span>
        </div>
        <div class="flex gap-4">
            <button onclick="window.location.href='dashboard.php'" class="btn btn-secondary">
                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Повернутись
            </button>
            <button onclick="logout()" class="btn btn-text">Вийти</button>
        </div>
    </nav>

    <div class="container">
        
        <div id="notificationArea" class="alert hidden"></div>

        <!-- System Stats Module -->
        <h2 class="section-title text-white">
            <svg class="icon-md text-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg> 
            Статистика системи
        </h2>
        <div class="stats-grid">
            <div class="panel">
                <p class="text-xs text-muted uppercase font-bold">Всього користувачів</p>
                <p id="statUsers" class="text-4xl font-bold mt-4 text-white">-</p>
            </div>
            <div class="panel">
                <p class="text-xs text-muted uppercase font-bold">Проведено аналізів</p>
                <p id="statTasks" class="text-4xl font-bold mt-4 text-blue">-</p>
            </div>
            <div class="panel">
                <p class="text-xs text-muted uppercase font-bold">Точок даних (Свічок)</p>
                <p id="statData" class="text-4xl font-bold mt-4 text-purple">-</p>
            </div>
        </div>

        <!-- Users Management Module -->
        <h2 class="section-title text-white mt-8">
            <svg class="icon-md text-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg> 
            Керування користувачами
        </h2>
        <div class="panel" style="padding:0;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Дата реєстрації</th>
                            <th class="text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody"></tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Modal for Editing User -->
    <div id="editUserModal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 class="text-xl font-bold text-white mb-4">Редагування користувача</h3>
            <input type="hidden" id="editUserId">
            
            <div class="form-group">
                <label class="form-label">Роль користувача</label>
                <select id="editUserRole" class="form-control">
                    <option value="standard">Standard</option>
                    <option value="pro">PRO</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <button onclick="closeEditModal()" class="btn btn-secondary">Скасувати</button>
                <button onclick="saveUserEdit()" class="btn btn-primary">Зберегти</button>
            </div>
        </div>
    </div>

    <!-- JS Dependency -->
    <script src="assets/js/admin.js"></script>
</body>
</html>