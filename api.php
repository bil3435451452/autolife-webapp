<?php
session_start();

// Простая аутентификация
$valid_username = 'admin';
$valid_password = 'autolife2024';

if (!isset($_SESSION['admin_logged_in'])) {
    if ($_POST['username'] ?? '' === $valid_username && $_POST['password'] ?? '' === $valid_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error = 'Неверные учетные данные';
        }
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Вход в админ-панель - АвтоЛайф</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Montserrat', sans-serif;
                    background: #121212;
                    color: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                
                .login-container {
                    background: #1e1e1e;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                    width: 100%;
                    max-width: 400px;
                }
                
                .login-container h1 {
                    text-align: center;
                    margin-bottom: 30px;
                    color: #b22222;
                    font-weight: 600;
                }
                
                .form-group {
                    margin-bottom: 20px;
                }
                
                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 500;
                }
                
                .form-group input {
                    width: 100%;
                    padding: 12px 15px;
                    border: 1px solid #333;
                    border-radius: 5px;
                    background: #121212;
                    color: #f5f5f5;
                    font-size: 1rem;
                    transition: border-color 0.3s;
                }
                
                .form-group input:focus {
                    outline: none;
                    border-color: #b22222;
                }
                
                .btn {
                    width: 100%;
                    padding: 12px;
                    background: #b22222;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.3s;
                }
                
                .btn:hover {
                    background: #d63333;
                }
                
                .error {
                    color: #ff5555;
                    text-align: center;
                    margin-bottom: 15px;
                    padding: 10px;
                    background: rgba(255, 85, 85, 0.1);
                    border-radius: 5px;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <h1>Вход в админ-панель</h1>
                <?php if (isset($error)): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <label for="username">Логин</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Войти</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - АвтоЛайф</title>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #121212;
            --secondary: #1a1a1a;
            --accent: #b22222;
            --accent-light: #d63333;
            --text: #f5f5f5;
            --text-secondary: #aaaaaa;
            --border: #333333;
            --card-bg: #1e1e1e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--primary);
            color: var(--text);
            line-height: 1.6;
        }
        
        .admin-header {
            background: var(--secondary);
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 40px;
        }
        
        .admin-nav ul {
            display: flex;
            list-style: none;
        }
        
        .admin-nav ul li {
            margin-left: 20px;
        }
        
        .admin-nav ul li a {
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .admin-nav ul li a:hover {
            color: var(--accent);
        }
        
        .logout-btn {
            background: var(--accent);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: var(--accent-light);
        }
        
        .admin-content {
            padding: 40px 0;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .stat-card .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .stat-card .stat-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 5px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .chart-container {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .chart-container h2 {
            margin-bottom: 20px;
            color: var(--accent);
            font-size: 1.3rem;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .recent-appointments {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .recent-appointments h2 {
            margin-bottom: 20px;
            color: var(--accent);
            font-size: 1.3rem;
        }
        
        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .appointments-table th,
        .appointments-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .appointments-table th {
            background: var(--secondary);
            font-weight: 600;
        }
        
        .appointments-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .status-pending {
            color: #ffa500;
        }
        
        .status-confirmed {
            color: #32cd32;
        }
        
        .status-completed {
            color: #1e90ff;
        }
        
        .status-cancelled {
            color: #ff4500;
        }
        
        .last-updated {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                padding: 15px;
            }
            
            .admin-nav ul {
                flex-direction: column;
            }
            
            .admin-nav ul li {
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="IMG/Лого.png" alt="АвтоЛайф">
                    <span style="margin-left: 15px; font-weight: 600;">Админ-панель</span>
                </div>
                <nav class="admin-nav">
                    <ul>
                        <li><a href="#overview">Обзор</a></li>
                        <li><a href="#analytics">Аналитика</a></li>
                        <li><a href="#appointments">Записи</a></li>
                        <li><a href="logout.php" class="logout-btn">Выйти</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    
    <section class="admin-content">
        <div class="container">
            <h1 style="margin-bottom: 30px; text-align: center; color: var(--accent);">Статистика сайта АвтоЛайф</h1>
            
            <!-- Общая статистика -->
            <div class="stats-overview" id="overview">
                <div class="stat-card">
                    <h3>Посещений сегодня</h3>
                    <div class="stat-value" id="visits-today">0</div>
                    <div class="stat-desc">+0% с прошлого дня</div>
                </div>
                <div class="stat-card">
                    <h3>Записей сегодня</h3>
                    <div class="stat-value" id="appointments-today">0</div>
                    <div class="stat-desc">+0% с прошлого дня</div>
                </div>
                <div class="stat-card">
                    <h3>Всего посещений</h3>
                    <div class="stat-value" id="total-visits">0</div>
                    <div class="stat-desc">За все время</div>
                </div>
                <div class="stat-card">
                    <h3>Уникальных посетителей</h3>
                    <div class="stat-value" id="unique-visitors">0</div>
                    <div class="stat-desc">За последнюю неделю</div>
                </div>
            </div>
            
            <!-- Графики -->
            <div class="charts-grid" id="analytics">
                <div class="chart-container">
                    <h2>Посещения за последние 30 дней</h2>
                    <div class="chart-wrapper">
                        <canvas id="visitsChart"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h2>Записи онлайн за последние 30 дней</h2>
                    <div class="chart-wrapper">
                        <canvas id="appointmentsChart"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h2>Популярность услуг</h2>
                    <div class="chart-wrapper">
                        <canvas id="servicesChart"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h2>Устройства посетителей</h2>
                    <div class="chart-wrapper">
                        <canvas id="devicesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Последние записи -->
            <div class="recent-appointments" id="appointments">
                <h2>Последние записи</h2>
                <div class="chart-wrapper">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>Телефон</th>
                                <th>Автомобиль</th>
                                <th>Услуга</th>
                                <th>Дата записи</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody id="recent-appointments-list">
                            <!-- Данные будут загружены через JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="last-updated">
                Последнее обновление: <span id="last-updated-time">загрузка...</span>
            </div>
        </div>
    </section>

    <script>
        // Цвета для графиков
        const chartColors = {
            primary: '#b22222',
            secondary: '#1e90ff',
            success: '#32cd32',
            warning: '#ffa500',
            danger: '#ff4500',
            light: '#aaaaaa'
        };
        
        // Загрузка данных статистики
        async function loadStats() {
            try {
                // Загрузка общей статистики
                const overviewResponse = await fetch('stats.php?action=overview');
                const overviewData = await overviewResponse.json();
                
                if (!overviewData.error) {
                    document.getElementById('visits-today').textContent = overviewData.visits_today;
                    document.getElementById('appointments-today').textContent = overviewData.appointments_today;
                    document.getElementById('total-visits').textContent = overviewData.total_visits;
                    document.getElementById('unique-visitors').textContent = overviewData.unique_visitors;
                }
                
                // Загрузка статистики посещений
                const visitsResponse = await fetch('stats.php?action=visits');
                const visitsData = await visitsResponse.json();
                
                if (!visitsData.error) {
                    renderVisitsChart(visitsData);
                }
                
                // Загрузка статистики записей
                const appointmentsResponse = await fetch('stats.php?action=appointments');
                const appointmentsData = await appointmentsResponse.json();
                
                if (!appointmentsData.error) {
                    renderAppointmentsChart(appointmentsData);
                }
                
                // Загрузка статистики услуг
                const servicesResponse = await fetch('stats.php?action=services');
                const servicesData = await servicesResponse.json();
                
                if (!servicesData.error) {
                    renderServicesChart(servicesData);
                }
                
                // Обновление времени
                document.getElementById('last-updated-time').textContent = new Date().toLocaleString('ru-RU');
                
            } catch (error) {
                console.error('Ошибка загрузки статистики:', error);
            }
        }
        
        // График посещений
        function renderVisitsChart(data) {
            const ctx = document.getElementById('visitsChart').getContext('2d');
            
            const dates = data.map(item => {
                const date = new Date(item.date);
                return `${date.getDate()}.${date.getMonth()+1}`;
            });
            
            const visits = data.map(item => item.visits);
            const uniqueVisitors = data.map(item => item.unique_visitors);
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Все посещения',
                            data: visits,
                            borderColor: chartColors.primary,
                            backgroundColor: 'rgba(178, 34, 34, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Уникальные посетители',
                            data: uniqueVisitors,
                            borderColor: chartColors.secondary,
                            backgroundColor: 'rgba(30, 144, 255, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#aaaaaa'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#aaaaaa'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#f5f5f5'
                            }
                        }
                    }
                }
            });
        }
        
        // График записей
        function renderAppointmentsChart(data) {
            const ctx = document.getElementById('appointmentsChart').getContext('2d');
            
            const dates = data.map(item => {
                const date = new Date(item.date);
                return `${date.getDate()}.${date.getMonth()+1}`;
            });
            
            const appointments = data.map(item => item.appointments);
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Записи онлайн',
                        data: appointments,
                        backgroundColor: 'rgba(178, 34, 34, 0.7)',
                        borderColor: chartColors.primary,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#aaaaaa'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#aaaaaa'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#f5f5f5'
                            }
                        }
                    }
                }
            });
        }
        
        // График услуг
        function renderServicesChart(data) {
            const ctx = document.getElementById('servicesChart').getContext('2d');
            
            const services = data.map(item => item.service);
            const counts = data.map(item => item.count);
            
            // Цвета для диаграммы
            const backgroundColors = [
                '#b22222', '#1e90ff', '#32cd32', '#ffa500', 
                '#9370db', '#ff69b4', '#20b2aa', '#ff4500'
            ];
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: services,
                    datasets: [{
                        data: counts,
                        backgroundColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#f5f5f5',
                                boxWidth: 15,
                                padding: 15
                            }
                        }
                    }
                }
            });
        }
        
        // График устройств (заглушка)
        function renderDevicesChart() {
            const ctx = document.getElementById('devicesChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Десктоп', 'Мобильные', 'Планшеты'],
                    datasets: [{
                        data: [65, 30, 5],
                        backgroundColor: [
                            chartColors.primary,
                            chartColors.secondary,
                            chartColors.warning
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#f5f5f5',
                                boxWidth: 15,
                                padding: 15
                            }
                        }
                    }
                }
            });
        }
        
        // Загрузка последних записей
        async function loadRecentAppointments() {
            try {
                const response = await fetch('get_appointments.php?limit=10');
                const data = await response.json();
                
                if (!data.error) {
                    const tableBody = document.getElementById('recent-appointments-list');
                    tableBody.innerHTML = '';
                    
                    data.forEach(appointment => {
                        const row = document.createElement('tr');
                        
                        // Форматирование даты
                        const appointmentDate = new Date(appointment.appointment_date);
                        const formattedDate = `${appointmentDate.getDate().toString().padStart(2, '0')}.${(appointmentDate.getMonth()+1).toString().padStart(2, '0')}.${appointmentDate.getFullYear()} ${appointmentDate.getHours().toString().padStart(2, '0')}:${appointmentDate.getMinutes().toString().padStart(2, '0')}`;
                        
                        row.innerHTML = `
                            <td>${appointment.id}</td>
                            <td>${appointment.client_name}</td>
                            <td>${appointment.client_phone}</td>
                            <td>${appointment.car_brand} ${appointment.car_model}</td>
                            <td>${appointment.service}</td>
                            <td>${formattedDate}</td>
                            <td class="status-${appointment.status}">${getStatusText(appointment.status)}</td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                }
            } catch (error) {
                console.error('Ошибка загрузки записей:', error);
            }
        }
        
        function getStatusText(status) {
            const statusMap = {
                'pending': 'Ожидание',
                'confirmed': 'Подтверждена',
                'completed': 'Выполнена',
                'cancelled': 'Отменена'
            };
            
            return statusMap[status] || status;
        }
        
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadRecentAppointments();
            renderDevicesChart();
            
            // Обновление каждые 5 минут
            setInterval(loadStats, 300000);
        });
    </script>
</body>
</html>