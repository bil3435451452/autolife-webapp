<?php
// feedback_handler.php - Обработчик формы обратной связи с отправкой email

// Устанавливаем заголовки для CORS и JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Разрешаем preflight запросы
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Логирование ошибок
error_log("=== FEEDBACK HANDLER STARTED ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));

try {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Только POST запросы разрешены. Текущий метод: ' . $_SERVER['REQUEST_METHOD']);
    }

    // Определяем тип контента
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // Получаем данные в зависимости от типа контента
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents('php://input');
        error_log("Raw JSON input: " . $rawInput);
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ошибка декодирования JSON: ' . json_last_error_msg());
        }
    } else {
        // Форма отправлена как application/x-www-form-urlencoded или multipart/form-data
        error_log("Form data received:");
        error_log("POST data: " . print_r($_POST, true));
        $input = $_POST;
    }

    // Логируем все полученные данные для отладки
    error_log("Final input data: " . print_r($input, true));

    // Получаем данные из формы - используем ТОЛЬКО имена из HTML формы
    $name = isset($input['client-name']) ? trim($input['client-name']) : '';
    $phone = isset($input['client-phone']) ? trim($input['client-phone']) : '';
    $carBrand = isset($input['car-brand']) ? trim($input['car-brand']) : '';
    $carModel = isset($input['car-model']) ? trim($input['car-model']) : '';
    $service = isset($input['service']) ? trim($input['service']) : '';
    $date = isset($input['booking-date']) ? trim($input['booking-date']) : '';
    $additionalInfo = isset($input['additional-info']) ? trim($input['additional-info']) : '';

    error_log("Extracted values:");
    error_log("Name: " . $name);
    error_log("Phone: " . $phone);
    error_log("Car Brand: " . $carBrand);
    error_log("Car Model: " . $carModel);
    error_log("Service: " . $service);
    error_log("Date: " . $date);
    error_log("Additional Info: " . $additionalInfo);

    // Проверяем обязательные поля
    if (empty($name)) {
        throw new Exception('Обязательное поле "Имя" не заполнено');
    }
    if (empty($phone)) {
        throw new Exception('Обязательное поле "Телефон" не заполнено');
    }
    if (empty($carBrand)) {
        throw new Exception('Обязательное поле "Марка автомобиля" не заполнено');
    }
    if (empty($carModel)) {
        throw new Exception('Обязательное поле "Модель автомобиля" не заполнено');
    }
    if (empty($service)) {
        throw new Exception('Обязательное поле "Услуга" не заполнено');
    }
    if (empty($date)) {
        throw new Exception('Обязательное поле "Дата и время" не заполнено');
    }

    // Валидация телефона
    $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($phone_clean) < 10) {
        throw new Exception('Некорректный номер телефона: ' . $phone);
    }

    // Валидация даты
    $appointment_date = DateTime::createFromFormat('Y-m-d H:i', $date);
    if (!$appointment_date) {
        // Пробуем другой формат даты (с T)
        $appointment_date = DateTime::createFromFormat('Y-m-d\TH:i', $date);
        if (!$appointment_date) {
            throw new Exception('Неверный формат даты: ' . $date);
        }
    }

    // Проверяем что дата не в прошлом
    $now = new DateTime();
    if ($appointment_date < $now) {
        throw new Exception('Нельзя записаться на прошедшую дату');
    }

    // Форматируем дату для красивого отображения
    $formatted_date = $appointment_date->format('d.m.Y H:i');

    // Отправляем email уведомление
    $emailSent = sendEmailNotification([
        'name' => $name,
        'phone' => $phone,
        'carBrand' => $carBrand,
        'carModel' => $carModel,
        'service' => $service,
        'date' => $formatted_date,
        'additionalInfo' => $additionalInfo
    ]);

    if (!$emailSent) {
        throw new Exception('Ошибка при отправке email уведомления');
    }

    // Логируем успешную отправку
    error_log("Email notification sent successfully for: " . $name . " - " . $phone);

    // Возвращаем успешный ответ
    echo json_encode([
        'success' => true,
        'message' => 'Запись успешно создана! Мы свяжемся с вами для подтверждения.'
    ]);

} catch (Exception $e) {
    // Логируем ошибку
    error_log("Feedback Handler Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'received_keys' => array_keys($input ?? []),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set'
        ]
    ]);
}

/**
 * Отправка email уведомления о новой заявке
 */
