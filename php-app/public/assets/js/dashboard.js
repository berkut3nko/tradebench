let equityChartInstance = null;
let currentTaskId = null;
let displayedTaskId = null;
let isCurrentTaskOptimized = false; // Зберігає контекст походження графіка для ШІ

document.addEventListener('DOMContentLoaded', () => {
    try {
        if (localStorage.getItem('jwt_token')) showDashboard();
    } catch (e) {
        console.error("Помилка відновлення сесії:", e);
    }
    
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    
    const startDateEl = document.getElementById('startDate');
    const endDateEl = document.getElementById('endDate');
    if (startDateEl) startDateEl.value = thirtyDaysAgo;
    if (endDateEl) endDateEl.value = today;

    const startBtn = document.getElementById('startBtn');
    if (startBtn) startBtn.addEventListener('click', startAnalysis);
    
    const strategyEl = document.getElementById('strategy');
    if (strategyEl) strategyEl.addEventListener('change', renderStrategyParams);
    
    renderStrategyParams();
});

function resetDashboardState() {
    currentTaskId = null;
    displayedTaskId = null;
    isCurrentTaskOptimized = false;
    
    document.getElementById('completedState').classList.add('hidden');
    document.getElementById('processingState').classList.add('hidden');
    document.getElementById('idleState').classList.remove('hidden');
    
    if (equityChartInstance) {
        equityChartInstance.destroy();
        equityChartInstance = null;
    }
    
    document.getElementById('profitDisplay').innerText = '';
    document.getElementById('tradesDisplay').innerText = '';
    document.getElementById('winRateDisplay').innerText = '';
    document.getElementById('drawdownDisplay').innerText = '';
    document.getElementById('activeStrategyDisplay').innerText = 'Стратегія';

    const askAiBtn = document.getElementById('askAiBtn');
    const aiResponse = document.getElementById('aiResponse');
    const aiEmptyState = document.getElementById('aiEmptyState');
    
    if (askAiBtn) askAiBtn.classList.add('hidden');
    if (aiResponse) {
        aiResponse.classList.add('hidden');
        aiResponse.innerText = '';
    }
    if (aiEmptyState) aiEmptyState.classList.remove('hidden');
}

function renderStrategyParams() {
    const strategyEl = document.getElementById('strategy');
    const box = document.getElementById('paramsBox');
    
    if (!strategyEl || !box) return;
    const strategy = strategyEl.value;
    
    if (strategy === 'SMA_CROSS' || strategy === 'EMA_CROSS') {
        box.className = 'grid grid-cols-2 gap-4 bg-gray-900/50 p-3 rounded border border-gray-700';
        box.innerHTML = `
            <div><label class="block text-xs text-gray-400 mb-1">Fast Period</label><input type="number" id="param1" value="9" min="2" max="50" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Slow Period</label><input type="number" id="param2" value="21" min="10" max="200" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
        `;
    } else if (strategy === 'RSI') {
        box.className = 'grid grid-cols-3 gap-4 bg-gray-900/50 p-3 rounded border border-gray-700';
        box.innerHTML = `
            <div><label class="block text-xs text-gray-400 mb-1">Period</label><input type="number" id="param1" value="14" min="2" max="50" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Overbought</label><input type="number" id="param2" value="70" min="50" max="99" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Oversold</label><input type="number" id="param3" value="30" min="1" max="50" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
        `;
    } else if (strategy === 'MACD') {
        box.className = 'grid grid-cols-3 gap-4 bg-gray-900/50 p-3 rounded border border-gray-700';
        box.innerHTML = `
            <div><label class="block text-xs text-gray-400 mb-1">Fast EMA</label><input type="number" id="param1" value="12" min="2" max="50" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Slow EMA</label><input type="number" id="param2" value="26" min="10" max="200" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
            <div><label class="block text-xs text-gray-400 mb-1">Signal EMA</label><input type="number" id="param3" value="9" min="2" max="50" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
        `;
    } else if (strategy === 'BOLLINGER') {
        box.className = 'grid grid-cols-2 gap-4 bg-gray-900/50 p-3 rounded border border-gray-700';
        box.innerHTML = `
            <div><label class="block text-xs text-gray-400 mb-1">Period (SMA)</label><input type="number" id="param1" value="20" min="5" max="100" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
            <div><label class="block text-xs text-gray-400 mb-1">StdDev Multiplier</label><input type="number" step="0.1" id="param2" value="2.0" min="0.5" max="5.0" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500"></div>
        `;
    }
}

