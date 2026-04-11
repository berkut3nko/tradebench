<?php
// MVC View: User Dashboard Panel
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TradeBench | Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-900 text-white font-sans min-h-screen p-8">

    <div id="authView" class="max-w-md mx-auto mt-20 bg-gray-800 p-8 rounded-xl border border-gray-700 shadow-2xl">
        <h1 class="text-3xl font-bold text-blue-500 mb-2 text-center">TradeBench</h1>
        <p class="text-gray-400 text-sm text-center mb-6">Аналітична платформа</p>
        
        <div id="authError" class="hidden mb-4 p-3 bg-red-900/50 border border-red-500 text-red-200 text-sm rounded"></div>
        <div id="authSuccess" class="hidden mb-4 p-3 bg-green-900/50 border border-green-500 text-green-200 text-sm rounded"></div>

        <form id="authForm" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-400 mb-1">Email</label>
                <input type="email" id="email" required class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white outline-none focus:border-blue-500 transition">
            </div>
            <div>
                <label class="block text-sm text-gray-400 mb-1">Пароль</label>
                <input type="password" id="password" required class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white outline-none focus:border-blue-500 transition">
                <p class="text-xs text-gray-500 mt-1">Для реєстрації: мін. 8 символів, літери та цифри.</p>
            </div>
            <div class="flex gap-4 pt-4">
                <button type="button" onclick="handleAuth('login')" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">Увійти</button>
                <button type="button" onclick="handleAuth('register')" class="w-full bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition">Реєстрація</button>
            </div>
        </form>
    </div>

    <div id="dashboardView" class="hidden max-w-6xl mx-auto">
        
        <header class="flex justify-between items-center mb-10 border-b border-gray-800 pb-4">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-blue-500">TradeBench <span class="text-gray-500 text-lg">Control Center</span></h1>
                <span id="roleBadge" class="hidden px-3 py-1 rounded text-xs font-bold uppercase tracking-wider"></span>
            </div>
            <div class="flex items-center gap-4">
                <button id="adminPanelBtn" onclick="window.location.href='admin.php'" class="hidden text-sm bg-purple-600 hover:bg-purple-500 px-4 py-2 rounded transition text-white font-bold flex items-center gap-2 shadow-lg shadow-purple-900/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Адмін-панель
                </button>
                <button onclick="logout()" class="text-sm text-gray-400 hover:text-white transition">Вийти з акаунта</button>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 lg:col-span-1 h-fit shadow-lg">
                <h2 class="text-xl font-semibold mb-4 text-blue-400">Аналітика</h2>
                
                <div class="mb-4">
                    <label class="block text-sm text-gray-400 mb-1">Валютна пара</label>
                    <select id="pair" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white outline-none focus:border-blue-500">
                        <option value="BTCUSDT">BTC/USDT</option>
                        <option value="EURUSDT">EUR/USDT</option>
                        <option value="ETHUSDT">ETH/USDT</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm text-gray-400 mb-1">Таймфрейм</label>
                    <select id="timeframe" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white outline-none focus:border-blue-500">
                        <option value="1h">1 Година (1h)</option>
                        <option value="15m">15 Хвилин (15m) — [PRO]</option>
                        <option value="4h">4 Години (4h) — [PRO]</option>
                        <option value="1d">1 День (1d) — [PRO]</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm text-gray-400 mb-1">Основа стратегії</label>
                    <select id="strategy" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white outline-none focus:border-blue-500">
                        <option value="OPTIMIZE" class="font-bold text-purple-400">🌟 Генетичний ШІ-Автопідбір</option>    
                        <option value="SMA_CROSS">SMA Crossover (Ковзне середнє)</option>
                        <option value="EMA_CROSS">EMA Crossover (Експоненційне)</option>
                        <option value="RSI">RSI (Індекс відносної сили)</option>
                        <option value="MACD">MACD (Розбіжність ковзних середніх)</option>
                        <option value="BOLLINGER">Bollinger Bands (Смуги Боллінджера)</option>
                    </select>
                </div>
                
                <div id="paramsBox" class="mb-4"></div>
                
                <div class="grid grid-cols-2 gap-4 mb-6 relative">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Від (дата)</label>
                        <input type="date" id="startDate" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-sm text-white outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">До (дата)</label>
                        <input type="date" id="endDate" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-sm text-white outline-none focus:border-blue-500">
                    </div>
                    <p class="text-[10px] text-gray-500 col-span-2 mt-1">*Standard акаунт: до 30 днів історії</p>
                </div>

                <button id="startBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition shadow-lg">Запустити бектест</button>
            </div>

            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 flex flex-col items-center text-center lg:col-span-2 min-h-[350px] relative w-full overflow-hidden shadow-lg">
                
                <div id="idleState" class="text-gray-500 mt-16">
                    <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <p>Очікування запуску...</p>
                </div>

                <div id="processingState" class="hidden w-full mt-16">
                    <h3 class="text-lg font-bold text-blue-400 mb-2">Обчислення в C++</h3>
                    <p id="taskIdDisplay" class="text-xs text-gray-500 mb-4 font-mono truncate px-2"></p>
                    <div class="w-full bg-gray-700 rounded-full h-2.5 mb-4">
                        <div class="bg-blue-600 h-2.5 rounded-full w-1/2 animate-pulse"></div>
                    </div>
                    <p class="text-sm text-gray-400">Синхронізація через SSE...</p>
                </div>

                <div id="completedState" class="hidden w-full h-full flex flex-col">
                    <div class="bg-blue-900/30 text-blue-400 text-xs py-1 px-3 rounded-full mx-auto mb-3 border border-blue-800" id="activeStrategyDisplay">
                        Стратегія
                    </div>

                    <div class="flex justify-between w-full mb-4">
                        <div class="text-left">
                            <p class="text-xs text-gray-400">Стартовий баланс</p>
                            <p class="font-bold text-white">$1000.00</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Зміна капіталу (PnL)</p>
                            <p id="profitDisplay" class="font-bold text-lg"></p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 w-full mb-4">
                        <div class="bg-gray-900 p-3 rounded border border-gray-700">
                            <p class="text-[10px] text-gray-400 uppercase">Угод</p>
                            <p id="tradesDisplay" class="text-lg font-bold text-blue-400"></p>
                        </div>
                        <div class="bg-gray-900 p-3 rounded border border-gray-700">
                            <p class="text-[10px] text-gray-400 uppercase">Win Rate</p>
                            <p id="winRateDisplay" class="text-lg font-bold text-green-400"></p>
                        </div>
                        <div class="bg-gray-900 p-3 rounded border border-gray-700">
                            <p class="text-[10px] text-gray-400 uppercase">Просідання</p>
                            <p id="drawdownDisplay" class="text-lg font-bold text-red-400"></p>
                        </div>
                    </div>
                    
                    <div class="flex-grow w-full h-48 relative">
                        <canvas id="equityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 mt-8 shadow-lg">
            <h2 class="text-xl font-semibold mb-4 text-gray-300">Історія бектестів</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-400">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-900 border-b border-gray-700">
                        <tr>
                            <th class="px-4 py-3">Дата</th>
                            <th class="px-4 py-3">Пара</th>
                            <th class="px-4 py-3">Стратегія та Параметри</th>
                            <th class="px-4 py-3">Прибуток</th>
                            <th class="px-4 py-3">Win Rate</th>
                            <th class="px-4 py-3 text-right">Дія</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="divide-y divide-gray-800"></tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>