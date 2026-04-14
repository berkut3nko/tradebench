let equityChartInstance = null;
let currentTaskId = null;
let displayedTaskId = null;
let isCurrentTaskOptimized = false; 

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('jwt_token')) showDashboard();
    
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    
    if (document.getElementById('startDate')) document.getElementById('startDate').value = thirtyDaysAgo;
    if (document.getElementById('endDate')) document.getElementById('endDate').value = today;

    document.getElementById('strategy')?.addEventListener('change', renderStrategyParams);
    renderStrategyParams();
    
    document.getElementById('startBtn')?.addEventListener('click', () => executeTask(false));
});

function resetDashboardState() {
    currentTaskId = displayedTaskId = null;
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
    document.getElementById('activeStrategyDisplay').innerText = 'Strategy';

    document.getElementById('askAiBtn')?.classList.add('hidden');
    const aiResp = document.getElementById('aiResponse');
    if (aiResp) { aiResp.classList.add('hidden'); aiResp.innerText = ''; }
    document.getElementById('aiEmptyState')?.classList.remove('hidden');
}

function renderStrategyParams() {
    const strategy = document.getElementById('strategy')?.value;
    const box = document.getElementById('paramsBox');
    if (!strategy || !box) return;
    
    const layouts = {
        'SMA_CROSS': { cols: 2, fields: [{l:'Fast Period', v:9}, {l:'Slow Period', v:21}] },
        'EMA_CROSS': { cols: 2, fields: [{l:'Fast Period', v:9}, {l:'Slow Period', v:21}] },
        'RSI': { cols: 3, fields: [{l:'Period', v:14}, {l:'Overbought', v:70}, {l:'Oversold', v:30}] },
        'MACD': { cols: 3, fields: [{l:'Fast EMA', v:12}, {l:'Slow EMA', v:26}, {l:'Signal EMA', v:9}] },
        'BOLLINGER': { cols: 2, fields: [{l:'Period', v:20}, {l:'StdDev', v:2.0, step:0.1}] }
    };
    
    const config = layouts[strategy];
    box.className = `grid grid-cols-${config.cols} gap-4 bg-gray-900/50 p-3 rounded border border-gray-700`;
    box.innerHTML = config.fields.map((f, i) => `
        <div>
            <label class="block text-xs text-gray-400 mb-1">${f.l}</label>
            <input type="number" id="param${i+1}" value="${f.v}" ${f.step ? `step="${f.step}"` : ''} class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500">
        </div>
    `).join('');
}

function formatStrategyName(rawName) {
    if (!rawName) return 'Unknown';
    if (rawName.includes(':')) {
        const parts = rawName.split(':');
        return `${parts[0].replace('_CROSS', ' Crossover')} (${parts.slice(1).join(', ')})`;
    }
    return rawName;
}

async function handleAuth(action) {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const err = document.getElementById('authError');
    const succ = document.getElementById('authSuccess');
    
    err.classList.add('hidden'); succ.classList.add('hidden');
    if (!email || !password) return (err.innerText = "Всі поля обов'язкові", err.classList.remove('hidden'));

    try {
        const response = await fetch(`/api/auth/${action}`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error);
        
        if (action === 'register') {
            succ.innerText = "Успішна реєстрація!"; succ.classList.remove('hidden');
            document.getElementById('password').value = '';
        } else { 
            localStorage.setItem('jwt_token', data.token); 
            localStorage.setItem('user_role', data.role); 
            showDashboard(); 
        }
    } catch (e) {
        err.innerText = e.message; err.classList.remove('hidden');
    }
}

function showDashboard() {
    document.getElementById('authView').classList.add('hidden');
    document.getElementById('dashboardView').classList.remove('hidden');
    
    const role = localStorage.getItem('user_role') || 'standard';
    const isPro = role === 'admin' || role === 'pro';
    
    const badge = document.getElementById('roleBadge');
    badge.classList.remove('hidden');
    badge.innerText = role.toUpperCase();
    
    document.getElementById('adminPanelBtn')?.classList.toggle('hidden', role !== 'admin');
    document.getElementById('aiProLock')?.classList.toggle('hidden', isPro);
    document.getElementById('aiContent')?.classList.toggle('hidden', !isPro);
    document.getElementById('optimizeProLock')?.classList.toggle('hidden', isPro);
    document.getElementById('optimizeBtn')?.classList.toggle('hidden', !isPro);
    document.getElementById('optimizeDesc')?.classList.toggle('hidden', !isPro);

    setupEventStream();
    loadHistory();
}

function logout() {
    localStorage.clear();
    window.location.reload();
}

