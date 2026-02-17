<?php
session_start();
ob_start();

// Подключение к базе данных
$host = 'localhost';
$dbname = 'autolife';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Создание необходимых таблиц для продвижения
    initPromotionTables($pdo);
    
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Функция инициализации таблиц для продвижения
function initPromotionTables($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS promotion_campaigns (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            service_type VARCHAR(50) NOT NULL,
            discount_type ENUM('percent', 'fixed', 'package') DEFAULT 'percent',
            discount_value DECIMAL(10,2) DEFAULT 0,
            target_vehicle_brands JSON,
            target_client_segment ENUM('all', 'new', 'returning', 'inactive') DEFAULT 'all',
            start_date DATETIME,
            end_date DATETIME,
            status ENUM('draft', 'active', 'paused', 'completed') DEFAULT 'draft',
            auto_renew BOOLEAN DEFAULT FALSE,
            budget DECIMAL(10,2) DEFAULT 0,
            spent DECIMAL(10,2) DEFAULT 0,
            conversions INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS client_profiles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            phone VARCHAR(20) UNIQUE,
            email VARCHAR(100),
            vehicle_brand VARCHAR(50),
            vehicle_model VARCHAR(50),
            last_service_date DATE,
            total_spent DECIMAL(10,2) DEFAULT 0,
            service_history JSON,
            loyalty_points INT DEFAULT 0,
            marketing_consent BOOLEAN DEFAULT TRUE,
            preferred_services JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS promotion_results (
            id INT PRIMARY KEY AUTO_INCREMENT,
            campaign_id INT,
            appointment_id INT,
            client_id INT,
            conversion_value DECIMAL(10,2) DEFAULT 0,
            channel ENUM('sms', 'email', 'push', 'site_banner', 'social', 'telegram') DEFAULT 'site_banner',
            converted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES promotion_campaigns(id) ON DELETE SET NULL,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS service_promotions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            service_name VARCHAR(100) NOT NULL,
            base_price DECIMAL(10,2) NOT NULL,
            promo_price DECIMAL(10,2),
            promo_active BOOLEAN DEFAULT FALSE,
            promo_start DATE,
            promo_end DATE,
            demand_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
            seasonal_multiplier DECIMAL(3,2) DEFAULT 1.0,
            last_promo_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE IF NOT EXISTS marketing_channels (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            type ENUM('telegram', 'email', 'sms', 'push', 'social') NOT NULL,
            api_key TEXT,
            api_secret TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            settings JSON,
            last_used TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Таблица уже существует или ошибка создания
        }
    }
    
    // Заполняем таблицу услуг начальными данными
    $services = [
        ['Пленки', 15000, null, false, 'medium'],
        ['Химчистка', 8000, null, false, 'medium'],
        ['Тонировка', 12000, null, false, 'medium'],
        ['Детейлинг', 25000, null, false, 'medium'],
        ['Полировка', 10000, null, false, 'medium'],
        ['Антидождь', 5000, null, false, 'medium']
    ];
    
    foreach ($services as $service) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_promotions (service_name, base_price, demand_level) VALUES (?, ?, ?)");
        $stmt->execute([$service[0], $service[1], $service[4]]);
    }
}

