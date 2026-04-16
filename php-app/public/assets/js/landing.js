document.addEventListener('DOMContentLoaded', () => {
    updateUIAuthState();
    checkSystemStatus();
});

function updateUIAuthState() {
    const token = localStorage.getItem('jwt_token');
    const role = localStorage.getItem('user_role');
    
    if (token) {
        const navBtn = document.getElementById('navActionBtn');
        navBtn.innerText = 'Перейти в Dashboard';
        navBtn.classList.remove('bg-white', 'text-gray-900');
        navBtn.classList.add('bg-blue-600', 'text-white', 'border', 'border-blue-500');

        document.querySelectorAll('.pricing-btn').forEach(btn => {
            btn.innerText = (role === 'pro' || role === 'admin') ? 'Подовжити підписку' : 'Оформити PRO статус';
        });
    }
}

function showToast(message) {
    const toast = document.getElementById('toast');
    document.getElementById('toastMessage').innerText = message;
    toast.classList.remove('translate-x-full', 'opacity-0');
    setTimeout(() => toast.classList.add('translate-x-full', 'opacity-0'), 5000);
}

async function handleSubscribe(months) {
    const token = localStorage.getItem('jwt_token');
    if (!token) return window.location.href = 'dashboard';

    document.querySelectorAll('.pricing-btn').forEach(b => b.innerText = 'Обробка...');

    try {
        const response = await fetch('/api/subscription/upgrade', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ months })
        });
        const data = await response.json();
        
        if (!response.ok) throw new Error(data.error || 'Помилка сервера');

        localStorage.setItem('jwt_token', data.token);
        localStorage.setItem('user_role', data.role);
        
        showToast(data.message);
        updateUIAuthState(); 
    } catch (error) {
        alert(`Помилка: ${error.message}`);
        updateUIAuthState();
    }
}

async function checkSystemStatus() {
    const badge = document.getElementById('coreStatusBadge');
    const ping = document.getElementById('coreStatusPing');
    const dot = document.getElementById('coreStatusDot');
    const text = document.getElementById('coreStatusText');

    if (!badge) return;

    try {
        const response = await fetch('/api/system/status?t=' + new Date().getTime(), {
            cache: 'no-store'
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || `HTTP error! status: ${response.status}`);
        }

        if (data.cpp_core_active === true) {
            badge.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-900/30 border border-blue-500/30 text-blue-400 text-xs font-bold uppercase mb-8 transition-colors duration-300';
            ping.className = 'animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75';
            dot.className = 'relative inline-flex rounded-full h-2 w-2 bg-blue-500';
            text.innerText = 'C++ Ядро активно';
        } else {
            // Логуємо причину (debug) у консоль браузера
            console.warn("C++ Core is inactive. Server debug reason:", data.debug);
            badge.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-900/30 border border-red-500/30 text-red-400 text-xs font-bold uppercase mb-8 transition-colors duration-300';
            ping.className = 'hidden';
            dot.className = 'relative inline-flex rounded-full h-2 w-2 bg-red-500';
            text.innerText = 'C++ Ядро недоступне';
        }
    } catch (error) {
        console.error("Помилка з'єднання при перевірці статусу API:", error);
        badge.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-900/30 border border-red-500/30 text-red-400 text-xs font-bold uppercase mb-8 transition-colors duration-300';
        ping.className = 'hidden';
        dot.className = 'relative inline-flex rounded-full h-2 w-2 bg-red-500';
        text.innerText = 'Помилка з\'єднання API';
    }
}