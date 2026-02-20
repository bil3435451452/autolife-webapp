<?php
require_once 'config.php';

header('Content-Type: application/json');

// Простой тест - всегда возвращаем успех
echo json_encode([
    'success' => true,
    'message' => 'Тестовый ответ от сервера',
    'test_data' => [
        'name' => 'Тестовое имя',
        'phone' => '79999999999'
    ]
]);
?>