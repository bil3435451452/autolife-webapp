<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    if (!isDbConnected()) {
        throw new Exception('Нет подключения к базе данных');
    }

    $sql = "SELECT DATE_FORMAT(appointment_date, '%Y-%m-%d') as busy_date 
            FROM appointments 
            WHERE appointment_date >= CURDATE() 
            AND status != 'cancelled'
            GROUP BY DATE(appointment_date)
            HAVING COUNT(*) >= 8
            ORDER BY busy_date";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'busy_dates' => $dates
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>