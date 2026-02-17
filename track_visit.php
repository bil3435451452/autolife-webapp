<?php
require_once 'config.php';

// Получаем данные о посещении
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$page_visited = $_SERVER['HTTP_REFERER'] ?? 'Direct';
$visit_time = date('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare("INSERT INTO site_visits (ip_address, user_agent, page_visited, visit_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$ip_address, $user_agent, $page_visited, $visit_time]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // Логируем ошибку, но не прерываем выполнение
    error_log("Ошибка при записи посещения: " . $e->getMessage());
}
?>