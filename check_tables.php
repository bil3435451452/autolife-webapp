<?php
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Проверка структуры базы данных</h2>";

if (!isDbConnected()) {
    echo "<p style='color: red;'>Нет подключения к БД</p>";
    exit;
}

try {
    // Проверяем таблицу appointments
    $stmt = $pdo->query("DESCRIBE appointments");
    $columns = $stmt->fetchAll();
    
    echo "<h3>Структура таблицы appointments:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Показываем несколько последних записей
    echo "<h3>Последние 5 записей:</h3>";
    $stmt = $pdo->query("SELECT * FROM appointments ORDER BY id DESC LIMIT 5");
    $appointments = $stmt->fetchAll();
    
    if (count($appointments) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Имя</th><th>Телефон</th><th>Услуга</th><th>Дата</th><th>Статус</th></tr>";
        
        foreach ($appointments as $appointment) {
            echo "<tr>";
            echo "<td>{$appointment['id']}</td>";
            echo "<td>{$appointment['client_name']}</td>";
            echo "<td>{$appointment['client_phone']}</td>";
            echo "<td>{$appointment['service']}</td>";
            echo "<td>{$appointment['appointment_date']}</td>";
            echo "<td>{$appointment['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Записей нет</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>";
}
?>