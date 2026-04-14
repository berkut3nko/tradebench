# TradeBench: Algorithmic Trading & Backtesting Platform

![Architecture](https://img.shields.io/badge/Architecture-Hybrid_Microservices-purple) ![Backend](https://img.shields.io/badge/Backend-PHP_8.2-777BB4?logo=php&logoColor=white) ![Core](https://img.shields.io/badge/Core-C%2B%2B_17-00599C?logo=c%2B%2B&logoColor=white) ![DB](https://img.shields.io/badge/Database-PostgreSQL-4169E1?logo=postgresql&logoColor=white) ![RPC](https://img.shields.io/badge/Communication-gRPC-244c5a?logo=grpc&logoColor=white) ![Build](https://img.shields.io/badge/Build-CMake-008FBA?logo=cmake&logoColor=white) ![License](https://img.shields.io/badge/License-MIT-yellow.svg)

**TradeBench** — це розподілена інформаційна система для високошвидкісного бектестингу торгових алгоритмів. Проєкт поєднує гнучкість веб-інтерфейсу на **PHP** та безкомпромісну швидкість паралельних обчислень на **C++**. 

Система використовує **gRPC** для блискавичної комунікації між мікросервісами, **PostgreSQL** для зберігання даних, **Redis** для потокових подій (SSE) та інтеграцію зі штучним інтелектом (Google Gemini) для оцінки стратегій.

---

## 📋 Вимоги до системи (Prerequisites)

Для успішного запуску проєкту на вашій машині повинні бути встановлені:
* [Docker](https://www.docker.com/) та Docker Compose
* Git
* *Усі інші залежності (C++ компілятори, CMake, PHP, Protoc) автоматично встановлюються всередині Docker-контейнерів.*

---

## 🚀 Швидкий старт (Quick Setup)

### 1. Клонування репозиторію
```bash
git clone [https://github.com/berkut3nko/tradebench.git](https://github.com/berkut3nko/tradebench.git)
cd tradebench
```

### 2. Налаштування середовища (.env)
У папці `php-app` створіть файл `.env` (або скопіюйте з прикладу, якщо він є) та заповніть його:
```bash
touch php-app/.env
```
**Вміст `php-app/.env`:**
```env
# Database Configuration
DB_HOST=db
DB_PORT=5432
DB_DATABASE=analyzer_db
DB_USERNAME=user
DB_PASSWORD=pass

# Security & Auth
JWT_SECRET=your_super_secret_jwt_key_2026

# External APIs
GEMINI_API_KEY=your_google_gemini_api_key_here

# Messaging
REDIS_HOST=redis
```

### 3. Запуск Docker-контейнерів
```bash
docker compose build
docker compose up -d
```

### 4. Встановлення залежностей PHP (Composer)
```bash
docker compose exec php-app composer install --no-progress
```

### 5. Збірка та запуск C++ Ядра (Computational Core)
Зайдіть у контейнер C++, зберіть проєкт через CMake та запустіть gRPC сервер:
```bash
docker compose exec cpp-engine bash
mkdir -p build && cd build
cmake ..
make -j 4
./tradebench_core
```
*(Щоб вийти з контейнера, натисніть `Ctrl+D` або введіть `exit`)*

---

## 🧪 Запуск тестів (Testing)

Проєкт має високий рівень покриття Unit-тестами. 

**Тестування PHP-бекенду (PHPUnit):**
```bash
docker compose exec php-app vendor/bin/phpunit tests --testdox
```

**Тестування C++ ядра (GoogleTest):**
```bash
docker compose exec cpp-engine bash -c "cd build && ./run_tests"
```

---

## 🛠 Корисні команди адміністратора

Запуск терміналу бази даних PostgreSQL:
```bash
docker compose exec db psql -U user -d analyzer_db
```

**Швидкі SQL-команди:**
* Перевірити наявні результати аналізу:
  ```bash
  docker compose exec db psql -U user -d analyzer_db -c "SELECT id FROM analysis_results;"
  ```
* Перевірити строки закінчення підписки у користувачів:
  ```bash
  docker compose exec db psql -U user -d analyzer_db -c "SELECT id, email, pro_expires_at FROM users;"
  ```
* **Видати права Адміністратора** (наприклад, для користувача з `id = 1`):
  ```bash
  docker compose exec db psql -U user -d analyzer_db -c "UPDATE users SET role = 'admin' WHERE id = 1;"
  ```

---

## 📁 Структура проєкту

```text
tradebench/
├── bruno/                  # Колекція API запитів для тестування (Bruno)
├── cpp-core/               # C++ gRPC Сервер (Обчислювальне ядро)
│   ├── include/            # Заголовні файли (.h)
│   ├── src/                # Логіка (engine, database, grpc_service)
│   ├── tests/              # GoogleTest файли
│   └── CMakeLists.txt      # Конфігурація збірки
├── database/
│   └── init.sql            # Міграції та схема БД PostgreSQL
├── nginx/
│   └── conf.d/             # Налаштування веб-сервера та проксі
├── php-app/                # PHP gRPC Клієнт (Веб-бекенд)
│   ├── src/                # MVC Архітектура (Controllers, Models, Services)
│   ├── public/             # Точка входу (index.php) та HTML/CSS/JS фронтенд
│   ├── tests/Unit/         # PHPUnit тести
│   └── composer.json       # Залежності PHP
├── proto/                  # Спільні контракти
│   └── analysis.proto      # Опис gRPC методів
└── docker-compose.yml      # Оркестратор інфраструктури
```

---
*Розроблено в рамках курсового проєкту з архітектури розподілених систем.*