function formatStrategyName(rawName) {
    if (!rawName) return 'Невідомо';
    if (rawName.includes(':')) {
        const parts = rawName.split(':');
        let name = parts[0].replace('_CROSS', ' Crossover');
        parts.shift();
        return `${name} (${parts.join(', ')})`;
    }
    return rawName;
}

async function handleAuth(action) {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    
    const errorDiv = document.getElementById('authError');
    const successDiv = document.getElementById('authSuccess');
    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');

    if (!email || !password) {
        errorDiv.innerText = "Будь ласка, заповніть всі поля";
        return errorDiv.classList.remove('hidden');
    }

    if (action === 'register') {
        if (password.length < 8) {
            errorDiv.innerText = "Пароль має містити щонайменше 8 символів";
            return errorDiv.classList.remove('hidden');
        }
    }

    try {
        const response = await fetch(`/api/auth/${action}`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error);
        
        if (action === 'register') {
            successDiv.innerText = "Успішна реєстрація! Тепер ви можете увійти.";
            successDiv.classList.remove('hidden');
            document.getElementById('password').value = '';
        } else { 
            localStorage.setItem('jwt_token', data.token); 
            localStorage.setItem('user_role', data.role); 
            showDashboard(); 
        }
    } catch (error) {
        errorDiv.innerText = error.message; 
        errorDiv.classList.remove('hidden');
    }
}

function showDashboard() {
    document.getElementById('authView').classList.add('hidden');
    document.getElementById('dashboardView').classList.remove('hidden');
    
    const role = localStorage.getItem('user_role') || 'standard';
    const badge = document.getElementById('roleBadge');
    const adminBtn = document.getElementById('adminPanelBtn');
    
    const aiLock = document.getElementById('aiProLock');
    const aiContent = document.getElementById('aiContent');
    const optLock = document.getElementById('optimizeProLock');
    const optBtn = document.getElementById('optimizeBtn');
    const optDesc = document.getElementById('optimizeDesc');
    
    badge.classList.remove('hidden', 'badge-admin', 'badge-pro', 'badge-standard');
    if (adminBtn) adminBtn.classList.add('hidden');
    
    if (role === 'admin' || role === 'pro') {
        if (role === 'admin') {
            badge.innerText = 'Admin';
            badge.classList.add('badge-admin');
            if (adminBtn) adminBtn.classList.remove('hidden');
        } else {
            badge.innerText = 'PRO Account';
            badge.classList.add('badge-pro');
        }
        if (aiLock) aiLock.classList.add('hidden');
        if (aiContent) aiContent.classList.remove('hidden');
        if (optLock) optLock.classList.add('hidden');
        if (optBtn) optBtn.classList.remove('hidden');
        if (optDesc) optDesc.classList.remove('hidden');
    } else {
        badge.innerText = 'Standard';
        badge.classList.add('badge-standard');
        if (aiLock) aiLock.classList.remove('hidden');
        if (aiContent) aiContent.classList.add('hidden');
        if (optLock) optLock.classList.remove('hidden');
        if (optBtn) optBtn.classList.add('hidden');
        if (optDesc) optDesc.classList.add('hidden');
    }

    setupEventStream();
    loadHistory();
}

function logout() {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('user_role');
    document.getElementById('dashboardView').classList.add('hidden');
    document.getElementById('authView').classList.remove('hidden');
    
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('authError').classList.add('hidden');
    document.getElementById('authSuccess').classList.add('hidden');

    if (window.eventSource) window.eventSource.close();
    document.getElementById('historyTableBody').innerHTML = '';
    
    resetDashboardState();
}

