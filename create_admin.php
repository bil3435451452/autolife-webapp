<?php
// Скрипт для создания администратора
$host = 'localhost';
$dbname = 'ct50507_autolife';
$username = 'ct50507_autolife';
$password = 'disKym-damve7-mijkat';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Пароль: autolife2024
    $password_hash = password_hash('autolife2024', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
    $stmt->execute(['admin', $password_hash]);
    
    echo "Администратор успешно создан!<br>";
    echo "Логин: admin<br>";
    echo "Пароль: autolife2024<br>";
    echo "<a href='admin.php'>Перейти в админ-панель</a>";
    
} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>