<?php
require_once 'config.php';

// Устанавливаем заголовок для JSON
header('Content-Type: application/json');

// Разрешаем CORS если нужно
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Только POST запросы разрешены');
    }

    // Проверяем подключение к БД
    if (!isDbConnected()) {
        throw new Exception('Нет подключения к базе данных');
    }

    // Получаем данные из запроса
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Если JSON не удалось распарсить, пробуем получить из POST
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }

    // Проверяем обязательные поля
    $required = ['name', 'phone', 'carBrand', 'carModel', 'service', 'date'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Обязательное поле '$field' не заполнено");
        }
    }

    // Подготавливаем данные
    $name = trim($input['name']);
    $phone = trim($input['phone']);
    $carBrand = trim($input['carBrand']);
    $carModel = trim($input['carModel']);
    $service = trim($input['service']);
    $date = trim($input['date']);
    $additionalInfo = trim($input['additionalInfo'] ?? '');

    // Валидация телефона
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($phone) < 10) {
        throw new Exception('Некорректный номер телефона');
    }

    // Валидация даты
    $appointment_date = DateTime::createFromFormat('Y-m-d H:i', $date);
    if (!$appointment_date) {
        throw new Exception('Неверный формат даты');
    }

    // Проверяем что дата не в прошлом
    $now = new DateTime();
    if ($appointment_date < $now) {
        throw new Exception('Нельзя записаться на прошедшую дату');
    }

    // Сохраняем запись в БД
    $stmt = $pdo->prepare("INSERT INTO appointments (client_name, client_phone, car_brand, car_model, service, appointment_date, additional_info) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $carBrand, $carModel, $service, $appointment_date->format('Y-m-d H:i:s'), $additionalInfo]);
    
    $appointment_id = $pdo->lastInsertId();

    // Логируем успешное сохранение
    error_log("Appointment saved successfully. ID: $appointment_id");

    echo json_encode([
        'success' => true,
        'message' => 'Запись успешно создана! Мы свяжемся с вами для подтверждения.',
        'appointment_id' => $appointment_id
    ]);

} catch (Exception $e) {
    // Логируем ошибку
    error_log("API Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// После успешного сохранения записи в БД
require_once 'telegram_notifications.php';

$telegram = new TelegramNotifier("8064907128:AAHZnMzoyq-a7yVZYrUm-iWjV7GyvBVZtZ4", "813270618");
$telegram->sendNewAppointmentNotification($appointmentData);

// Или для нескольких администраторов
$admin_chat_ids = ["ID1", "ID2", "ID3"];
foreach ($admin_chat_ids as $chat_id) {
    $telegram = new TelegramNotifier("8064907128:AAHZnMzoyq-a7yVZYrUm-iWjV7GyvBVZtZ4", $chat_id);
    $telegram->sendNewAppointmentNotification($appointmentData);
}
?>