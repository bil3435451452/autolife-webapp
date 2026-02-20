<?php
require_once 'config.php';

echo "<h2>Проверка подключения к БД</h2>";

if (isDbConnected()) {
    echo "<p style='color: green;'>✓ Подключение к БД успешно</p>";
    
    // Проверяем таблицы
    $tables = ['appointments', 'site_visits', 'busy_dates'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✓ Таблица '$table' существует</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Таблица '$table' не существует</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Ошибка проверки таблицы '$table': " . $e->getMessage() . "</p>";
        }
    }
    
} else {
    echo "<p style='color: red;'>✗ Ошибка подключения к БД</p>";
}

echo "<h2>Проверка файлов</h2>";

$files = [
    'IMG/NANO.webp',
    'IMG/Химчистка_кожаных_сидений.webp',
    'IMG/Детейлинг_мойка.webp',
    'IMG/site.webmanifest'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file существует</p>";
    } else {
        echo "<p style='color: red;'>✗ $file не найден</p>";
    }
}
?>