async function loadHistory() {
    const token = localStorage.getItem('jwt_token');
    if (!token) return;
    try {
        const res = await fetch('/api/analysis/history', { headers: { 'Authorization': `Bearer ${token}` } });
        const data = await res.json();
        
        document.getElementById('historyTableBody').innerHTML = data.map(task => {
            const rd = task.result_data ? JSON.parse(task.result_data) : null;
            const isAct = task.task_id === displayedTaskId;
            const profitStr = rd ? `${rd.profit >= 0 ? '+' : ''}$${rd.profit.toFixed(2)}` : '-';
            const stratStr = rd ? `[${rd.timeframe||''}] ${formatStrategyName(rd.strategy)} ${rd.is_optimized?'🧬':''} ${rd.ai_insight?'✨':''}` : '';
            
            const btnHtml = isAct ? 
                `<span class="text-blue-400 text-xs bg-blue-900/30 px-3 py-1.5 rounded border border-blue-500 font-bold uppercase tracking-wider">Активний</span>` : 
                (rd ? `<button onclick='viewHistoricalChart(this, "${task.task_id}")' data-result='${task.result_data.replace(/'/g, "&#39;")}' class="bg-gray-700 hover:bg-blue-600 px-3 py-1.5 rounded text-xs font-bold mr-3 transition duration-200 shadow">Переглянути</button>` : '');

            const trashIcon = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>`;

            /* Correct Win Rate styling based on trades count */
            const wrColor = (rd?.trades > 0) ? (rd.win_rate >= 50 ? 'text-green-400' : 'text-red-400') : 'text-gray-500';

            return `
                <tr class="${isAct ? 'bg-gray-800/90' : 'hover:bg-gray-750'} transition-colors">
                    <td class="px-4 py-4 text-gray-300 ${isAct ? 'border-l-4 border-blue-500' : 'border-l-4 border-transparent'}">${new Date(task.created_at).toLocaleString('uk-UA')}</td>
                    <td class="px-4 py-4 font-bold text-white">${task.pair}</td>
                    <td class="px-4 py-4 font-mono text-xs text-gray-300">${stratStr}</td>
                    <td class="px-4 py-4 font-bold ${rd?.profit >= 0 ? 'text-green-400' : 'text-red-400'}">${profitStr}</td>
                    <td class="px-4 py-4 font-bold ${wrColor}">${rd?.win_rate?.toFixed(1) || '-'}%</td>
                    <td class="px-4 py-4 text-right flex justify-end items-center">
                        ${btnHtml}
                        <button onclick='deleteBacktest("${task.task_id}")' class="text-red-400 hover:text-red-300 transition p-1" title="Видалити бектест">
                            ${trashIcon}
                        </button>
                    </td>
                </tr>`;
        }).join('');
    } catch (e) {}
}

async function deleteBacktest(taskId) {
    if (!confirm('Ви впевнені, що хочете видалити цей бектест?')) return;
    try {
        await fetch(`/api/analysis/history/${taskId}`, {
            method: 'DELETE', headers: { 'Authorization': `Bearer ${localStorage.getItem('jwt_token')}` }
        });
        if (taskId === displayedTaskId || taskId === currentTaskId) resetDashboardState();
        loadHistory();
    } catch (e) {}
}

window.viewHistoricalChart = function(btn, taskId) {
    displayedTaskId = taskId; 
    const resultData = JSON.parse(btn.getAttribute('data-result'));
    isCurrentTaskOptimized = resultData.is_optimized === true;
    displayResults(resultData);
    loadHistory();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

async function executeTask(isOptimized) {
    const pair = document.getElementById('pair').value;
    const timeframe = document.getElementById('timeframe').value;
    const strategy = isOptimized ? 'OPTIMIZE' : document.getElementById('strategy').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    let params = [];
    if (!isOptimized) {
        [1, 2, 3].forEach(i => {
            const p = document.getElementById(`param${i}`);
            if (p) params.push(p.value);
        });
    }

    resetDashboardState();
    isCurrentTaskOptimized = isOptimized;
    
    document.getElementById('idleState').classList.add('hidden');
    document.getElementById('processingState').classList.remove('hidden');
    document.getElementById('processingTitle').innerHTML = isOptimized ? '<span class="text-purple-400">🧬 Evolution Engine Running</span>' : 'Обчислення в C++ Ядрі';
    
    document.getElementById('startBtn').disabled = true;
    if (document.getElementById('optimizeBtn')) document.getElementById('optimizeBtn').disabled = true;

    try {
        const response = await fetch('/api/analysis/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('jwt_token')}` },
            body: JSON.stringify({ pair, timeframe, strategy, params, startDate, endDate })
        });
        
        if (response.status === 401) { logout(); return; }
        const data = await response.json();
        
        if (!response.ok) {
            alert(response.status === 403 ? `🛑 Відмовлено в доступі:\n${data.error}` : data.error);
            throw new Error();
        }
        
        currentTaskId = data.task_id;
        document.getElementById('taskIdDisplay').innerText = `ID Завдання: ${currentTaskId}`;
    } catch (e) {
        resetDashboardState();
        document.getElementById('startBtn').disabled = false;
        if (document.getElementById('optimizeBtn')) document.getElementById('optimizeBtn').disabled = false;
    }
}

