<?php
require_once 'config.php';

if (!isDbConnected()) {
    die("Нет подключения к БД");
}

$queries = [
    "CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_name VARCHAR(100) NOT NULL,
        client_phone VARCHAR(20) NOT NULL,
        car_brand VARCHAR(50) NOT NULL,
        car_model VARCHAR(50) NOT NULL,
        service VARCHAR(100) NOT NULL,
        appointment_date DATETIME NOT NULL,
        additional_info TEXT,
        status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS site_visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        user_agent TEXT,
        page_visited VARCHAR(500),
        visit_time DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS busy_dates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL UNIQUE,
        reason VARCHAR(200),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($queries as $query) {
    try {
        $pdo->exec($query);
        echo "<p style='color: green;'>✓ Таблица создана/проверена</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Ошибка создания таблицы: " . $e->getMessage() . "</p>";
    }
}

echo "<p><strong>Готово!</strong> Все таблицы проверены/созданы.</p>";
?>