// Класс для управления продвижением
class PromotionManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Быстрый запуск промо-акции
    public function launchQuickPromotion($service, $discount = 20, $duration = 7) {
        $serviceInfo = $this->getServiceInfo($service);
        if (!$serviceInfo) return false;
        
        $title = "Акция: {$service} -{$discount}%";
        $endDate = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO promotion_campaigns 
            (title, service_type, discount_type, discount_value, start_date, end_date, status, auto_renew)
            VALUES (?, ?, 'percent', ?, NOW(), ?, 'active', FALSE)
        ");
        
        $stmt->execute([$title, $service, $discount, $endDate]);
        $campaignId = $this->pdo->lastInsertId();
        
        // Обновляем цену в услугах
        $promoPrice = $serviceInfo['base_price'] * (1 - $discount/100);
        $this->updateServicePromo($service, $promoPrice, $endDate);
        
        // Отправляем уведомления
        $this->notifyChannels($campaignId, $service, $discount);
        
        return $campaignId;
    }
    
    // AI анализ и рекомендации
    public function getAIRecommendations() {
        $recommendations = [];
        
        // Анализ спроса услуг
        $stmt = $this->pdo->query("
            SELECT s.service_name, s.demand_level, s.last_promo_date,
                   COUNT(a.id) as recent_appointments
            FROM service_promotions s
            LEFT JOIN appointments a ON a.service = s.service_name 
                AND a.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY s.id
            ORDER BY s.demand_level, recent_appointments
        ");
        
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($services as $service) {
            if ($service['demand_level'] == 'low' || 
                (strtotime($service['last_promo_date']) < strtotime('-30 days') || !$service['last_promo_date'])) {
                
                $recommendations[] = [
                    'service' => $service['service_name'],
                    'type' => 'demand_boost',
                    'message' => "Низкий спрос на услугу '{$service['service_name']}'. Рекомендуется запустить промо-акцию.",
                    'suggested_discount' => $this->calculateOptimalDiscount($service),
                    'priority' => 'high'
                ];
            }
        }
        
        // Сезонные рекомендации
        $month = date('n');
        $seasonalServices = $this->getSeasonalServices($month);
        foreach ($seasonalServices as $service => $info) {
            $recommendations[] = [
                'service' => $service,
                'type' => 'seasonal',
                'message' => $info['message'],
                'suggested_discount' => $info['discount'],
                'priority' => 'medium'
            ];
        }
        
        return $recommendations;
    }
    
    private function calculateOptimalDiscount($service) {
        // Простая логика расчета скидки
        $daysSincePromo = $service['last_promo_date'] ? 
            (time() - strtotime($service['last_promo_date'])) / 86400 : 90;
        
        if ($daysSincePromo > 60) return 25;
        if ($daysSincePromo > 30) return 20;
        return 15;
    }
    
    private function getSeasonalServices($month) {
        $services = [];
        
        // Весна (Март-Май)
        if ($month >= 3 && $month <= 5) {
            $services['Химчистка'] = [
                'message' => 'Весенний сезон - самое время для химчистки салона!',
                'discount' => 20
            ];
            $services['Полировка'] = [
                'message' => 'Весной автомобилю нужна полировка после зимы',
                'discount' => 15
            ];
        }
        
        // Лето (Июнь-Август)
        if ($month >= 6 && $month <= 8) {
            $services['Тонировка'] = [
                'message' => 'Летом тонировка особенно актуальна',
                'discount' => 25
            ];
            $services['Антидождь'] = [
                'message' => 'Летние дожди - обработайте стекла',
                'discount' => 20
            ];
        }
        
        // Осень (Сентябрь-Ноябрь)
        if ($month >= 9 && $month <= 11) {
            $services['Пленки'] = [
                'message' => 'Подготовьте авто к зиме с защитными пленками',
                'discount' => 20
            ];
        }
        
        // Зима (Декабрь-Февраль)
        if ($month == 12 || $month <= 2) {
            $services['Детейлинг'] = [
                'message' => 'Зимний детейлинг для защиты от реагентов',
                'discount' => 25
            ];
        }
        
        return $services;
    }
    
    private function getServiceInfo($serviceName) {
        $stmt = $this->pdo->prepare("SELECT * FROM service_promotions WHERE service_name = ?");
        $stmt->execute([$serviceName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateServicePromo($service, $promoPrice, $endDate) {
        $stmt = $this->pdo->prepare("
            UPDATE service_promotions 
            SET promo_price = ?, promo_active = TRUE, promo_end = ?, last_promo_date = NOW()
            WHERE service_name = ?
        ");
        $stmt->execute([$promoPrice, $endDate, $service]);
    }
    
    private function notifyChannels($campaignId, $service, $discount) {
        // В реальном приложении здесь будет код отправки через API
        // Telegram, Email, SMS и т.д.
        error_log("Campaign {$campaignId}: {$service} -{$discount}% launched");
    }
}

// Создаем менеджер продвижения
$promoManager = new PromotionManager($pdo);

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input_username = $_POST['username'] ?? '';
        $input_password = $_POST['password'] ?? '';
        
        // Ищем пользователя в базе
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$input_username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($input_password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_id'] = $admin['id'];
            
            // Устанавливаем время последнего просмотра
            $stmt = $pdo->prepare("UPDATE admins SET last_notification_check = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);
        } else {
            $error = 'Неверные учетные данные';
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Вход в админ-панель - АвтоЛайф</title>
            <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Montserrat', sans-serif;
                    background: linear-gradient(135deg, #000000 0%, #0a0a0a 100%);
                    color: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                .login-container {
                    background: rgba(20, 20, 20, 0.95);
                    padding: 50px 40px;
                    border-radius: 15px;
                    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7);
                    width: 100%;
                    max-width: 450px;
                    border: 1px solid rgba(178, 34, 34, 0.3);
                }
                .form-group { margin-bottom: 25px; }
                .form-group input {
                    width: 100%;
                    padding: 15px 20px;
                    border: 2px solid rgba(255, 255, 255, 0.1);
                    border-radius: 10px;
                    background: rgba(15, 15, 15, 0.9);
                    color: #f5f5f5;
                    font-size: 1rem;
                }
                .btn {
                    width: 100%;
                    padding: 15px;
                    background: linear-gradient(45deg, #b22222, #d63333);
                    color: white;
                    border: none;
                    border-radius: 10px;
                    font-size: 1.1rem;
                    font-weight: 600;
                    cursor: pointer;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-header" style="text-align: center; margin-bottom: 40px;">
                    <img src="IMG/Лого.png" alt="АвтоЛайф" style="max-width: 200px;">
                    <p>Панель администратора</p>
                </div>
                <?php if (isset($error)): ?>
                    <div style="color: #ff5555; text-align: center; margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
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
                    <button type="submit" class="btn">Войти в систему</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Обработка действий продвижения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Быстрый запуск промо
    if (isset($_POST['quick_promo'])) {
        $service = $_POST['service'] ?? '';
        $discount = $_POST['discount'] ?? 20;
        $duration = $_POST['duration'] ?? 7;
        
        $campaignId = $promoManager->launchQuickPromotion($service, $discount, $duration);
        
        if ($campaignId) {
            $_SESSION['promo_success'] = "Промо-акция для '{$service}' успешно запущена!";
        } else {
            $_SESSION['promo_error'] = "Ошибка при запуске промо-акции";
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Применить рекомендацию AI
    if (isset($_POST['apply_ai_recommendation'])) {
        $service = $_POST['service'] ?? '';
        $discount = $_POST['suggested_discount'] ?? 20;
        
        $campaignId = $promoManager->launchQuickPromotion($service, $discount);
        
        if ($campaignId) {
            $_SESSION['promo_success'] = "Рекомендация AI применена: '{$service}' -{$discount}%";
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Остальные обработчики (статусы, удаление и т.д.) остаются
    if (isset($_POST['update_status'])) {
        $appointment_id = $_POST['appointment_id'];
        $new_status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $appointment_id]);
        $_SESSION['status_updated'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Получение данных для дашборда
function getDashboardStats($pdo) {
    $stats = [];
    
    // Статистика по услугам
    $stmt = $pdo->query("
        SELECT service, COUNT(*) as count, 
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM appointments 
        WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY service
        ORDER BY count DESC
    ");
    $stats['services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Активные промо-кампании
    $stmt = $pdo->query("
        SELECT * FROM promotion_campaigns 
        WHERE status = 'active' AND end_date >= NOW()
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stats['active_promos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Новые записи
    $stmt = $pdo->query("
        SELECT * FROM appointments 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stats['recent_appointments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Статистика за сегодня
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_today,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_today,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_today
        FROM appointments 
        WHERE DATE(appointment_date) = CURDATE()
    ");
    $stats['today_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $stats;
}

$stats = getDashboardStats($pdo);
$ai_recommendations = $promoManager->getAIRecommendations();

// Получаем список услуг для продвижения
$stmt = $pdo->query("SELECT * FROM service_promotions ORDER BY demand_level, service_name");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>АвтоЛайф - Расширенная админ-панель</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #000000;
            --secondary: #0a0a0a;
            --accent: #b22222;
            --accent-light: #ff6b6b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --text: #f5f5f5;
            --text-secondary: #a1a1aa;
            --border: #333333;
            --card-bg: #151515;
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }
        
        /* Сайдбар */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(20, 20, 20, 0.95);
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        
        .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        
        .logo img {
            max-width: 150px;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 25px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: rgba(178, 34, 34, 0.1);
            color: var(--accent);
            border-left-color: var(--accent);
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        .user-profile {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Основной контент */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .header h1 {
            font-size: 1.8rem;
            background: linear-gradient(45deg, var(--accent), var(--accent-light));
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Карточки */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--card-bg) 0%, #1e1e1e 100%);
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid var(--accent);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            margin: 10px 0;
        }
        
        /* Быстрый запуск промо */
        .quick-promo-section {
            background: linear-gradient(135deg, var(--card-bg) 0%, #1e1e1e 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }
        
        .quick-promo-section h2 {
            margin-bottom: 20px;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .service-card {
            background: rgba(30, 30, 30, 0.8);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .service-card:hover {
            background: rgba(178, 34, 34, 0.1);
            border-color: var(--accent);
        }
        
        .service-card.selected {
            background: rgba(178, 34, 34, 0.2);
            border-color: var(--accent);
        }
        
        .service-icon {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 10px;
        }
        
        .service-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .service-price {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .promo-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .discount-slider {
            flex: 1;
        }
        
        .discount-value {
            min-width: 80px;
            text-align: center;
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--accent);
        }
        
        .launch-btn {
            background: linear-gradient(45deg, var(--accent), var(--accent-light));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.3s ease;
        }
        
        .launch-btn:hover {
            transform: scale(1.05);
        }
        
        /* AI рекомендации */
        .ai-recommendations {
            background: linear-gradient(135deg, var(--card-bg) 0%, #1e1e1e 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid var(--info);
        }
        
        .recommendation-card {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .recommendation-content h4 {
            color: var(--info);
            margin-bottom: 5px;
        }
        
        .recommendation-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .apply-btn {
            background: var(--info);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        /* Таблицы */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
        }
        
        th {
            background: rgba(40, 40, 40, 0.8);
            padding: 15px;
            text-align: left;
            color: var(--accent);
            font-weight: 600;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        /* Уведомления */
        .notification {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification.success {
            background: rgba(16, 185, 129, 0.2);
            border-left: 4px solid var(--success);
            color: var(--success);
        }
        
        .notification.error {
            background: rgba(239, 68, 68, 0.2);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }
        
        /* Мобильная адаптация */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .nav-item span {
                display: none;
            }
            
            .logo img {
                max-width: 40px;
            }
            
            .service-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .promo-controls {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Сайдбар -->
    <div class="sidebar">
        <div class="logo">
            <img src="IMG/Лого.png" alt="АвтоЛайф">
        </div>
        
        <nav class="nav-menu">
            <a href="#dashboard" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Дашборд</span>
            </a>
            <a href="#promotions" class="nav-item">
                <i class="fas fa-bullhorn"></i>
                <span>Продвижение</span>
            </a>
            <a href="#appointments" class="nav-item">
                <i class="fas fa-calendar-check"></i>
                <span>Записи</span>
            </a>
            <a href="#analytics" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Аналитика</span>
            </a>
            <a href="#clients" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Клиенты</span>
            </a>
            <a href="#settings" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Настройки</span>
            </a>
        </nav>
        
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fas fa-user-circle" style="font-size: 2rem;"></i>
            </div>
            <div class="user-info">
                <div class="username"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></div>
                <a href="logout.php" style="color: var(--accent); font-size: 0.8rem;">Выйти</a>
            </div>
        </div>
    </div>
    
    <!-- Основной контент -->
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-rocket"></i> Центр продвижения АвтоЛайф</h1>
            <div class="header-actions">
                <button class="launch-btn" onclick="showAdvancedPromo()">
                    <i class="fas fa-plus"></i> Новая кампания
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['promo_success'])): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($_SESSION['promo_success']) ?></span>
            </div>
            <?php unset($_SESSION['promo_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['promo_error'])): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($_SESSION['promo_error']) ?></span>
            </div>
            <?php unset($_SESSION['promo_error']); ?>
        <?php endif; ?>
        
        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-calendar-day"></i> Записи сегодня</h3>
                <div class="stat-value"><?= $stats['today_stats']['total_today'] ?? 0 ?></div>
                <div class="stat-details">
                    <span style="color: var(--success);">✓ <?= $stats['today_stats']['completed_today'] ?? 0 ?></span>
                    <span style="color: var(--warning); margin-left: 10px;">⏳ <?= $stats['today_stats']['pending_today'] ?? 0 ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-fire"></i> Активные промо</h3>
                <div class="stat-value"><?= count($stats['active_promos'] ?? []) ?></div>
                <div class="stat-details">кампаний</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-chart-line"></i> Конверсия</h3>
                <div class="stat-value">
                    <?php 
                    $totalAppointments = count($stats['services'] ?? []);
                    $completed = array_sum(array_column($stats['services'] ?? [], 'completed'));
                    echo $totalAppointments > 0 ? round(($completed / $totalAppointments) * 100) : 0;
                    ?>%
                </div>
                <div class="stat-details">успешных записей</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-robot"></i> AI рекомендации</h3>
                <div class="stat-value"><?= count($ai_recommendations) ?></div>
                <div class="stat-details">готовы к запуску</div>
            </div>
        </div>
        
        <!-- Быстрый запуск промо -->
        <div class="quick-promo-section" id="quickPromo">
            <h2><i class="fas fa-bolt"></i> Быстрый запуск продвижения</h2>
            
            <div class="service-grid" id="serviceGrid">
                <?php foreach ($services as $service): ?>
                    <div class="service-card" 
                         data-service="<?= htmlspecialchars($service['service_name']) ?>"
                         data-base-price="<?= $service['base_price'] ?>"
                         onclick="selectService(this)">
                        <div class="service-icon">
                            <?php 
                            $icons = [
                                'Пленки' => 'fas fa-film',
                                'Химчистка' => 'fas fa-soap',
                                'Тонировка' => 'fas fa-tint',
                                'Детейлинг' => 'fas fa-spray-can',
                                'Полировка' => 'fas fa-star',
                                'Антидождь' => 'fas fa-cloud-rain'
                            ];
                            $icon = $icons[$service['service_name']] ?? 'fas fa-car';
                            ?>
                            <i class="<?= $icon ?>"></i>
                        </div>
                        <div class="service-name"><?= htmlspecialchars($service['service_name']) ?></div>
                        <div class="service-price">
                            <?php if ($service['promo_active']): ?>
                                <span style="text-decoration: line-through; color: #999;">
                                    <?= number_format($service['base_price'], 0, '', ' ') ?> ₽
                                </span>
                                <br>
                                <span style="color: var(--accent); font-weight: 600;">
                                    <?= number_format($service['promo_price'], 0, '', ' ') ?> ₽
                                </span>
                            <?php else: ?>
                                <?= number_format($service['base_price'], 0, '', ' ') ?> ₽
                            <?php endif; ?>
                        </div>
                        <?php if ($service['promo_active']): ?>
                            <div style="margin-top: 5px; font-size: 0.8rem; color: var(--success);">
                                <i class="fas fa-check"></i> Акция активна
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <form method="post" id="quickPromoForm">
                <input type="hidden" name="quick_promo" value="1">
                <input type="hidden" name="service" id="selectedService">
                
                <div class="promo-controls">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; color: var(--text-secondary);">
                            Скидка: <span id="discountValue">20</span>%
                        </label>
                        <div class="discount-slider">
                            <input type="range" 
                                   id="discountSlider" 
                                   name="discount" 
                                   min="5" 
                                   max="50" 
                                   step="5" 
                                   value="20"
                                   oninput="updateDiscountValue(this.value)"
                                   style="width: 100%;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; color: var(--text-secondary);">
                            Длительность
                        </label>
                        <select name="duration" style="padding: 10px; border-radius: 6px; background: var(--card-bg); color: white; border: 1px solid var(--border);">
                            <option value="3">3 дня</option>
                            <option value="7" selected>7 дней</option>
                            <option value="14">14 дней</option>
                            <option value="30">30 дней</option>
                        </select>
                    </div>
                    
                    <div class="discount-value">
                        <span id="finalPrice">0</span> ₽
                    </div>
                    
                    <button type="submit" class="launch-btn" id="launchBtn" disabled>
                        <i class="fas fa-rocket"></i> Запустить промо
                    </button>
                </div>
            </form>
        </div>
        
        <!-- AI рекомендации -->
        <?php if (!empty($ai_recommendations)): ?>
            <div class="ai-recommendations">
                <h2><i class="fas fa-robot"></i> AI рекомендации по продвижению</h2>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    На основе анализа данных система предлагает следующие промо-акции:
                </p>
                
                <?php foreach ($ai_recommendations as $rec): ?>
                    <div class="recommendation-card">
                        <div class="recommendation-content">
                            <h4><?= htmlspecialchars($rec['service']) ?></h4>
                            <p><?= htmlspecialchars($rec['message']) ?></p>
                            <div style="margin-top: 10px;">
                                <span style="color: var(--accent); font-weight: 600;">
                                    Рекомендуемая скидка: <?= $rec['suggested_discount'] ?>%
                                </span>
                                <span style="margin-left: 15px; padding: 3px 8px; background: rgba(245, 158, 11, 0.2); border-radius: 4px; font-size: 0.8rem; color: var(--warning);">
                                    Приоритет: <?= $rec['priority'] == 'high' ? 'Высокий' : 'Средний' ?>
                                </span>
                            </div>
                        </div>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="apply_ai_recommendation" value="1">
                            <input type="hidden" name="service" value="<?= htmlspecialchars($rec['service']) ?>">
                            <input type="hidden" name="suggested_discount" value="<?= $rec['suggested_discount'] ?>">
                            <button type="submit" class="apply-btn">
                                <i class="fas fa-play"></i> Применить
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Активные промо-кампании -->
        <div class="quick-promo-section">
            <h2><i class="fas fa-running"></i> Активные промо-кампании</h2>
            
            <?php if (!empty($stats['active_promos'])): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Услуга</th>
                                <th>Скидка</th>
                                <th>До конца</th>
                                <th>Конверсий</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['active_promos'] as $promo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($promo['title']) ?></td>
                                    <td><?= htmlspecialchars($promo['service_type']) ?></td>
                                    <td>
                                        <span style="color: var(--accent); font-weight: 600;">
                                            -<?= $promo['discount_value'] ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $end = new DateTime($promo['end_date']);
                                        $now = new DateTime();
                                        $diff = $now->diff($end);
                                        echo $diff->days . ' дней';
                                        ?>
                                    </td>
                                    <td><?= $promo['conversions'] ?></td>
                                    <td>
                                        <span style="padding: 5px 10px; background: rgba(16, 185, 129, 0.2); color: var(--success); border-radius: 20px; font-size: 0.8rem;">
                                            Активна
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 30px;">
                    <i class="fas fa-info-circle"></i> Нет активных промо-кампаний
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Последние записи -->
        <div class="quick-promo-section">
            <h2><i class="fas fa-history"></i> Последние записи</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Телефон</th>
                            <th>Услуга</th>
                            <th>Дата</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_appointments'] as $appointment): ?>
                            <tr>
                                <td>#<?= $appointment['id'] ?></td>
                                <td><?= htmlspecialchars($appointment['client_name']) ?></td>
                                <td><?= htmlspecialchars($appointment['client_phone']) ?></td>
                                <td><?= htmlspecialchars($appointment['service']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($appointment['appointment_date'])) ?></td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $statusTexts = [
                                        'pending' => 'Ожидание',
                                        'confirmed' => 'Подтверждена',
                                        'completed' => 'Выполнена',
                                        'cancelled' => 'Отменена'
                                    ];
                                    $color = $statusColors[$appointment['status']] ?? 'secondary';
                                    ?>
                                    <span style="padding: 5px 10px; background: rgba(var(--<?= $color ?>-rgb, 59, 130, 246), 0.2); color: var(--<?= $color ?>); border-radius: 20px; font-size: 0.8rem;">
                                        <?= $statusTexts[$appointment['status']] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let selectedService = null;
        let basePrice = 0;
        
        function selectService(card) {
            // Снимаем выделение со всех карточек
            document.querySelectorAll('.service-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Выделяем выбранную карточку
            card.classList.add('selected');
            
            // Сохраняем данные
            selectedService = card.dataset.service;
            basePrice = parseFloat(card.dataset.basePrice);
            
            // Активируем кнопку и обновляем форму
            document.getElementById('selectedService').value = selectedService;
            document.getElementById('launchBtn').disabled = false;
            
            // Пересчитываем цену
            updateDiscountValue(document.getElementById('discountSlider').value);
        }
        
        function updateDiscountValue(value) {
            document.getElementById('discountValue').textContent = value;
            
            if (basePrice > 0) {
                const discount = parseFloat(value);
                const finalPrice = basePrice * (1 - discount / 100);
                document.getElementById('finalPrice').textContent = 
                    finalPrice.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }
        }
        
        function showAdvancedPromo() {
            Swal.fire({
                title: 'Расширенное продвижение',
                html: `
                    <div style="text-align: left;">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: #666;">Название кампании</label>
                            <input type="text" id="campaignName" class="swal2-input" placeholder="Например: Летняя тонировка">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: #666;">Услуга</label>
                            <select id="campaignService" class="swal2-input">
                                <option value="">Выберите услугу</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= htmlspecialchars($service['service_name']) ?>">
                                        <?= htmlspecialchars($service['service_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: #666;">Тип скидки</label>
                            <select id="discountType" class="swal2-input" onchange="toggleDiscountFields()">
                                <option value="percent">Процент (%)</option>
                                <option value="fixed">Фиксированная сумма</option>
                                <option value="package">Пакет услуг</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: #666;">Значение скидки</label>
                            <input type="number" id="discountAmount" class="swal2-input" placeholder="20">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: #666;">Длительность (дней)</label>
                            <input type="number" id="campaignDuration" class="swal2-input" value="7" min="1" max="365">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; color: #666;">
                                <input type="checkbox" id="autoRenew"> Автопродление
                            </label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Создать кампанию',
                cancelButtonText: 'Отмена',
                preConfirm: () => {
                    const name = document.getElementById('campaignName').value;
                    const service = document.getElementById('campaignService').value;
                    const discount = document.getElementById('discountAmount').value;
                    
                    if (!name || !service || !discount) {
                        Swal.showValidationMessage('Заполните все обязательные поля');
                        return false;
                    }
                    
                    return { name, service, discount };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Кампания создана!',
                        text: `Промо-акция "${result.value.name}" успешно создана`,
                        icon: 'success',
                        timer: 2000
                    });
                    
                    // В реальном приложении здесь будет AJAX запрос
                    setTimeout(() => location.reload(), 2000);
                }
            });
        }
        
        function toggleDiscountFields() {
            const type = document.getElementById('discountType').value;
            const amountInput = document.getElementById('discountAmount');
            
            if (type === 'percent') {
                amountInput.placeholder = '20';
                amountInput.min = '1';
                amountInput.max = '100';
            } else if (type === 'fixed') {
                amountInput.placeholder = '1000';
                amountInput.min = '1';
                amountInput.max = '100000';
            } else {
                amountInput.placeholder = 'Количество услуг в пакете';
                amountInput.min = '2';
                amountInput.max = '10';
            }
        }
        
        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            // Автоматически выбираем первую услугу со спросом "low"
            const lowDemandCards = document.querySelectorAll('[data-demand="low"]');
            if (lowDemandCards.length > 0) {
                selectService(lowDemandCards[0]);
            }
            
            // Навигация по якорям
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = this.getAttribute('href').substring(1);
                    
                    // Убираем активный класс у всех
                    document.querySelectorAll('.nav-item').forEach(nav => {
                        nav.classList.remove('active');
                    });
                    
                    // Добавляем активный класс текущему
                    this.classList.add('active');
                    
                    // Прокрутка к разделу
                    const element = document.getElementById(target);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });
        
        // Автообновление каждые 2 минуты
        setInterval(() => {
            // Только если пользователь активен
            if (!document.hidden) {
                fetch(window.location.href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    // Здесь можно обновить только определенные части страницы
                    console.log('Auto-refresh check');
                });
            }
        }, 120000);
    </script>
</body>
</html>