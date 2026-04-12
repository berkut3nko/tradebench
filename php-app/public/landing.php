<?php
// MVC View: User Landing Page
?>
<!DOCTYPE html>
<html lang="uk" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TradeBench | Алгоритмічний трейдинг</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .glow-text { text-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
        .bg-grid {
            background-size: 40px 40px;
            background-image: linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        }
        .pricing-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body class="bg-gray-950 text-white font-sans antialiased min-h-screen flex flex-col">

    <!-- Тост-сповіщення (спливаюче вікно) -->
    <div id="toast" class="fixed top-20 right-6 z-50 transform transition-all duration-300 translate-x-full opacity-0">
        <div class="bg-gray-800 border border-green-500 rounded-lg shadow-2xl p-4 flex items-center gap-3">
            <div class="bg-green-500/20 text-green-400 p-2 rounded-full">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div>
                <p class="font-bold text-white">Успішно!</p>
                <p id="toastMessage" class="text-sm text-gray-300">Підписку оформлено.</p>
            </div>
        </div>
    </div>

    <!-- Навігація -->
    <nav class="border-b border-gray-800 bg-gray-900/80 backdrop-blur-md fixed w-full z-40 top-0">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-2 cursor-pointer" onclick="window.scrollTo(0,0)">
                <div class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center font-bold text-lg">T</div>
                <span class="text-2xl font-bold text-white tracking-tight">Trade<span class="text-blue-500">Bench</span></span>
            </div>
            <div class="flex gap-4 items-center">
                <a href="#pricing" class="text-gray-300 hover:text-white text-sm font-medium transition hidden sm:block">Тарифи</a>
                <a id="navActionBtn" href="dashboard" class="bg-white text-gray-900 hover:bg-gray-200 font-bold py-2 px-6 rounded-full transition shadow-lg shadow-white/10">
                    Увійти в платформу
                </a>
            </div>
        </div>
    </nav>

    <!-- Головний Hero-блок -->
    <main class="flex-grow pt-32 pb-20 px-6 bg-grid relative overflow-hidden">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-blue-600/20 blur-[120px] rounded-full pointer-events-none"></div>
        
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-900/30 border border-blue-500/30 text-blue-400 text-xs font-bold uppercase tracking-wider mb-8">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                </span>
                C++ Ядро активно
            </div>
            
            <h1 class="text-5xl md:text-7xl font-extrabold mb-6 tracking-tight leading-tight">
                Бектестинг стратегій зі швидкістю <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500 glow-text">C++</span>
            </h1>
            
            <p class="text-xl text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed">
                Гібридна розподілена архітектура. Аналізуйте ринки, використовуйте еволюційні алгоритми для пошуку параметрів та отримуйте експертні висновки від штучного інтелекту.
            </p>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="dashboard" class="bg-blue-600 hover:bg-blue-500 text-white font-bold text-lg py-4 px-8 rounded-lg transition shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                    Розпочати аналіз
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </a>
            </div>
        </div>
    </main>

    <!-- Особливості архітектури -->
    <section class="py-20 bg-gray-900 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Технологічна перевага</h2>
                <p class="text-gray-400 max-w-2xl mx-auto">Платформа TradeBench побудована на базі мікросервісної архітектури, що гарантує високу точність та швидкість обчислень.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gray-800 p-8 rounded-2xl border border-gray-700 hover:border-blue-500/50 transition duration-300">
                    <div class="w-12 h-12 bg-blue-900/50 text-blue-400 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Високопродуктивне C++ Ядро</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Весь математичний аналіз винесено у незалежний C++ мікросервіс. Взаємодія з PHP відбувається через блискавичний протокол gRPC.
                    </p>
                </div>
                
                <div class="bg-gray-800 p-8 rounded-2xl border border-gray-700 hover:border-purple-500/50 transition duration-300">
                    <div class="w-12 h-12 bg-purple-900/50 text-purple-400 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Генетична Оптимізація</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Еволюційні алгоритми переберуть тисячі комбінацій у фоновому режимі та знайдуть глобальний оптимум для ринку (PRO).
                    </p>
                </div>
                
                <div class="bg-gray-800 p-8 rounded-2xl border border-gray-700 hover:border-green-500/50 transition duration-300">
                    <div class="w-12 h-12 bg-green-900/50 text-green-400 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">AI Асистент</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Інтеграція з Google Gemini. Штучний інтелект оцінює ризик-менеджмент та надає професійні висновки щодо надійності (PRO).
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Секція Тарифів (Pricing) -->
    <section id="pricing" class="py-20 bg-gray-950">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Розблокуйте всю потужність</h2>
                <p class="text-gray-400 max-w-xl mx-auto">Переходьте на PRO-акаунт, щоб отримати доступ до Генетичного автопідбору, ШІ-аналітики та мілких таймфреймів (15m, 4h).</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                
                <!-- Тариф 1 Місяць -->
                <div class="pricing-card bg-gray-900 rounded-2xl border border-gray-800 p-8 flex flex-col transition duration-300 relative">
                    <h3 class="text-xl font-bold text-gray-300 mb-2">Старт</h3>
                    <div class="flex items-baseline gap-1 mb-6">
                        <span class="text-4xl font-extrabold">$15</span><span class="text-gray-500">/ міс</span>
                    </div>
                    <ul class="space-y-4 mb-8 flex-grow text-gray-400 text-sm">
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 1 місяць PRO доступу</li>
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Генетична оптимізація</li>
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> ШІ-Асистент Gemini</li>
                    </ul>
                    <button onclick="handleSubscribe(1)" class="pricing-btn w-full py-3 px-4 bg-gray-800 hover:bg-gray-700 border border-gray-600 rounded-lg font-bold transition">Отримати PRO</button>
                </div>

                <!-- Тариф 6 Місяців -->
                <div class="pricing-card bg-gray-800 rounded-2xl border border-purple-500 shadow-[0_0_30px_rgba(168,85,247,0.15)] p-8 flex flex-col transition duration-300 relative transform md:-translate-y-4 z-10">
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-gradient-to-r from-purple-600 to-blue-600 text-white px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide">Найпопулярніший</div>
                    <h3 class="text-xl font-bold text-purple-400 mb-2">Оптимум</h3>
                    <div class="flex items-baseline gap-1 mb-6">
                        <span class="text-4xl font-extrabold">$75</span><span class="text-gray-500">/ 6 міс</span>
                    </div>
                    <ul class="space-y-4 mb-8 flex-grow text-gray-300 text-sm">
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 6 місяців PRO доступу</li>
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Економія $15</li>
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Генетична оптимізація</li>
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Необмежений бектестинг</li>
                    </ul>
                    <button onclick="handleSubscribe(6)" class="pricing-btn w-full py-3 px-4 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 rounded-lg font-bold shadow-lg transition">Отримати PRO</button>
                </div>

                <!-- Тариф 12 Місяців -->
                <div class="pricing-card bg-gray-900 rounded-2xl border border-gray-800 p-8 flex flex-col transition duration-300 relative">
                    <h3 class="text-xl font-bold text-gray-300 mb-2">Професіонал</h3>
                    <div class="flex items-baseline gap-1 mb-6">
                        <span class="text-4xl font-extrabold">$120</span><span class="text-gray-500">/ рік</span>
                    </div>
                    <ul class="space-y-4 mb-8 flex-grow text-gray-400 text-sm">
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 1 рік PRO доступу</li>
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Економія $60 (Два місяці безкоштовно)</li>
                        <li class="flex items-center gap-3"><svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Усі PRO функції</li>
                    </ul>
                    <button onclick="handleSubscribe(12)" class="pricing-btn w-full py-3 px-4 bg-gray-800 hover:bg-gray-700 border border-gray-600 rounded-lg font-bold transition">Отримати PRO</button>
                </div>

            </div>
        </div>
    </section>

    <footer class="bg-gray-950 py-8 border-t border-gray-900 text-center text-gray-600 text-sm">
        <p>© 2026 TradeBench. Курсовий проєкт з розробки архітектури розподілених систем.</p>
    </footer>

    <!-- Скрипт керування станом Landing Page -->
    <script>
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
    </script>
</body>
</html>