window.startOptimization = () => executeTask(true);

function renderChart(equityData, buySignals = [], sellSignals = [], timestamps = []) {
    const ctx = document.getElementById('equityChart').getContext('2d');
    if (equityChartInstance) equityChartInstance.destroy();

    /* Determine line segment colors based on trade profitability */
    const segmentColors = new Array(equityData.length).fill('#4b5563'); /* Default Gray (No active trade) */
    let activeBuyIdx = -1;

    for (let i = 0; i < equityData.length; i++) {
        if (buySignals.includes(i)) {
            activeBuyIdx = i;
        }
        if (sellSignals.includes(i) && activeBuyIdx !== -1) {
            let isProfit = equityData[i] >= equityData[activeBuyIdx];
            let color = isProfit ? '#10b981' : '#ef4444'; /* Green or Red segment */
            for (let j = activeBuyIdx; j < i; j++) {
                segmentColors[j] = color;
            }
            activeBuyIdx = -1;
        }
    }
    
    /* Handle unclosed active trade at the end of the data */
    if (activeBuyIdx !== -1) {
        let isProfit = equityData[equityData.length - 1] >= equityData[activeBuyIdx];
        let color = isProfit ? '#10b981' : '#ef4444';
        for (let j = activeBuyIdx; j < equityData.length - 1; j++) {
            segmentColors[j] = color;
        }
    }

    /* Determine Point Colors: Blue (Buy), Orange (Sell) */
    const ptColors = equityData.map((_, i) => {
        if (buySignals.includes(i) && sellSignals.includes(i)) return '#a855f7'; /* Purple fallback */
        if (buySignals.includes(i)) return '#3b82f6'; /* Blue for Buy */
        if (sellSignals.includes(i)) return '#f97316'; /* Orange for Sell */
        return 'transparent';
    });

    const ptRadii = equityData.map((_, i) => {
        if (sellSignals.includes(i)) return 3; /* Зменшено радіус точок для кращої видимості графіка */
        if (buySignals.includes(i)) return 3;
        return 0;
    });

    /* Helper function for precise date formatting */
    const formatChartDate = (ts) => {
        const d = new Date(ts * 1000);
        const day = d.getDate().toString().padStart(2, '0');
        const month = (d.getMonth() + 1).toString().padStart(2, '0');
        const hours = d.getHours().toString().padStart(2, '0');
        const minutes = d.getMinutes().toString().padStart(2, '0');
        return `${day}.${month} ${hours}:${minutes}`;
    };

    equityChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: equityData.map((_, i) => i),
            datasets: [{
                label: 'Капітал ($)',
                data: equityData,
                /* Apply calculated segment colors */
                segment: {
                    borderColor: ctx => segmentColors[ctx.p0DataIndex] || '#4b5563'
                },
                borderWidth: 2,
                pointRadius: ptRadii,
                pointHoverRadius: 5, /* Трохи збільшується при наведенні миші */
                pointBackgroundColor: ptColors,
                pointBorderColor: ptColors.map(c => c === 'transparent' ? 'transparent' : '#ffffff'),
                pointBorderWidth: 1,
                fill: false, 
                tension: 0.1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                /* Advanced Tooltip formatting for clear insights */
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#9ca3af',
                    bodyColor: '#ffffff',
                    borderColor: '#374151',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: function(context) {
                            const idx = context[0].dataIndex;
                            if (timestamps && timestamps.length > idx) {
                                return formatChartDate(timestamps[idx]);
                            }
                            return `Крок ${idx}`;
                        },
                        label: function(context) {
                            const idx = context.dataIndex;
                            const val = context.raw;
                            let lines = [`Баланс: $${val.toFixed(2)}`];
                            
                            if (buySignals.includes(idx)) {
                                lines.push('🔵 Вхід в угоду (Купівля)');
                            }
                            if (sellSignals.includes(idx)) {
                                /* Find corresponding buy to calculate exact PnL */
                                let entryVal = val;
                                for (let k = idx; k >= 0; k--) {
                                    if (buySignals.includes(k)) {
                                        entryVal = equityData[k];
                                        break;
                                    }
                                }
                                let diff = val - entryVal;
                                let sign = diff > 0 ? '+' : '';
                                let statusMsg = diff > 0 ? 'ПРИБУТКОВО' : (diff < 0 ? 'ЗБИТКОВО' : 'БЕЗ ЗМІН');
                                lines.push(`🟠 Вихід з угоди (Продаж)`);
                                lines.push(`💰 Результат: ${sign}$${diff.toFixed(2)} (${statusMsg})`);
                            }
                            return lines;
                        }
                    }
                }
            },
            scales: {
                x: { 
                    ticks: { 
                        color: '#9ca3af',
                        maxRotation: 45, /* Кут нахилу для дат, щоб не налізали одна на одну */
                        minRotation: 45,
                        callback: function(val, i) {
                            if (!timestamps.length) return i % Math.floor(equityData.length/10) === 0 ? `Step ${i}` : null;
                            if (i % Math.floor(equityData.length/8) === 0) {
                                return formatChartDate(timestamps[i]);
                            }
                            return null;
                        }
                    },
                    grid: { color: '#374151' }
                },
                y: { ticks: { color: '#9ca3af' }, grid: { color: '#374151' } }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            }
        }
    });
}