function sendEmailNotification($data) {
    $to = "mainmail@autolife-detail.ru";
    $subject = "Новая заявка с сайта AutoLife Detail - " . $data['name'];
    
    // Создаем HTML содержимое письма в стиле сайта
    $message = createEmailTemplate($data);
    
    // Устанавливаем заголовки для HTML письма
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@autolife-detail.ru" . "\r\n";
    $headers .= "Reply-To: no-reply@autolife-detail.ru" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Отправляем письмо
    return mail($to, $subject, $message, $headers);
}

/**
 * Создание HTML шаблона письма в стиле сайта
 */
function createEmailTemplate($data) {
    $html = '
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Новая заявка</title>
        <style>
            /* Основные стили в соответствии с сайтом */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: "Montserrat", sans-serif;
                background-color: #121212;
                color: #f5f5f5;
                line-height: 1.6;
                margin: 0;
                padding: 20px;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: #1e1e1e;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 10px 15px rgba(0, 0, 0, 0.775);
            }
            
            .email-header {
                background: #b22222;
                padding: 30px;
                text-align: center;
                color: white;
            }
            
            .email-header h1 {
                font-size: 24px;
                font-weight: 600;
                margin: 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .email-content {
                padding: 30px;
            }
            
            .appointment-card {
                background: #2a2a2a;
                border-radius: 8px;
                padding: 25px;
                margin-bottom: 20px;
                border-left: 4px solid #b22222;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 15px;
            }
            
            .info-item {
                margin-bottom: 12px;
            }
            
            .info-label {
                font-weight: 600;
                color: #aaaaaa;
                font-size: 14px;
                margin-bottom: 5px;
                display: block;
            }
            
            .info-value {
                font-size: 16px;
                color: #f5f5f5;
                font-weight: 500;
            }
            
            .additional-info {
                background: rgba(178, 34, 34, 0.1);
                padding: 15px;
                border-radius: 5px;
                margin-top: 15px;
                border-left: 3px solid #b22222;
            }
            
            .email-footer {
                background: #1a1a1a;
                padding: 20px;
                text-align: center;
                color: #aaaaaa;
                font-size: 14px;
                border-top: 1px solid #333333;
            }
            
            .highlight {
                color: #b22222;
                font-weight: 600;
            }
            
            @media (max-width: 768px) {
                .info-grid {
                    grid-template-columns: 1fr;
                }
                
                .email-content {
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <h1>🚗 Новая заявка с сайта</h1>
            </div>
            
            <div class="email-content">
                <div class="appointment-card">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="font-size: 18px; font-weight: 600; color: #b22222; margin-bottom: 10px;">
                            📅 Новая запись на услугу
                        </div>
                        <div style="font-size: 20px; font-weight: 600;">' . htmlspecialchars($data['service']) . '</div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">👤 Клиент:</span>
                            <div class="info-value highlight">' . htmlspecialchars($data['name']) . '</div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">📞 Телефон:</span>
                            <div class="info-value highlight">' . htmlspecialchars($data['phone']) . '</div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">🏎️ Марка авто:</span>
                            <div class="info-value">' . htmlspecialchars($data['carBrand']) . '</div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">🚙 Модель авто:</span>
                            <div class="info-value">' . htmlspecialchars($data['carModel']) . '</div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">🕐 Дата и время:</span>
                            <div class="info-value highlight">' . htmlspecialchars($data['date']) . '</div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">⚡ Услуга:</span>
                            <div class="info-value">' . htmlspecialchars($data['service']) . '</div>
                        </div>
                    </div>';

    // Добавляем дополнительную информацию, если она есть
    if (!empty($data['additionalInfo'])) {
        $html .= '
                    <div class="additional-info">
                        <div class="info-label">📝 Дополнительная информация:</div>
                        <div class="info-value">' . nl2br(htmlspecialchars($data['additionalInfo'])) . '</div>
                    </div>';
    }

    $html .= '
                </div>
                
                <div style="text-align: center; margin-top: 25px; padding: 15px; background: rgba(178, 34, 34, 0.1); border-radius: 5px;">
                    <div style="font-weight: 600; margin-bottom: 10px;">⚠️ Требуется подтверждение</div>
                    <div style="font-size: 14px; color: #aaaaaa;">
                        Пожалуйста, свяжитесь с клиентом для подтверждения записи
                    </div>
                </div>
            </div>
            
            <div class="email-footer">
                <div>📧 Это автоматическое уведомление от сайта AutoLife Detail</div>
                <div style="margin-top: 10px; font-size: 12px;">
                    ' . date('d.m.Y H:i') . '
                </div>
            </div>

             <div class="email-footer">
                <div>Перейти в админ-панель: https://autolife-detail.ru/admin.php</div>
                <div style="margin-top: 10px; font-size: 12px;">
                    ' . date('d.m.Y H:i') . '
                </div>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}
?>