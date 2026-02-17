<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? 'overview';
    
    switch($action) {
        case 'overview':
            echo json_encode(getOverviewStats($pdo));
            break;
        case 'visits':
            echo json_encode(getVisitsStats($pdo));
            break;
        case 'appointments':
            echo json_encode(getAppointmentsStats($pdo));
            break;
        case 'services':
            echo json_encode(getServicesStats($pdo));
            break;
        default:
            echo json_encode(['error' => 'Неизвестное действие']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}

function getOverviewStats($pdo) {
    // Общая статистика
    $today = date('Y-m-d');
    $week_ago = date('Y-m-d', strtotime('-7 days'));
    $month_ago = date('Y-m-d', strtotime('-30 days'));
    
    // Посещения за сегодня
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM site_visits WHERE DATE(visit_time) = ?");
    $stmt->execute([$today]);
    $visits_today = $stmt->fetch()['count'];
    
    // Посещения за неделю
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM site_visits WHERE visit_time >= ?");
    $stmt->execute([$week_ago]);
    $visits_week = $stmt->fetch()['count'];
    
    // Всего посещений
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM site_visits");
    $total_visits = $stmt->fetch()['count'];
    
    // Записи за сегодня
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $appointments_today = $stmt->fetch()['count'];
    
    // Всего записей
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    $total_appointments = $stmt->fetch()['count'];
    
    // Уникальные посетители за неделю
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as count FROM site_visits WHERE visit_time >= ?");
    $stmt->execute([$week_ago]);
    $unique_visitors = $stmt->fetch()['count'];
    
    return [
        'visits_today' => $visits_today,
        'visits_week' => $visits_week,
        'total_visits' => $total_visits,
        'appointments_today' => $appointments_today,
        'total_appointments' => $total_appointments,
        'unique_visitors' => $unique_visitors
    ];
}

function getVisitsStats($pdo) {
    $days = 30;
    $stats = [];
    
    for ($i = $days-1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as visits, COUNT(DISTINCT ip_address) as unique_visitors FROM site_visits WHERE DATE(visit_time) = ?");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        
        $stats[] = [
            'date' => $date,
            'visits' => $result['visits'],
            'unique_visitors' => $result['unique_visitors']
        ];
    }
    
    return $stats;
}

function getAppointmentsStats($pdo) {
    $days = 30;
    $stats = [];
    
    for ($i = $days-1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        
        $stats[] = [
            'date' => $date,
            'appointments' => $result['count']
        ];
    }
    
    return $stats;
}

function getServicesStats($pdo) {
    $stmt = $pdo->query("SELECT service, COUNT(*) as count FROM appointments GROUP BY service ORDER BY count DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>