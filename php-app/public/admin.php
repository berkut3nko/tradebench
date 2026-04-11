<?php
// MVC View: Administration Panel
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TradeBench | Admin Panel</title>
    <!-- Повертаємо Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-900 text-white font-sans min-h-screen">

    <nav class="bg-gray-800 border-b border-gray-700 px-8 py-4 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-4">
            <h1 class="text-2xl font-bold text-red-500">TradeBench <span class="text-gray-300 text-lg">Admin</span></h1>
            <span class="bg-red-500/20 text-red-400 border border-red-500/50 px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider">Superuser</span>
        </div>
        <div class="flex gap-4">
            <button onclick="window.location.href='dashboard.php'" class="text-sm bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Повернутись до застосунку
            </button>
            <button onclick="logout()" class="text-sm text-gray-400 hover:text-white transition">Вийти</button>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto p-8">
        
        <div id="notificationArea" class="hidden mb-6 p-4 rounded-lg text-sm font-medium"></div>

        <h2 class="text-xl font-bold text-gray-300 mb-4 flex items-center gap-2">
            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Статистика системи
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg">
                <p class="text-sm text-gray-400 uppercase tracking-wide">Всього користувачів</p>
                <p id="statUsers" class="text-4xl font-bold text-white mt-2">-</p>
            </div>
            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg">
                <p class="text-sm text-gray-400 uppercase tracking-wide">Проведено аналізів</p>
                <p id="statTasks" class="text-4xl font-bold text-blue-400 mt-2">-</p>
            </div>
            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg">
                <p class="text-sm text-gray-400 uppercase tracking-wide">Точок даних (Свічок)</p>
                <p id="statData" class="text-4xl font-bold text-purple-400 mt-2">-</p>
            </div>
        </div>

        <h2 class="text-xl font-bold text-gray-300 mb-4 flex items-center gap-2">
            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            Керування користувачами
        </h2>
        <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="text-xs text-gray-400 uppercase bg-gray-900/50 border-b border-gray-700">
                        <tr>
                            <th class="px-6 py-4 font-semibold">ID</th>
                            <th class="px-6 py-4 font-semibold">Email</th>
                            <th class="px-6 py-4 font-semibold">Роль</th>
                            <th class="px-6 py-4 font-semibold">Дата реєстрації</th>
                            <th class="px-6 py-4 font-semibold text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody" class="divide-y divide-gray-700"></tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Modal for Editing User -->
    <div id="editUserModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50">
        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 w-96 shadow-2xl">
            <h3 class="text-xl font-bold text-white mb-4">Редагування користувача</h3>
            <input type="hidden" id="editUserId">
            <div class="mb-4">
                <label class="block text-sm text-gray-400 mb-1">Роль користувача</label>
                <select id="editUserRole" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white outline-none focus:border-blue-500">
                    <option value="standard">Standard</option>
                    <option value="pro">PRO</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeEditModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded text-white text-sm transition">Скасувати</button>
                <button onclick="saveUserEdit()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 rounded text-white text-sm transition font-bold">Зберегти</button>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>