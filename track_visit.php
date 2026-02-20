<?php
require_once 'config.php';

// Всегда возвращаем JSON
header('Content-Type: application/json');

try {
    if (isDbConnected()) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $page_visited = $_SERVER['HTTP_REFERER'] ?? 'Direct';
        $visit_time = date('Y-m-d H:i:s');
        
        // Проверяем существование таблицы
        $stmt = $pdo->query("SHOW TABLES LIKE 'site_visits'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("INSERT INTO site_visits (ip_address, user_agent, page_visited, visit_time) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ip_address, $user_agent, $page_visited, $visit_time]);
        }
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // В случае ошибки все равно возвращаем успех, чтобы не ломать работу сайта
    error_log("Track visit error: " . $e->getMessage());
    echo json_encode(['success' => true]);
}
?>