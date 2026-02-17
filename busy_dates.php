<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Получаем все записи на ближайшие 30 дней
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+30 days'));
        
        $stmt = $pdo->prepare("SELECT DATE(appointment_date) as date, COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status != 'cancelled' GROUP BY DATE(appointment_date) HAVING COUNT(*) >= 8");
        $stmt->execute([$start_date, $end_date]);
        $busy_dates = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Также получаем даты из таблицы busy_dates
        $stmt = $pdo->query("SELECT date FROM busy_dates WHERE date >= CURDATE()");
        $manual_busy_dates = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Объединяем даты
        $all_busy_dates = array_unique(array_merge($busy_dates, $manual_busy_dates));
        
        echo json_encode(['busy_dates' => $all_busy_dates]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Ошибка при получении занятых дат: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Метод не поддерживается.']);
}
?>