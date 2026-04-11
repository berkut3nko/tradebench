let equityChartInstance = null;
let currentTaskId = null;

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('jwt_token')) showDashboard();
    
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    document.getElementById('startDate').value = thirtyDaysAgo;
    document.getElementById('endDate').value = today;

    document.getElementById('startBtn').addEventListener('click', startAnalysis);
    document.getElementById('strategy').addEventListener('change', renderStrategyParams);
    
    // Відображаємо параметри стратегії при першому завантаженні
    renderStrategyParams();
});

// Ця функція вставляє поля з правильними стилями Tailwind
function renderStrategyParams() {
    const strategy = document.getElementById('strategy').value;
    const box = document.getElementById('paramsBox');
    
    if (strategy === 'SMA_CROSS' || strategy === 'EMA_CROSS') {
        box.className = 'grid grid-cols-2 gap-4 bg-gray-900/50 p-3 rounded border border-gray-700';
        box.innerHTML = `
            <div>
                <label class="block text-xs text-gray-400 mb-1">Fast Period</label>
                <input type="number" id="param1" value="9" min="2" max="50" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Slow Period</label>
                <input type="number" id="param2" value="21" min="10" max="200" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500">
            </div>
        `;
    } else if (strategy === 'RSI') {
        box.className = 'grid grid-cols-3 gap-4 bg-gray-900/50 p-3 rounded border border-gray-700';
        box.innerHTML = `
            <div>
                <label class="block text-xs text-gray-400 mb-1">Period</label>
                <input type="number" id="param1" value="14" min="2" max="50" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Overbought</label>
                <input type="number" id="param2" value="70" min="50" max="99" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Oversold</label>
                <input type="number" id="param3" value="30" min="1" max="50" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm outline-none focus:border-blue-500">
            </div>
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
    
    badge.classList.remove('hidden', 'bg-yellow-500', 'bg-gray-600', 'bg-red-500', 'text-yellow-900', 'text-gray-200', 'text-red-100');
    if (adminBtn) adminBtn.classList.add('hidden');
    
    if (role === 'admin') {
        badge.innerText = 'Admin';
        badge.classList.add('bg-red-500', 'text-red-100');
        if (adminBtn) adminBtn.classList.remove('hidden');
    } else if (role === 'pro') {
        badge.innerText = 'PRO Account';
        badge.classList.add('bg-yellow-500', 'text-yellow-900');
    } else {
        badge.innerText = 'Standard';
        badge.classList.add('bg-gray-600', 'text-gray-200');
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
    if (equityChartInstance) equityChartInstance.destroy();
    document.getElementById('historyTableBody').innerHTML = '';
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
            let strategyName = formatStrategyName(resultData ? resultData.strategy : null);

            let date = new Date(task.created_at).toLocaleString('uk-UA');
            let safeJson = resultData ? task.result_data.replace(/'/g, "&#39;") : '';
            
            // ВАЖЛИВО: Стилізована кнопка "Графік"
            let actionBtn = resultData ? `<button onclick='viewHistoricalChart(this)' data-result='${safeJson}' class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded text-xs font-bold transition shadow">Графік</button>` : '-';

            tbody.innerHTML += `
                <tr class="hover:bg-gray-750 transition-colors">
                    <td class="px-4 py-3 text-gray-300">${date}</td>
                    <td class="px-4 py-3 font-bold text-white">${task.pair}</td>
                    <td class="px-4 py-3 font-mono text-xs text-purple-400">${strategyName}</td>
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

window.viewHistoricalChart = function(btn) {
    const resultData = JSON.parse(btn.getAttribute('data-result'));
    displayResults(resultData);
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

function renderChart(equityData) {
    const ctx = document.getElementById('equityChart').getContext('2d');
    if (equityChartInstance) equityChartInstance.destroy();

    const labels = equityData.map((_, i) => i);
    const isProfit = equityData[equityData.length - 1] >= 1000;
    const color = isProfit ? '#22c55e' : '#ef4444'; 
    const bgColor = isProfit ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)';

    equityChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Капітал ($)',
                data: equityData,
                borderColor: color,
                backgroundColor: bgColor,
                borderWidth: 2,
                pointRadius: 0,
                fill: true,
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { display: false },
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
    
    document.getElementById('activeStrategyDisplay').innerText = formatStrategyName(data.strategy);
    
    const profitEl = document.getElementById('profitDisplay');
    profitEl.innerText = `${data.profit >= 0 ? '+' : ''}$${data.profit.toFixed(2)}`;
    profitEl.className = `font-bold text-lg ${data.profit >= 0 ? 'text-green-400' : 'text-red-400'}`;
    
    document.getElementById('tradesDisplay').innerText = data.trades || 0;
    document.getElementById('winRateDisplay').innerText = data.win_rate !== undefined ? `${data.win_rate.toFixed(1)}%` : '0.0%';
    document.getElementById('drawdownDisplay').innerText = data.drawdown !== undefined ? `-${data.drawdown.toFixed(1)}%` : '0.0%';
    
    if (data.equity && data.equity.length > 0) {
        renderChart(data.equity);
    }
}

function setupEventStream() {
    const token = localStorage.getItem('jwt_token');
    if (!token) return;
    window.eventSource = new EventSource(`/api/analysis/stream?token=${token}`);
    window.eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        if (data.task_id === currentTaskId && data.status === 'COMPLETED') {
            displayResults(data);
            loadHistory();
        }
    };
}

async function startAnalysis() {
    const pair = document.getElementById('pair').value;
    const strategy = document.getElementById('strategy').value;
    const timeframe = document.getElementById('timeframe').value;
    
    // ВАЖЛИВО: Динамічний збір масиву параметрів
    let params = [];
    if (document.getElementById('param1')) params.push(document.getElementById('param1').value);
    if (document.getElementById('param2')) params.push(document.getElementById('param2').value);
    if (document.getElementById('param3')) params.push(document.getElementById('param3').value);
    
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const token = localStorage.getItem('jwt_token');
    
    document.getElementById('idleState').classList.add('hidden');
    document.getElementById('completedState').classList.add('hidden');
    document.getElementById('processingState').classList.remove('hidden');
    document.getElementById('startBtn').disabled = true;

    try {
        const response = await fetch('/api/analysis/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ pair, timeframe, strategy, params, startDate, endDate })
        });
        
        if (response.status === 401) { logout(); return; }
        const data = await response.json();
        
        if (!response.ok) {
            if (response.status === 403) {
                alert(`🛑 Обмеження доступу:\n\n${data.error}`);
            } else {
                throw new Error(data.error);
            }
            
            document.getElementById('startBtn').disabled = false;
            document.getElementById('idleState').classList.remove('hidden');
            document.getElementById('processingState').classList.add('hidden');
            return;
        }
        
        currentTaskId = data.task_id;
        document.getElementById('taskIdDisplay').innerText = `ID: ${currentTaskId}`;
    } catch (error) {
        alert(`Помилка: ${error.message}`);
        document.getElementById('startBtn').disabled = false;
        document.getElementById('idleState').classList.remove('hidden');
        document.getElementById('processingState').classList.add('hidden');
    }
}