function displayResults(data) {
    document.getElementById('idleState').classList.add('hidden');
    document.getElementById('processingState').classList.add('hidden');
    document.getElementById('completedState').classList.remove('hidden');
    
    document.getElementById('startBtn').disabled = false;
    if (document.getElementById('optimizeBtn')) document.getElementById('optimizeBtn').disabled = false;
    
    document.getElementById('activeStrategyDisplay').innerText = `[${data.timeframe||''}] ${formatStrategyName(data.strategy)}`;
    
    const pnl = document.getElementById('profitDisplay');
    pnl.innerText = `${data.profit >= 0 ? '+' : ''}$${data.profit.toFixed(2)}`;
    pnl.className = `font-bold text-lg ${data.profit >= 0 ? 'text-green-400' : 'text-red-400'}`;
    
    document.getElementById('tradesDisplay').innerText = data.trades || 0;
    
    /* Correct Win Rate styling based on trades count */
    const wr = data.win_rate || 0;
    const trades = data.trades || 0;
    const wrDisplay = document.getElementById('winRateDisplay');
    if (wrDisplay) {
        wrDisplay.innerText = `${wr.toFixed(1)}%`;
        if (trades > 0) {
            wrDisplay.className = `text-lg font-bold ${wr >= 50 ? 'text-green-400' : 'text-red-400'}`;
        } else {
            wrDisplay.className = `text-lg font-bold text-gray-500`;
        }
    }

    const ddDisplay = document.getElementById('drawdownDisplay');
    if (ddDisplay) {
        ddDisplay.innerText = `-${(data.drawdown||0).toFixed(1)}%`;
    }
    
    if (data.equity?.length > 0) renderChart(data.equity, data.buy_signals, data.sell_signals, data.timestamps);

    if (displayedTaskId && document.getElementById('askAiBtn')) {
        document.getElementById('aiEmptyState').classList.add('hidden');
        const aiResp = document.getElementById('aiResponse');
        if (data.ai_insight) {
            document.getElementById('askAiBtn').classList.add('hidden');
            aiResp.classList.remove('hidden');
            aiResp.innerHTML = data.ai_insight.replace(/\n/g, '<br>');
        } else {
            aiResp.classList.add('hidden');
            document.getElementById('askAiBtn').classList.remove('hidden');
        }
    }
}

async function askAI() {
    if (!displayedTaskId) return;
    const btn = document.getElementById('askAiBtn'), loader = document.getElementById('aiLoader'), resp = document.getElementById('aiResponse');
    
    btn.classList.add('hidden'); loader.classList.remove('hidden');
    try {
        const res = await fetch('/api/ai/analyze-result', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('jwt_token')}` },
            body: JSON.stringify({ task_id: displayedTaskId, is_optimized: isCurrentTaskOptimized })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error);
        
        resp.innerHTML = data.insight.replace(/\n/g, '<br>');
        resp.classList.remove('hidden');
        loadHistory();
    } catch (e) {
        btn.classList.remove('hidden'); alert(`AI Помилка: ${e.message}`);
    } finally { loader.classList.add('hidden'); }
}

function setupEventStream() {
    const token = localStorage.getItem('jwt_token');
    if (!token) return;
    window.eventSource = new EventSource(`/api/analysis/stream?token=${token}`);
    window.eventSource.onmessage = (e) => {
        const data = JSON.parse(e.data);
        if (data.task_id === currentTaskId && data.status === 'COMPLETED') {
            displayedTaskId = data.task_id; displayResults(data); loadHistory();
        }
    };
}