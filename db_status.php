<?php
require_once 'config.php';

header('Content-Type: application/json');

// Простой ответ без сложных проверок
$response = [
    'database_connected' => false,
    'server_time' => date('Y-m-d H:i:s'),
    'status' => 'checking'
];

try {
    // Простая проверка подключения
    if ($pdo && $pdo->query("SELECT 1")) {
        $response['database_connected'] = true;
        $response['status'] = 'connected';
        
        // Базовая проверка таблиц
        $tables = ['appointments', 'site_visits', 'busy_dates'];
        $response['tables'] = [];
        
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                $response['tables'][$table] = $stmt->rowCount() > 0;
            } catch (Exception $e) {
                $response['tables'][$table] = false;
            }
        }
        
    } else {
        $response['error'] = 'No database connection';
        $response['status'] = 'disconnected';
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['status'] = 'error';
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>