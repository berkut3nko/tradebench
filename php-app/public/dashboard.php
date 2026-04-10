<?php
// MVC View: User Dashboard Panel
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TradeBench | Dashboard</title>
    <!-- Styles & Logic are perfectly separated from the View -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

    <!-- Auth View -->
    <div id="authView" class="auth-card">
        <h1 class="title-main">TradeBench</h1>
        <p class="subtitle">Аналітична платформа</p>
        
        <div id="authError" class="alert alert-error hidden"></div>
        <div id="authSuccess" class="alert alert-success hidden"></div>

        <form id="authForm">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" id="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Пароль</label>
                <input type="password" id="password" class="form-control" required>
                <p class="text-xs text-muted mt-2">Для реєстрації: мін. 8 символів, літери та цифри.</p>
            </div>
            <div class="flex gap-4 mt-4">
                <button type="button" onclick="handleAuth('login')" class="btn btn-primary btn-block">Увійти</button>
                <button type="button" onclick="handleAuth('register')" class="btn btn-secondary btn-block">Реєстрація</button>
            </div>
        </form>
    </div>

    <!-- Main Dashboard View -->
    <div id="dashboardView" class="container hidden">
        
        <header class="app-header flex-between">
            <div class="flex items-center gap-4">
                <h1 class="title-main" style="margin:0;">TradeBench <span>Control Center</span></h1>
                <span id="roleBadge" class="badge hidden"></span>
            </div>
            <div class="flex items-center gap-4">
                <button id="adminPanelBtn" onclick="window.location.href='admin.php'" class="btn btn-admin hidden">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Адмін-панель
                </button>
                <button onclick="logout()" class="btn btn-text">Вийти з акаунта</button>
            </div>
        </header>

        <div class="grid-layout">
            
            <!-- Config Panel -->
            <div class="panel">
                <h2 class="section-title text-blue">Аналітика</h2>
                
                <div class="form-group">
                    <label class="form-label">Валютна пара</label>
                    <select id="pair" class="form-control">
                        <option value="BTCUSDT">BTC/USDT</option>
                        <option value="EURUSDT">EUR/USDT</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Таймфрейм</label>
                    <select id="timeframe" class="form-control">
                        <option value="1h">1 Година (1h)</option>
                        <option value="15m">15 Хвилин (15m) — [PRO]</option>
                        <option value="4h">4 Години (4h) — [PRO]</option>
                        <option value="1d">1 День (1d) — [PRO]</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Основа стратегії</label>
                    <select id="strategy" class="form-control">
                        <option value="SMA_CROSS">SMA Crossover</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div>
                        <label class="form-label text-xs">Fast SMA</label>
                        <input type="number" id="fastSma" value="9" min="2" max="50" class="form-control">
                    </div>
                    <div>
                        <label class="form-label text-xs">Slow SMA</label>
                        <input type="number" id="slowSma" value="21" min="10" max="200" class="form-control">
                    </div>
                </div>
                
                <div class="form-row" style="position: relative;">
                    <div>
                        <label class="form-label text-xs">Від (дата)</label>
                        <input type="date" id="startDate" class="form-control">
                    </div>
                    <div>
                        <label class="form-label text-xs">До (дата)</label>
                        <input type="date" id="endDate" class="form-control">
                    </div>
                    <p class="text-xs text-muted" style="position: absolute; bottom: -20px; left: 0;">*Standard акаунт: до 30 днів історії</p>
                </div>

                <button id="startBtn" class="btn btn-primary btn-block">Запустити бектест</button>
            </div>

            <!-- Visualization Panel -->
            <div class="panel panel-large text-center flex-center">
                
                <div id="idleState" class="empty-state">
                    <svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <p>Очікування запуску...</p>
                </div>

                <div id="processingState" class="hidden w-full" style="margin-top: 4rem;">
                    <h3 class="text-blue text-lg font-bold mb-2">Обчислення в C++</h3>
                    <p id="taskIdDisplay" class="text-xs text-muted font-mono mb-4">-</p>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p class="text-sm text-muted">Синхронізація через SSE...</p>
                </div>

                <div id="completedState" class="hidden w-full flex-col" style="height: 100%; display: flex;">
                    
                    <div class="badge badge-standard" id="activeStrategyDisplay" style="margin: 0 auto 1rem auto; width: fit-content;">Стратегія</div>

                    <div class="flex-between mb-4">
                        <div class="text-left">
                            <p class="text-xs text-muted">Стартовий баланс</p>
                            <p class="font-bold text-white">$1000.00</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-muted">Зміна капіталу (PnL)</p>
                            <p id="profitDisplay" class="font-bold text-lg"></p>
                        </div>
                    </div>
                    
                    <div class="result-grid">
                        <div class="result-box">
                            <p class="text-xs text-muted uppercase">Угод</p>
                            <p id="tradesDisplay" class="result-val text-blue"></p>
                        </div>
                        <div class="result-box">
                            <p class="text-xs text-muted uppercase">Win Rate</p>
                            <p id="winRateDisplay" class="result-val text-green"></p>
                        </div>
                        <div class="result-box">
                            <p class="text-xs text-muted uppercase">Просідання</p>
                            <p id="drawdownDisplay" class="result-val text-red"></p>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="equityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="panel mt-8">
            <h2 class="section-title text-white mb-4">Історія бектестів</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Пара</th>
                            <th>Стратегія та Параметри</th>
                            <th>Прибуток</th>
                            <th>Win Rate</th>
                            <th class="text-right">Дія</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody"></tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>