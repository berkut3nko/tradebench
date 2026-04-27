/**
 * @brief TradeBench Dashboard Application Logic with A/B Comparison Support
 */

let equityChartInstance = null;
let currentTaskId = null;
let displayedTaskId = null;
let isCurrentTaskOptimized = false; 

// A/B Testing State
let compareTaskId = null;
window.appHistory = []; 

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('jwt_token')) {
        showDashboard();
    } else {
        document.getElementById('authView').classList.remove('hidden');
        document.getElementById('dashboardView').classList.add('hidden');
    }
    
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    
    if (document.getElementById('startDate')) document.getElementById('startDate').value = thirtyDaysAgo;
    if (document.getElementById('endDate')) document.getElementById('endDate').value = today;

    document.getElementById('strategy')?.addEventListener('change', renderStrategyParams);
    renderStrategyParams();
    
    document.getElementById('startBtn')?.addEventListener('click', () => executeTask(false));
});

// ==========================================
// CORE UI & AUTH STATE MANAGEMENT
// ==========================================

window.handleAuth = async function(type) {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const errEl = document.getElementById('authError');
    const succEl = document.getElementById('authSuccess');
    
    errEl.classList.add('hidden');
    succEl.classList.add('hidden');

    try {
        const res = await fetch(`/api/auth/${type}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();
        
        if (!res.ok) throw new Error(data.error || 'Помилка авторизації');
        
        if (type === 'login') {
            localStorage.setItem('jwt_token', data.token);
            localStorage.setItem('user_role', data.role);
            showDashboard();
        } else {
            succEl.textContent = 'Реєстрація успішна! Тепер ви можете увійти.';
            succEl.classList.remove('hidden');
        }
    } catch (err) {
        errEl.textContent = err.message;
        errEl.classList.remove('hidden');
    }
};

window.logout = function() {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('user_role');
    window.location.reload();
};

function showDashboard() {
    document.getElementById('authView').classList.add('hidden');
    document.getElementById('dashboardView').classList.remove('hidden');
    
    const role = localStorage.getItem('user_role');
    const badge = document.getElementById('roleBadge');
    
    if (role === 'admin') {
        badge.textContent = 'ADMIN';
        badge.className = 'px-3 py-1 rounded text-xs font-bold uppercase tracking-wider bg-red-500/20 text-red-400 border border-red-500/50';
        badge.classList.remove('hidden');
        document.getElementById('adminPanelBtn')?.classList.remove('hidden');
    } else if (role === 'pro') {
        badge.textContent = 'PRO';
        badge.className = 'px-3 py-1 rounded text-xs font-bold uppercase tracking-wider bg-purple-500/20 text-purple-400 border border-purple-500/50';
        badge.classList.remove('hidden');
        document.getElementById('optimizeBtn')?.classList.remove('hidden');
        document.getElementById('optimizeDesc')?.classList.remove('hidden');
        document.getElementById('optimizeProLock')?.classList.add('hidden');
        document.getElementById('aiProLock')?.classList.add('hidden');
        document.getElementById('aiContent')?.classList.remove('hidden');
    } else {
        badge.textContent = 'STANDARD';
        badge.className = 'px-3 py-1 rounded text-xs font-bold uppercase tracking-wider bg-gray-700/50 text-gray-300 border border-gray-600';
        badge.classList.remove('hidden');
    }
    
    setupEventStream();
    loadHistory();
}

function resetDashboardState() {
    currentTaskId = displayedTaskId = null;
    compareTaskId = null;
    isCurrentTaskOptimized = false;
    
    document.getElementById('completedState').classList.add('hidden');
    document.getElementById('processingState').classList.add('hidden');
    document.getElementById('idleState').classList.remove('hidden');
    
    if (equityChartInstance) {
        equityChartInstance.destroy();
        equityChartInstance = null;
    }
    
    ['profitDisplay', 'tradesDisplay', 'winRateDisplay', 'drawdownDisplay'].forEach(id => {
        if(document.getElementById(id)) document.getElementById(id).innerText = '';
    });
    
    const aiResp = document.getElementById('aiResponse');
    if (aiResp) {
        aiResp.innerHTML = '';
        aiResp.classList.add('hidden');
    }
    document.getElementById('askAiBtn')?.classList.add('hidden');
    document.getElementById('aiEmptyState')?.classList.remove('hidden');
}

function renderStrategyParams() {
    const strat = document.getElementById('strategy').value;
    const box = document.getElementById('paramsBox');
    let html = '';
    
    if (strat === 'SMA_CROSS' || strat === 'EMA_CROSS') {
        html = `
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-gray-400 mb-1">Fast Window</label><input type="number" id="p_fast" value="9" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
                <div><label class="block text-xs text-gray-400 mb-1">Slow Window</label><input type="number" id="p_slow" value="21" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
            </div>`;
    } else if (strat === 'RSI') {
        html = `
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-xs text-gray-400 mb-1">Period</label><input type="number" id="p_period" value="14" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
                <div><label class="block text-xs text-gray-400 mb-1">Overbought</label><input type="number" id="p_ob" value="70" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
                <div><label class="block text-xs text-gray-400 mb-1">Oversold</label><input type="number" id="p_os" value="30" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
            </div>`;
    } else if (strat === 'MACD') {
        html = `
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-xs text-gray-400 mb-1">Fast</label><input type="number" id="p_fast" value="12" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
                <div><label class="block text-xs text-gray-400 mb-1">Slow</label><input type="number" id="p_slow" value="26" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
                <div><label class="block text-xs text-gray-400 mb-1">Signal</label><input type="number" id="p_signal" value="9" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
            </div>`;
    } else if (strat === 'BOLLINGER') {
        html = `
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-gray-400 mb-1">Period</label><input type="number" id="p_period" value="20" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
                <div><label class="block text-xs text-gray-400 mb-1">Std Dev</label><input type="number" step="0.1" id="p_std" value="2.0" class="w-full bg-gray-950 border border-gray-700 rounded p-1.5 text-sm text-white"></div>
            </div>`;
    }
    if (box) box.innerHTML = html;
}

// ==========================================
// ANALYSIS AND SSE STREAMING
// ==========================================

function setupEventStream() {
    const token = localStorage.getItem('jwt_token');
    if (!token) return;
    
    if (window.eventSource) window.eventSource.close();
    
    window.eventSource = new EventSource(`/api/analysis/stream?token=${token}`);
    window.eventSource.onmessage = (e) => {
        const data = JSON.parse(e.data);
        if (data.status === 'COMPLETED') {
            const isWaiting = !document.getElementById('processingState').classList.contains('hidden');
            
            if (data.task_id === currentTaskId || isWaiting) {
                currentTaskId = data.task_id;
                displayedTaskId = data.task_id; 
                
                // Завантажуємо свіжі дані та одразу оновлюємо інтерфейс
                loadHistory().then(() => {
                    const latestTask = window.appHistory.find(t => t.task_id === data.task_id);
                    if (latestTask) {
                        let res = typeof latestTask.result_data === 'string' ? JSON.parse(latestTask.result_data) : latestTask.result_data;
                        displayResults(res);
                    }
                });
            } else {
                loadHistory();
            }
        }
    };
}

async function executeTask(isOptimize = false) {
    const pair = document.getElementById('pair').value;
    const timeframe = document.getElementById('timeframe').value;
    const strat = document.getElementById('strategy').value;
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    
    let strategyPayload = isOptimize ? "OPTIMIZE" : strat;
    
    if (!isOptimize) {
        if (strat === 'SMA_CROSS' || strat === 'EMA_CROSS') {
            strategyPayload += `:${document.getElementById('p_fast').value}:${document.getElementById('p_slow').value}`;
        } else if (strat === 'RSI') {
            strategyPayload += `:${document.getElementById('p_period').value}:${document.getElementById('p_ob').value}:${document.getElementById('p_os').value}`;
        } else if (strat === 'MACD') {
            strategyPayload += `:${document.getElementById('p_fast').value}:${document.getElementById('p_slow').value}:${document.getElementById('p_signal').value}`;
        } else if (strat === 'BOLLINGER') {
            strategyPayload += `:${document.getElementById('p_period').value}:${document.getElementById('p_std').value}`;
        }
    }

    try {
        const response = await fetch('/api/analysis/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('jwt_token')}` },
            body: JSON.stringify({
                pair: pair, timeframe: timeframe, start_time: start, end_time: end, strategy: strategyPayload
            })
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error);

        currentTaskId = data.task_id;
        
        document.getElementById('idleState').classList.add('hidden');
        document.getElementById('completedState').classList.add('hidden');
        document.getElementById('processingState').classList.remove('hidden');
        document.getElementById('taskIdDisplay').innerText = `ID Завдання: ${data.task_id}`;
        
        document.getElementById('aiEmptyState')?.classList.remove('hidden');
        document.getElementById('askAiBtn')?.classList.add('hidden');
        document.getElementById('aiResponse')?.classList.add('hidden');
        
        loadHistory();
        
    } catch (e) {
        alert(`Помилка виконання: ${e.message}`);
    }
}

