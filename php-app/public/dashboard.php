<?php
// MVC View: User Dashboard Panel
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TradeBench | Dashboard</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-900 text-white font-sans min-h-screen p-8">

    <!-- Auth View -->
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

    <!-- Main Dashboard View -->
    <div id="dashboardView" class="hidden max-w-[1400px] mx-auto">
        
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

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
            
            <!-- Ліва панель налаштувань -->
            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 xl:col-span-1 h-fit shadow-lg">
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
                        <!-- Видалили OPTIMIZE звідси -->
                        <option value="SMA_CROSS">SMA Crossover (Ковзне середнє)</option>
                        <option value="EMA_CROSS">EMA Crossover (Експоненційне)</option>
                        <option value="RSI">RSI (Індекс відносної сили)</option>
                        <option value="MACD">MACD (Розбіжність ковзних середніх)</option>
                        <option value="BOLLINGER">Bollinger Bands (Смуги Боллінджера)</option>
                    </select>
                </div>
                
                <!-- Dynamic Parameter Box -->
                <div id="paramsBox" class="mb-4">
                    <!-- JS буде вставляти сюди гріди -->
                </div>
                
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

                <!-- ВІДОКРЕМЛЕНИЙ БЛОК ГЕНЕТИЧНОГО АЛГОРИТМУ -->
                <div class="mt-6 pt-6 border-t border-gray-700">
                    <div id="optimizeProLock" class="flex flex-col items-center justify-center text-center p-3 bg-gray-900/50 rounded-lg border border-gray-700">
                        <p class="text-xs text-gray-400 font-bold"><span class="text-sm">🔒</span> Автопідбір (PRO)</p>
                    </div>
                    <button id="optimizeBtn" onclick="startOptimization()" class="hidden w-full bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white font-bold py-2 px-4 rounded transition shadow-lg flex justify-center items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        Генетичний Автопідбір
                    </button>
                    <p id="optimizeDesc" class="hidden text-[10px] text-gray-500 text-center mt-2 leading-tight">ШІ самостійно еволюційним методом перебере тисячі комбінацій для пошуку найкращого результату.</p>
                </div>

            </div>

            <!-- Центральна панель візуалізації -->
            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 flex flex-col items-center text-center xl:col-span-2 min-h-[350px] relative w-full overflow-hidden shadow-lg">
                
                <div id="idleState" class="text-gray-500 mt-16">
                    <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <p>Очікування запуску...</p>
                </div>

                <div id="processingState" class="hidden w-full mt-16">
                    <h3 id="processingTitle" class="text-lg font-bold text-blue-400 mb-2">Обчислення в C++</h3>
                    <p id="taskIdDisplay" class="text-xs text-gray-500 mb-4 font-mono truncate px-2"></p>
                    <div class="w-full bg-gray-700 rounded-full h-2.5 mb-4">
                        <div class="bg-blue-600 h-2.5 rounded-full w-1/2 animate-pulse"></div>
                    </div>
                    <p id="processingDesc" class="text-sm text-gray-400">Синхронізація через SSE...</p>
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

            <!-- ПРАВА ПАНЕЛЬ ШІ -->
            <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 xl:col-span-1 h-fit shadow-lg flex flex-col">
                <h2 class="text-xl font-semibold mb-4 text-purple-400 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    TradeBench AI
                </h2>
                
                <!-- Блок для звичайних користувачів -->
                <div id="aiProLock" class="flex flex-col items-center justify-center text-center p-6 bg-gray-900/50 rounded-lg border border-gray-700 mt-4">
                    <span class="text-4xl mb-3">🔒</span>
                    <p class="text-sm text-gray-400 font-bold mb-1">Доступно з підпискою PRO</p>
                    <p class="text-xs text-gray-500">Штучний інтелект проаналізує ваші угоди та дасть експертну оцінку стратегії.</p>
                </div>

                <!-- Блок для PRO користувачів -->
                <div id="aiContent" class="hidden flex-col flex-grow">
                    <div id="aiEmptyState" class="text-sm text-gray-500 text-center py-8">
                        Запустіть бектест або виберіть графік з історії, щоб отримати оцінку ШІ.
                    </div>
                    
                    <button id="askAiBtn" onclick="askAI()" class="w-full bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white font-bold py-2 px-4 rounded transition shadow-lg mb-4 hidden">
                        ✨ Аналізувати результат
                    </button>
                    
                    <div id="aiLoader" class="hidden text-center text-purple-400 py-4">
                        <svg class="w-6 h-6 animate-spin mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span class="text-xs">Генерація висновку...</span>
                    </div>
                    
                    <div id="aiResponse" class="text-sm text-gray-300 bg-gray-900/80 p-4 rounded-lg border border-purple-500/30 hidden leading-relaxed">
                    </div>
                </div>
            </div>

        </div>

        <!-- Історія -->
        <div class="mt-8 bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg">
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

    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>