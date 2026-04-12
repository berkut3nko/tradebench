document.addEventListener('DOMContentLoaded', () => {
    updateUIAuthState();
});

// Функція для візуального оновлення кнопок залежно від авторизації
function updateUIAuthState() {
    const token = localStorage.getItem('jwt_token');
    const role = localStorage.getItem('user_role');
    const navBtn = document.getElementById('navActionBtn');
    const pricingBtns = document.querySelectorAll('.pricing-btn');

    if (token) {
        // Користувач увійшов
        navBtn.innerText = 'Перейти в Dashboard';
        navBtn.classList.remove('bg-white', 'text-gray-900');
        navBtn.classList.add('bg-blue-600', 'text-white', 'border', 'border-blue-500');

        pricingBtns.forEach(btn => {
            if (role === 'pro' || role === 'admin') {
                btn.innerText = 'Подовжити підписку';
            } else {
                btn.innerText = 'Оформити PRO статус';
            }
        });
    }
}

// Функція показу красивого спливаючого повідомлення
function showToast(message) {
    const toast = document.getElementById('toast');
    const msgEl = document.getElementById('toastMessage');
    msgEl.innerText = message;
    
    toast.classList.remove('translate-x-full', 'opacity-0');
    
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
    }, 5000);
}

// Головна функція оформлення/подовження підписки
async function handleSubscribe(months) {
    const token = localStorage.getItem('jwt_token');
    
    // Якщо не авторизований - кидаємо на вхід
    if (!token) {
        window.location.href = 'dashboard';
        return;
    }

    // Якщо авторизований - робимо запит до API
    try {
        // Змінюємо текст кнопок на завантаження
        const btns = document.querySelectorAll('.pricing-btn');
        btns.forEach(b => b.innerText = 'Обробка...');

        const response = await fetch('/api/subscription/upgrade', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}` 
            },
            body: JSON.stringify({ months: months })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Помилка сервера');
        }

        // ОНОВЛЮЄМО ТОКЕН ТА РОЛЬ ЛОКАЛЬНО БЕЗ ПЕРЕЗАВАНТАЖЕННЯ!
        localStorage.setItem('jwt_token', data.token);
        localStorage.setItem('user_role', data.role);
        
        showToast(data.message);
        updateUIAuthState(); // Оновлюємо текст кнопок ("Подовжити")

    } catch (error) {
        alert(`Помилка: ${error.message}`);
        updateUIAuthState(); // Повертаємо текст кнопок назад
    }
}