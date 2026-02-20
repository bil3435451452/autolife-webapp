<?php
// send-notification.php
header('Content-Type: application/json');

// Простая проверка авторизации
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($authHeader, 'Push') === false) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Подключение к БД
$host = 'localhost';
$dbname = 'ct50507_autolife';
$username = 'ct50507_autolife';
$password = 'disKym-damve7-mijkat';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("DB connection error: " . $e->getMessage());
    exit;
}

// VAPID ключи
define('VAPID_PUBLIC_KEY', 'BJbm5KwPee4Dnmc_tmBLNk17XJ4bDVJMZkxF7PKpIAA8u7yO-3Fh-jSECRljN4zuRU1A3fqQ6I8d7zwemkATwqs');
define('VAPID_PRIVATE_KEY', 'sbFMC7SMBroD-KqmgHIKCgKOzFMqNtAX79hkvrd-9h8');

// Получаем входные данные
$input = json_decode(file_get_contents('php://input'), true);

// Обработка новой записи
if (isset($input['appointment_data'])) {
    $appointment = $input['appointment_data'];
    
    // Получаем все активные подписки администраторов
    $stmt = $pdo->query("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE active = 1");
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($subscriptions)) {
        echo json_encode(['success' => true, 'message' => 'No active subscriptions']);
        exit;
    }
    
    // Формируем данные для уведомления
    $payload = json_encode([
        'title' => '🚗 Новая запись в АвтоЛайф',
        'body' => $appointment['client_name'] . ' - ' . $appointment['service'],
        'icon' => '/icons/icon-192x192.png',
        'badge' => '/icons/badge-72x72.png',
        'image' => '/icons/icon-512x512.png',
        'vibrate' => [200, 100, 200],
        'data' => [
            'url' => '/admin.php',
            'appointment_id' => $appointment['id'],
            'timestamp' => time()
        ],
        'actions' => [
            [
                'action' => 'open',
                'title' => '📋 Открыть',
                'icon' => '/icons/check.png'
            ],
            [
                'action' => 'close', 
                'title' => '❌ Закрыть',
                'icon' => '/icons/close.png'
            ]
        ]
    ]);
    
    $results = [];
    $sentCount = 0;
    $errorCount = 0;
    
    foreach ($subscriptions as $subscription) {
        $result = sendWebPush($subscription, $payload);
        $results[] = $result;
        
        if ($result['success']) {
            $sentCount++;
        } else {
            $errorCount++;
            error_log("Push failed for {$subscription['endpoint']}: {$result['error']}");
        }
    }
    
    echo json_encode([
        'success' => true,
        'sent' => $sentCount,
        'errors' => $errorCount,
        'total' => count($subscriptions)
    ]);
    exit;
}

// Функция отправки web push
function sendWebPush($subscription, $payload) {
    $endpoint = $subscription['endpoint'];
    
    // Определяем сервис push-уведомлений по endpoint
    if (strpos($endpoint, 'https://fcm.googleapis.com') !== false) {
        return sendFCMNotification($subscription, $payload);
    } else {
        return sendStandardWebPush($subscription, $payload);
    }
}

// Отправка через FCM (Firebase Cloud Messaging)
function sendFCMNotification($subscription, $payload) {
    $url = 'https://fcm.googleapis.com/fcm/send';
    
    $headers = [
        'Authorization: key=AAA...', // Ваш FCM ключ (если используется)
        'Content-Type: application/json',
        'TTL: 60'
    ];
    
    $data = [
        'to' => $subscription['endpoint'],
        'notification' => [
            'title' => '🚗 Новая запись в АвтоЛайф',
            'body' => json_decode($payload, true)['body'],
            'icon' => '/icons/icon-192x192.png',
            'click_action' => '/admin.php'
        ],
        'data' => [
            'url' => '/admin.php',
            'payload' => $payload
        ]
    ];
    
    return makeHttpRequest($url, $headers, json_encode($data));
}

// Стандартная отправка web push
function sendStandardWebPush($subscription, $payload) {
    $url = $subscription['endpoint'];
    
    // Генерируем заголовки для VAPID
    $vapidHeaders = generateVAPIDHeaders($url);
    
    $headers = [
        'Authorization: ' . $vapidHeaders['Authorization'],
        'Crypto-Key: ' . $vapidHeaders['Crypto-Key'],
        'Content-Type: application/octet-stream',
        'Content-Encoding: aesgcm',
        'TTL: 60'
    ];
    
    return makeHttpRequest($url, $headers, $payload);
}

// Генерация VAPID заголовков
function generateVAPIDHeaders($endpoint) {
    // Упрощенная реализация - в продакшене используйте библиотеку web-push
    $vapidClaims = [
        "sub" => "mailto:admin@avtolife.ru",
        "exp" => time() + 12 * 60 * 60
    ];
    
    // Здесь должна быть полная реализация JWT подписи
    // Для упрощения возвращаем заглушку
    return [
        'Authorization' => 'vapid t=eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.eyJzdWIiOiJtYWlsdG86YWRtaW5AYXZ0b2xpZmUucnUiLCJleHAiOj' . (time() + 43200) . 'fQ.signature',
        'Crypto-Key' => 'p256ecdsa=' . VAPID_PUBLIC_KEY
    ];
}

// Универсальная функция HTTP запроса
function makeHttpRequest($url, $headers, $data) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'error' => $error,
        'response' => $response
    ];
}

// Обработка тестовых уведомлений
if (isset($input['test']) && $input['test']) {
    $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE admin_id = ?");
    $stmt->execute([$input['admin_id']]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $payload = json_encode([
        'title' => $input['title'] ?? 'Тест от АвтоЛайф',
        'body' => $input['body'] ?? 'Это тестовое push-уведомление!',
        'icon' => '/icons/icon-192x192.png',
        'data' => ['url' => '/admin.php']
    ]);
    
    $results = [];
    foreach ($subscriptions as $subscription) {
        $results[] = sendWebPush($subscription, $payload);
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}
?>