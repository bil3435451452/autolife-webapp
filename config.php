<?php
header('Content-Type: text/html; charset=utf-8');

// Реальные данные от хостинга
$host = 'localhost';
$dbname = 'ct50507_autolife';
$username = 'ct50507_autolife';
$password = 'disKym-damve7-mijkat';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username, 
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Логируем ошибку
    error_log("Database connection failed: " . $e->getMessage());
    
    // Для отладки можно временно показать ошибку
    if (isset($_GET['debug']) || $_SERVER['SERVER_NAME'] == 'localhost') {
        die("Database connection error: " . $e->getMessage());
    } else {
        // В продакшене просто возвращаем null
        $pdo = null;
    }
}

// Функция для проверки подключения
function isDbConnected() {
    global $pdo;
    return $pdo !== null;
}

// Функция для получения занятых дат
function getBusyDates() {
    global $pdo;
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->query("SELECT date FROM busy_dates WHERE date >= CURDATE()");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        error_log("Error getting busy dates: " . $e->getMessage());
        return [];
    }
}
?>