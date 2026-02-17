<?php
require_once 'config.php';

header('Content-Type: application/json');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    $stmt = $pdo->prepare("SELECT * FROM appointments ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($appointments);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка при получении записей: ' . $e->getMessage()]);
}
?>