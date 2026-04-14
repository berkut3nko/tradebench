document.addEventListener('DOMContentLoaded', updateUIAuthState);

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