async function loadHistory() {
    const token = localStorage.getItem('jwt_token');
    if (!token) return;
    try {
        const res = await fetch('/api/analysis/history', {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        const tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '';
        
        data.forEach(task => {
            let resultData = task.result_data ? JSON.parse(task.result_data) : null;
            let profitText = resultData ? `$${resultData.profit.toFixed(2)}` : '-';
            let profitClass = resultData && resultData.profit >= 0 ? 'text-green-400' : (resultData ? 'text-red-400' : '');
            
            let wrText = resultData && resultData.win_rate !== undefined ? `${resultData.win_rate.toFixed(1)}%` : '-';
            let tfBadge = resultData && resultData.timeframe ? `<span class="text-gray-500 mr-1">[${resultData.timeframe}]</span>` : '';
            
            // ВАЖЛИВО: Візуальні індикатори Генетики та ШІ
            let isOptBadge = resultData && resultData.is_optimized ? `<span title="Знадено Генетичним Автопідбором" class="text-purple-400 ml-2 cursor-help">🧬</span>` : '';
            let hasAiBadge = resultData && resultData.ai_insight ? `<span title="Наявний висновок ШІ" class="text-blue-400 ml-1 cursor-help">✨</span>` : '';
            
            let strategyName = tfBadge + formatStrategyName(resultData ? resultData.strategy : null) + isOptBadge + hasAiBadge;

            let date = new Date(task.created_at).toLocaleString('uk-UA');
            let safeJson = resultData ? task.result_data.replace(/'/g, "&#39;") : '';
            
            let actionBtn = `
                <div class="flex justify-end gap-3 items-center">
                    ${resultData ? `<button onclick='viewHistoricalChart(this, "${task.task_id}")' data-result='${safeJson}' class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded text-xs font-bold transition shadow">Графік</button>` : ''}
                    <button onclick='deleteBacktest("${task.task_id}")' class="text-red-400 hover:text-red-300 transition p-1" title="Видалити бектест">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            `;

            tbody.innerHTML += `
                <tr class="hover:bg-gray-750 transition-colors">
                    <td class="px-4 py-3 text-gray-300">${date}</td>
                    <td class="px-4 py-3 font-bold text-white">${task.pair}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-300 flex items-center">${strategyName}</td>
                    <td class="px-4 py-3 font-bold ${profitClass}">${profitText}</td>
                    <td class="px-4 py-3 text-green-400">${wrText}</td>
                    <td class="px-4 py-3 text-right">${actionBtn}</td>
                </tr>
            `;
        });
    } catch (e) {
        console.error("Failed to load history", e);
    }
}

async function deleteBacktest(taskId) {
    if (!confirm('Ви впевнені, що хочете видалити цей бектест з історії?')) return;

    const token = localStorage.getItem('jwt_token');
    try {
        const response = await fetch(`/api/analysis/history/${taskId}`, {
            method: 'DELETE',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await response.json();
        
        if (!response.ok) throw new Error(data.error);
        
        if (taskId === displayedTaskId || taskId === currentTaskId) {
            resetDashboardState();
        }
        
        loadHistory();
    } catch (error) {
        alert(`Помилка видалення: ${error.message}`);
    }
}

window.viewHistoricalChart = function(btn, taskId) {
    displayedTaskId = taskId; 
    const resultData = JSON.parse(btn.getAttribute('data-result'));
    
    isCurrentTaskOptimized = resultData.is_optimized === true;
    
    displayResults(resultData);
    
    if (resultData.strategy) {
        const parts = resultData.strategy.split(':');
        const stratName = parts[0].replace('_CROSS', '_CROSS');
        const strategySelect = document.getElementById('strategy');
        
        if (Array.from(strategySelect.options).some(opt => opt.value === stratName)) {
            strategySelect.value = stratName;
            renderStrategyParams();
            if (parts[1] && document.getElementById('param1')) document.getElementById('param1').value = parts[1];
            if (parts[2] && document.getElementById('param2')) document.getElementById('param2').value = parts[2];
            if (parts[3] && document.getElementById('param3')) document.getElementById('param3').value = parts[3];
        }
    }
    
    if (resultData.ai_insight) {
        const askAiBtn = document.getElementById('askAiBtn');
        const aiEmptyState = document.getElementById('aiEmptyState');
        const aiResponse = document.getElementById('aiResponse');
        
        if (askAiBtn) askAiBtn.classList.add('hidden');
        if (aiEmptyState) aiEmptyState.classList.add('hidden');
        if (aiResponse) {
            aiResponse.classList.remove('hidden');
            aiResponse.innerHTML = resultData.ai_insight.replace(/\n/g, '<br>');
        }
    }
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

function renderChart(equityData, buySignals = [], sellSignals = [], timestamps = [], timeframe = '1h') {
    const ctx = document.getElementById('equityChart').getContext('2d');
    if (equityChartInstance) equityChartInstance.destroy();

    const labels = equityData.map((_, i) => i);
    const totalPoints = equityData.length;

    const baseRadius = totalPoints > 500 ? 2 : 4;
    const hoverRadius = baseRadius + 3;

    const pointRadius = labels.map((_, i) => {
        if (buySignals.includes(i) || sellSignals.includes(i)) return baseRadius;
        return 0;
    });

    const pointBackgroundColor = labels.map((_, i) => {
        const isBuy = buySignals.includes(i);
        const isSell = sellSignals.includes(i);
        
        if (isBuy && isSell) return '#a855f7'; 
        if (isBuy) return '#3b82f6'; 
        if (isSell) return '#eab308'; 
        return 'transparent';
    });

    const pointBorderWidth = labels.map((_, i) => {
        if (buySignals.includes(i) || sellSignals.includes(i)) return 1;
        return 0;
    });

    equityChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Капітал ($)',
                data: equityData,
                segment: {
                    borderColor: ctx => {
                        if (!ctx.p0 || !ctx.p1) return '#4b5563'; 
                        const diff = ctx.p1.parsed.y - ctx.p0.parsed.y;
                        if (diff > 0) return '#22c55e'; 
                        if (diff < 0) return '#ef4444'; 
                        return '#4b5563'; 
                    }
                },
                borderWidth: totalPoints > 1000 ? 1 : 2,
                pointRadius: pointRadius,
                pointBackgroundColor: pointBackgroundColor,
                pointBorderColor: '#ffffff',
                pointBorderWidth: pointBorderWidth,
                pointHoverRadius: hoverRadius,
                fill: false, 
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            plugins: { 
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            const index = context[0].dataIndex;
                            if (timestamps && timestamps[index]) {
                                const d = new Date(timestamps[index] * 1000);
                                return d.toLocaleString('uk-UA');
                            }
                            return `Крок ${index}`;
                        },
                        label: function(context) {
                            const index = context.dataIndex;
                            const isBuy = buySignals.includes(index);
                            const isSell = sellSignals.includes(index);

                            let label = 'Капітал: $' + context.parsed.y.toFixed(2);
                            
                            if (isBuy && isSell) label += ' (ВХІД ТА ВИХІД)';
                            else if (isBuy) label += ' (ВХІД В УГОДУ)';
                            else if (isSell) label += ' (ВИХІД З УГОДИ)';
                            
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: { 
                    display: true, 
                    ticks: { 
                        color: '#9ca3af', 
                        font: { size: 10 },
                        maxRotation: 0,   
                        minRotation: 0,
                        autoSkip: false,  
                        callback: function(value, index) {
                            if (!timestamps || timestamps.length === 0) {
                                const step = Math.max(1, Math.floor(totalPoints / 10));
                                return index % step === 0 ? `Крок ${index}` : null;
                            }

                            const ts = timestamps[index];
                            const d = new Date(ts * 1000);
                            
                            const h = d.getHours();
                            const m = d.getMinutes();
                            const day = d.getDate();
                            const month = d.getMonth() + 1;
                            const year = d.getFullYear();

                            const hStr = h.toString().padStart(2, '0');
                            const mStr = m.toString().padStart(2, '0');
                            const dayStr = day.toString().padStart(2, '0');
                            const moStr = month.toString().padStart(2, '0');

                            const localDate = new Date(year, month - 1, day);
                            const localDays = Math.round(localDate.getTime() / 86400000); 
                            const localMonths = year * 12 + month;

                            let isNewDay = false;
                            let isNewMonth = false;
                            
                            if (index === 0) {
                                isNewDay = true;
                                isNewMonth = true;
                            } else {
                                const prevD = new Date(timestamps[index - 1] * 1000);
                                if (prevD.getDate() !== day) isNewDay = true;
                                if (prevD.getMonth() !== month - 1) isNewMonth = true;
                            }

                            let show = false;
                            let format = '';

                            if (timeframe === '15m') {
                                if (totalPoints > 2000) { 
                                    if (isNewDay && localDays % 2 === 0) { show = true; format = `${dayStr}.${moStr}`; }
                                } else if (totalPoints > 1000) { 
                                    if (isNewDay) { show = true; format = `${dayStr}.${moStr}`; }
                                } else if (totalPoints > 300) { 
                                    if (h % 12 === 0 && m === 0) { show = true; format = `${hStr}:00\n${dayStr}.${moStr}`; }
                                } else { 
                                    if (h % 4 === 0 && m === 0) { show = true; format = `${hStr}:00\n${dayStr}.${moStr}`; }
                                }
                            } else if (timeframe === '1h') {
                                if (totalPoints > 2000) { 
                                    if (isNewDay && localDays % 7 === 0) { show = true; format = `${dayStr}.${moStr}`; }
                                } else if (totalPoints > 700) { 
                                    if (isNewDay && localDays % 3 === 0) { show = true; format = `${dayStr}.${moStr}`; }
                                } else if (totalPoints > 168) { 
                                    if (isNewDay) { show = true; format = `${dayStr}.${moStr}`; }
                                } else { 
                                    if (h % 6 === 0) { show = true; format = `${hStr}:00\n${dayStr}.${moStr}`; }
                                }
                            } else if (timeframe === '4h') {
                                if (totalPoints > 1000) { 
                                    if (isNewMonth) { show = true; format = `${moStr}.${year}`; }
                                } else if (totalPoints > 180) { 
                                    if (isNewDay && localDays % 7 === 0) { show = true; format = `${dayStr}.${moStr}`; }
                                } else { 
                                    if (isNewDay) { show = true; format = `${dayStr}.${moStr}`; }
                                }
                            } else if (timeframe === '1d') {
                                if (totalPoints > 700) { 
                                    if (isNewMonth && localMonths % 3 === 0) { show = true; format = `${moStr}.${year}`; }
                                } else if (totalPoints > 365) { 
                                    if (isNewMonth) { show = true; format = `${moStr}.${year}`; }
                                } else if (totalPoints > 90) { 
                                    if (isNewDay && localDays % 15 === 0) { show = true; format = `${dayStr}.${moStr}`; }
                                } else { 
                                    if (isNewDay && localDays % 5 === 0) { show = true; format = `${dayStr}.${moStr}`; }
                                }
                            } else {
                                const step = Math.max(1, Math.floor(totalPoints / 10));
                                if (index % step === 0) { show = true; format = `${dayStr}.${moStr}\n${hStr}:${mStr}`; }
                            }

                            return show ? format.split('\n') : null;
                        }
                    },
                    grid: { 
                        display: true,
                        drawBorder: false,
                        color: function(context) {
                            if (context.tick && context.tick.label && context.tick.label.length > 0) {
                                return 'rgba(75, 85, 99, 0.4)';
                            }
                            return 'transparent';
                        }
                    }
                },
                y: { 
                    ticks: { color: '#9ca3af', font: { size: 10 } },
                    grid: { color: '#374151' }
                }
            }
        }
    });
}

function displayResults(data) {
    document.getElementById('idleState').classList.add('hidden');
    document.getElementById('processingState').classList.add('hidden');
    document.getElementById('completedState').classList.remove('hidden');
    
    document.getElementById('startBtn').disabled = false;
    const optBtn = document.getElementById('optimizeBtn');
    if (optBtn) optBtn.disabled = false;
    
    if (data.strategy && data.strategy.includes(':')) {
        const parts = data.strategy.split(':');
        const stratName = parts[0].replace('_CROSS', '_CROSS');
        const strategySelect = document.getElementById('strategy');
        
        if (Array.from(strategySelect.options).some(opt => opt.value === stratName)) {
            strategySelect.value = stratName;
            renderStrategyParams();
            if (parts[1] && document.getElementById('param1')) document.getElementById('param1').value = parts[1];
            if (parts[2] && document.getElementById('param2')) document.getElementById('param2').value = parts[2];
            if (parts[3] && document.getElementById('param3')) document.getElementById('param3').value = parts[3];
        }
    }
    
    let tfBadge = data.timeframe ? `[${data.timeframe}] ` : '';
    document.getElementById('activeStrategyDisplay').innerText = tfBadge + formatStrategyName(data.strategy);
    
    const profitEl = document.getElementById('profitDisplay');
    profitEl.innerText = `${data.profit >= 0 ? '+' : ''}$${data.profit.toFixed(2)}`;
    profitEl.className = `font-bold text-lg ${data.profit >= 0 ? 'text-green-400' : 'text-red-400'}`;
    
    document.getElementById('tradesDisplay').innerText = data.trades || 0;
    document.getElementById('winRateDisplay').innerText = data.win_rate !== undefined ? `${data.win_rate.toFixed(1)}%` : '0.0%';
    document.getElementById('drawdownDisplay').innerText = data.drawdown !== undefined ? `-${data.drawdown.toFixed(1)}%` : '0.0%';
    
    if (data.equity && data.equity.length > 0) {
        renderChart(data.equity, data.buy_signals, data.sell_signals, data.timestamps, data.timeframe);
    }

    const askAiBtn = document.getElementById('askAiBtn');
    const aiEmptyState = document.getElementById('aiEmptyState');
    const aiResponse = document.getElementById('aiResponse');
    
    if (displayedTaskId && askAiBtn) {
        if (aiEmptyState) aiEmptyState.classList.add('hidden');
        if (aiResponse) aiResponse.classList.add('hidden');
        askAiBtn.classList.remove('hidden');
    }
}

async function askAI() {
    if (!displayedTaskId) return;

    const token = localStorage.getItem('jwt_token');
    const btn = document.getElementById('askAiBtn');
    const loader = document.getElementById('aiLoader');
    const responseBox = document.getElementById('aiResponse');

    if (btn) btn.classList.add('hidden');
    if (responseBox) responseBox.classList.add('hidden');
    if (loader) loader.classList.remove('hidden');

    try {
        const response = await fetch('/api/ai/analyze-result', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            // Передаємо контекст, чи був цей графік результатом оптимізації
            body: JSON.stringify({ task_id: displayedTaskId, is_optimized: isCurrentTaskOptimized })
        });
        
        const data = await response.json();
        
        if (!response.ok) throw new Error(data.error);
        
        if (loader) loader.classList.add('hidden');
        if (responseBox) {
            responseBox.classList.remove('hidden');
            responseBox.innerHTML = data.insight.replace(/\n/g, '<br>');
        }
        
    } catch (error) {
        if (loader) loader.classList.add('hidden');
        if (btn) btn.classList.remove('hidden');
        alert(`Помилка ШІ: ${error.message}`);
    }
}

function setupEventStream() {
    const token = localStorage.getItem('jwt_token');
    if (!token) return;
    window.eventSource = new EventSource(`/api/analysis/stream?token=${token}`);
    window.eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        if (data.task_id === currentTaskId && data.status === 'COMPLETED') {
            displayedTaskId = data.task_id; 
            displayResults(data);
            loadHistory();
        }
    };
}

async function startAnalysis() {
    const pair = document.getElementById('pair').value;
    const strategy = document.getElementById('strategy').value;
    const timeframe = document.getElementById('timeframe').value;
    
    let params = [];
    if (document.getElementById('param1')) params.push(document.getElementById('param1').value);
    if (document.getElementById('param2')) params.push(document.getElementById('param2').value);
    if (document.getElementById('param3')) params.push(document.getElementById('param3').value);
    
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const token = localStorage.getItem('jwt_token');
    
    resetDashboardState();
    // Встановлюємо флаг, що це ручний запуск (ШІ має бути критичним)
    isCurrentTaskOptimized = false;
    
    document.getElementById('idleState').classList.add('hidden');
    document.getElementById('processingState').classList.remove('hidden');
    
    document.getElementById('processingTitle').innerText = 'Обчислення в C++';
    document.getElementById('processingDesc').innerText = 'Синхронізація через SSE...';
    
    const startBtn = document.getElementById('startBtn');
    const optBtn = document.getElementById('optimizeBtn');
    if (startBtn) startBtn.disabled = true;
    if (optBtn) optBtn.disabled = true;

    try {
        const response = await fetch('/api/analysis/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ pair, timeframe, strategy, params, startDate, endDate })
        });
        
        if (response.status === 401) { logout(); return; }
        const data = await response.json();
        
        if (!response.ok) {
            if (response.status === 403) alert(`🛑 Обмеження доступу:\n\n${data.error}`);
            else throw new Error(data.error);
            resetDashboardState();
            if (startBtn) startBtn.disabled = false;
            if (optBtn) optBtn.disabled = false;
            return;
        }
        
        currentTaskId = data.task_id;
        const taskIdDisplay = document.getElementById('taskIdDisplay');
        if (taskIdDisplay) taskIdDisplay.innerText = `ID: ${currentTaskId}`;
    } catch (error) {
        alert(`Помилка: ${error.message}`);
        resetDashboardState();
        if (startBtn) startBtn.disabled = false;
        if (optBtn) optBtn.disabled = false;
    }
}

async function startOptimization() {
    const pair = document.getElementById('pair').value;
    const timeframe = document.getElementById('timeframe').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const token = localStorage.getItem('jwt_token');
    
    resetDashboardState();
    // Встановлюємо флаг, що це результат роботи ШІ-підбору (щоб Gemini його не "сварив")
    isCurrentTaskOptimized = true;
    
    document.getElementById('idleState').classList.add('hidden');
    document.getElementById('processingState').classList.remove('hidden');
    
    document.getElementById('processingTitle').innerHTML = '<span class="text-purple-400">🧬 Еволюційна Оптимізація</span>';
    document.getElementById('processingDesc').innerText = 'Перебір тисяч комбінацій параметрів...';
    
    const startBtn = document.getElementById('startBtn');
    const optBtn = document.getElementById('optimizeBtn');
    if (startBtn) startBtn.disabled = true;
    if (optBtn) optBtn.disabled = true;

    try {
        const response = await fetch('/api/analysis/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            // Передаємо ключове слово OPTIMIZE як стратегію
            body: JSON.stringify({ pair, timeframe, strategy: 'OPTIMIZE', params: [], startDate, endDate })
        });
        
        if (response.status === 401) { logout(); return; }
        const data = await response.json();
        
        if (!response.ok) {
            if (response.status === 403) alert(`🛑 Обмеження доступу:\n\n${data.error}`);
            else throw new Error(data.error);
            resetDashboardState();
            if (startBtn) startBtn.disabled = false;
            if (optBtn) optBtn.disabled = false;
            return;
        }
        
        currentTaskId = data.task_id;
        const taskIdDisplay = document.getElementById('taskIdDisplay');
        if (taskIdDisplay) taskIdDisplay.innerText = `ID: ${currentTaskId}`;
    } catch (error) {
        alert(`Помилка: ${error.message}`);
        resetDashboardState();
        if (startBtn) startBtn.disabled = false;
        if (optBtn) optBtn.disabled = false;
    }
}