window.startOptimization = function() {
    executeTask(true);
};

// ==========================================
// HISTORY AND A/B TESTING LOGIC
// ==========================================

async function loadHistory() {
    try {
        const res = await fetch('/api/analysis/history', {
            headers: { 'Authorization': `Bearer ${localStorage.getItem('jwt_token')}` }
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error);
        
        window.appHistory = data;
        renderHistoryTable();
    } catch (e) {
        console.error("History fetch error:", e);
    }
}

function isComparable(current, candidate) {
    if (!current || !candidate || current.task_id === candidate.task_id) return false;
    if (current.status !== 'COMPLETED' || candidate.status !== 'COMPLETED') return false;
    if (current.pair !== candidate.pair) return false;

    try {
        const r1 = typeof current.result_data === 'string' ? JSON.parse(current.result_data) : (current.result_data || {});
        const r2 = typeof candidate.result_data === 'string' ? JSON.parse(candidate.result_data) : (candidate.result_data || {});

        if (r1.timeframe !== r2.timeframe) return false;
        
        const t1 = r1.timestamps || [];
        const t2 = r2.timestamps || [];
        if (t1.length === 0 || t2.length === 0) return false;
        
        return t1[0] === t2[0] && t1[t1.length - 1] === t2[t2.length - 1];
    } catch (e) { return false; }
}

function renderHistoryTable() {
    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;

    const currentTask = window.appHistory.find(t => t.task_id === displayedTaskId);

    tbody.innerHTML = window.appHistory.map(task => {
        let res = {};
        if (task.status === 'COMPLETED' && task.result_data) {
            try { res = typeof task.result_data === 'string' ? JSON.parse(task.result_data) : task.result_data; } catch(e){}
        }

        const dateStr = new Date(task.created_at).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        
        const isCurrent = displayedTaskId === task.task_id;
        const isCompare = compareTaskId === task.task_id;
        const canCompare = isComparable(currentTask, task);

        let actionHtml = `<button onclick="viewHistoryTask('${task.task_id}')" class="text-blue-400 hover:text-blue-300 mr-2 transition text-xs font-semibold uppercase">Огляд</button>`;

        if (isCurrent) {
            actionHtml = `<span class="text-green-500 font-bold mr-3 text-[10px] uppercase tracking-wider bg-green-500/10 px-2 py-1 rounded">Основа</span>`;
        } else if (isCompare) {
            actionHtml += `<button onclick="clearCompare()" class="text-red-400 hover:text-red-300 font-bold transition text-[10px] uppercase bg-red-400/10 px-2 py-1 rounded border border-red-500/20">× Скинути A/B</button>`;
        } else if (canCompare) {
            actionHtml += `<button onclick="setCompare('${task.task_id}')" class="text-orange-400 hover:text-orange-300 font-bold transition text-[10px] uppercase bg-orange-400/10 px-2 py-1 rounded border border-orange-500/30 shadow-sm hover:shadow-orange-500/20">A/B Порівняти</button>`;
        }

        const stratName = res.strategy || '-';
        const profit = res.profit !== undefined ? `$${parseFloat(res.profit).toFixed(2)}` : '-';
        const winRate = res.win_rate !== undefined ? `${parseFloat(res.win_rate).toFixed(2)}%` : '-';
        
        let statusColor = 'text-gray-400';
        if (task.status === 'COMPLETED') statusColor = parseFloat(res.profit) >= 0 ? 'text-green-400' : 'text-red-400';
        else if (task.status === 'PENDING') statusColor = 'text-yellow-400';

        const rowBg = isCurrent ? 'bg-blue-900/10' : (isCompare ? 'bg-orange-900/10' : '');

        return `
            <tr class="border-b border-gray-800 hover:bg-gray-800/40 transition ${rowBg}">
                <td class="px-4 py-3 text-gray-500 text-xs">${dateStr}</td>
                <td class="px-4 py-3 font-bold">${task.pair}</td>
                <td class="px-4 py-3"><span class="bg-gray-800 border border-gray-700 text-gray-300 px-2 py-1 rounded text-xs font-mono">${stratName}</span></td>
                <td class="px-4 py-3 font-semibold ${statusColor}">${task.status === 'COMPLETED' ? profit : task.status}</td>
                <td class="px-4 py-3 text-gray-300">${winRate}</td>
                <td class="px-4 py-3 text-right">${actionHtml}</td>
            </tr>
        `;
    }).join('');
}

window.viewHistoryTask = function(taskId) {
    const task = window.appHistory.find(t => t.task_id === taskId);
    if (!task || task.status !== 'COMPLETED') return;
    
    let res = typeof task.result_data === 'string' ? JSON.parse(task.result_data) : task.result_data;
    
    displayedTaskId = taskId;
    isCurrentTaskOptimized = res.is_optimized || false;
    compareTaskId = null;
    
    displayResults(res);
    renderHistoryTable();
};

window.setCompare = function(taskId) {
    compareTaskId = taskId;
    
    const baseTask = window.appHistory.find(t => t.task_id === displayedTaskId);
    const compTask = window.appHistory.find(t => t.task_id === taskId);
    
    if (baseTask && compTask) {
        const r1 = typeof baseTask.result_data === 'string' ? JSON.parse(baseTask.result_data) : baseTask.result_data;
        const r2 = typeof compTask.result_data === 'string' ? JSON.parse(compTask.result_data) : compTask.result_data;
        displayResults(r1, r2);
    }
    renderHistoryTable();
};

window.clearCompare = function() {
    compareTaskId = null;
    const baseTask = window.appHistory.find(t => t.task_id === displayedTaskId);
    if (baseTask) {
        const r1 = typeof baseTask.result_data === 'string' ? JSON.parse(baseTask.result_data) : baseTask.result_data;
        displayResults(r1);
    }
    renderHistoryTable();
};

// ==========================================
// RESULTS RENDERING & CHARTING
// ==========================================

function displayResults(data, compareData = null) {
    if (!data) return;

    document.getElementById('processingState').classList.add('hidden');
    document.getElementById('idleState').classList.add('hidden');
    document.getElementById('completedState').classList.remove('hidden');
    
    const activeDisplay = document.getElementById('activeStrategyDisplay');
    if (activeDisplay) {
        activeDisplay.innerHTML = compareData 
            ? `A/B Тест: <span class="text-white">${data.strategy}</span> vs <span class="text-orange-400">${compareData.strategy}</span>`
            : `Стратегія: <span class="text-white">${data.strategy}</span> ${data.is_optimized ? '<span class="text-yellow-400 ml-2">⚡ ОПТИМІЗОВАНО</span>' : ''}`;
    }

    updateStatCard('profitDisplay', data.profit, compareData?.profit, true, '');
    updateStatCard('tradesDisplay', data.trades, compareData?.trades, false, '', true); // Is integer
    updateStatCard('winRateDisplay', data.win_rate, compareData?.win_rate, false, '%');
    updateStatCard('drawdownDisplay', data.drawdown, compareData?.drawdown, false, '%');

    renderComparisonChart(data, compareData);

    document.getElementById('aiEmptyState')?.classList.add('hidden');
    document.getElementById('askAiBtn')?.classList.remove('hidden');
    
    const aiResp = document.getElementById('aiResponse');
    if (aiResp && !compareData) {
        if (data.ai_insight) {
            aiResp.innerHTML = data.ai_insight.replace(/\n/g, '<br>');
            aiResp.classList.remove('hidden');
            document.getElementById('askAiBtn').classList.add('hidden');
        } else {
            aiResp.classList.add('hidden');
        }
    } else if (compareData && aiResp) {
         aiResp.classList.add('hidden');
         document.getElementById('askAiBtn').classList.add('hidden');
    }
}

function updateStatCard(elementId, val1, val2, isCurrency = false, suffix = '', isInt = false) {
    const el = document.getElementById(elementId);
    if (!el) return;

    const formatVal = (v) => {
        if (isCurrency) return `$${parseFloat(v).toFixed(2)}`;
        if (isInt) return parseInt(v).toString() + suffix;
        return parseFloat(v).toFixed(2) + suffix;
    };

    const v1Num = parseFloat(val1);
    
    let colorClass = 'text-white';
    if (isCurrency) colorClass = v1Num >= 0 ? 'text-green-400' : 'text-red-400';
    else if (elementId === 'winRateDisplay') colorClass = 'text-green-400';
    else if (elementId === 'drawdownDisplay') colorClass = 'text-red-400';
    else if (elementId === 'tradesDisplay') colorClass = 'text-blue-400';

    let html = `<span class="${colorClass}">${val1 !== undefined ? formatVal(val1) : '-'}</span>`;

    if (val2 !== undefined && val2 !== null) {
        html += `<div class="text-xs mt-1 font-normal flex items-center justify-center gap-1">
                    <span class="text-gray-500 italic">vs</span>
                    <span class="text-orange-400 bg-orange-400/10 px-1.5 py-0.5 rounded border border-orange-500/20 shadow-sm">${formatVal(val2)}</span>
                 </div>`;
    }
    
    el.innerHTML = html;
}

function renderComparisonChart(baseData, compareData = null) {
    const canvas = document.getElementById('equityChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (equityChartInstance) {
        equityChartInstance.destroy();
    }

    const curve1 = baseData.equity || [];
    const curve2 = compareData ? (compareData.equity || []) : [];
    
    const labels = baseData.timestamps 
        ? baseData.timestamps.map(t => {
            const d = new Date(t * 1000);
            return `${d.getDate().toString().padStart(2, '0')}.${(d.getMonth()+1).toString().padStart(2, '0')} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
          }) 
        : curve1.map((_, i) => `Крок ${i}`);

    // Розпізнаємо сегменти угод для забарвлення лінії
    const segmentColors = new Array(curve1.length).fill('#6b7280'); // Сірий за замовчуванням
    
    if (!compareData && baseData.buy_signals && baseData.sell_signals) {
        let buyIdx = 0;
        let sellIdx = 0;
        while (buyIdx < baseData.buy_signals.length) {
            let start = baseData.buy_signals[buyIdx];
            while (sellIdx < baseData.sell_signals.length && baseData.sell_signals[sellIdx] <= start) {
                sellIdx++;
            }
            let end = (sellIdx < baseData.sell_signals.length) ? baseData.sell_signals[sellIdx] : curve1.length - 1;
            
            let isProfitable = curve1[end] >= curve1[start];
            let tradeColor = isProfitable ? '#22c55e' : '#ef4444'; // Зелений (прибуток) або червоний (збиток)

            for (let i = start; i < end; i++) {
                segmentColors[i] = tradeColor;
            }
            buyIdx++;
            if (sellIdx < baseData.sell_signals.length) sellIdx++;
        }
    }

    // Додаємо точки входу та виходу з угоди тільки для основного графіка (коли немає порівняння A/B)
    const pointBgColors = [];
    const pointRadii = [];
    const pointBorderColors = [];
    
    for (let i = 0; i < curve1.length; i++) {
        if (!compareData && baseData.buy_signals && baseData.buy_signals.includes(i)) {
            pointBgColors.push('#3b82f6'); // Синій (Вхід / Buy)
            pointRadii.push(5);
            pointBorderColors.push('#ffffff');
        } else if (!compareData && baseData.sell_signals && baseData.sell_signals.includes(i)) {
            pointBgColors.push('#f97316'); // Помаранчевий (Вихід / Sell)
            pointRadii.push(5);
            pointBorderColors.push('#ffffff');
        } else {
            pointBgColors.push('#3b82f6');
            pointRadii.push(0); // Ховаємо звичайні точки
            pointBorderColors.push('transparent');
        }
    }

    const datasets = [{
        label: `Основа`,
        data: curve1,
        borderColor: compareData ? '#3b82f6' : '#6b7280', 
        backgroundColor: compareData ? 'transparent' : 'rgba(59, 130, 246, 0.05)', 
        borderWidth: 2,
        tension: 0.1,
        fill: !compareData,
        pointBackgroundColor: pointBgColors,
        pointRadius: pointRadii,
        pointBorderColor: pointBorderColors,
        pointHoverRadius: 6,
        segment: compareData ? undefined : {
            borderColor: ctx => segmentColors[ctx.p0DataIndex] || '#6b7280'
        }
    }];

    if (compareData) {
        datasets.push({
            label: `A/B Тест`,
            data: curve2,
            borderColor: '#f97316', 
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [5, 5], 
            tension: 0.1,
            fill: false,
            pointRadius: 0,
            pointHoverRadius: 4
        });
    }

    equityChartInstance = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false, 
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: '#9ca3af', usePointStyle: true, boxWidth: 8 }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: '#374151',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            let label = ` ${context.dataset.label}: $${context.parsed.y.toFixed(2)}`;
                            // Додаємо інформацію про покупку чи продаж у тултип
                            if (context.datasetIndex === 0 && !compareData) {
                                if (baseData.buy_signals && baseData.buy_signals.includes(context.dataIndex)) {
                                    label += ' (🔵 Купівля)';
                                } else if (baseData.sell_signals && baseData.sell_signals.includes(context.dataIndex)) {
                                    label += ' (🟠 Продаж)';
                                }
                            }
                            return label;
                        },
                        footer: function(tooltipItems) {
                            if (tooltipItems.length === 2) {
                                const v1 = tooltipItems[0].parsed.y;
                                const v2 = tooltipItems[1].parsed.y;
                                const diff = v2 - v1;
                                const diffClass = diff >= 0 ? '↗' : '↘';
                                return `\nРізниця (A/B - Основа): ${diffClass} $${Math.abs(diff).toFixed(2)}`;
                            }
                            return '';
                        }
                    }
                }
            },
            scales: {
                x: { 
                    grid: { display: false },
                    ticks: { color: '#6b7280', maxTicksLimit: 8 }
                },
                y: { 
                    grid: { color: 'rgba(55, 65, 81, 0.3)' },
                    ticks: { 
                        color: '#9ca3af',
                        callback: (value) => '$' + value 
                    }
                }
            }
        }
    });
}

// ==========================================
// AI INTEGRATION
// ==========================================

window.askAI = async function() {
    if (!displayedTaskId) return;
    
    const btn = document.getElementById('askAiBtn');
    const loader = document.getElementById('aiLoader');
    const resp = document.getElementById('aiResponse');
    
    btn.classList.add('hidden');
    loader.classList.remove('hidden');
    
    try {
        const res = await fetch('/api/ai/analyze-result', {
            method: 'POST', 
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('jwt_token')}` },
            body: JSON.stringify({ task_id: displayedTaskId, is_optimized: isCurrentTaskOptimized })
        });
        
        const data = await res.json();
        if (!res.ok) throw new Error(data.error);
        
        resp.innerHTML = data.insight.replace(/\n/g, '<br>');
        resp.classList.remove('hidden');
        
        loadHistory();
    } catch (e) {
        btn.classList.remove('hidden'); 
        alert(`Помилка AI: ${e.message}`);
    } finally { 
        loader.classList.add('hidden'); 
    }
};