<?php
function verifyRecaptcha($secretKey) {
    if (isset($_POST['g-recaptcha-response'])) {
        $recaptcha_response = $_POST['g-recaptcha-response'];
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        
        $data = [
            'secret' => $secretKey,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result);
        
        return $response->success;
    }
    return false;
}

// Использование
$secret_key = "6Le4FuQrAAAAAKb35nXkx96CfUo8TOYlaKvBeSzY";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyRecaptcha($secret_key)) {
        // Капча пройдена успешно
        echo "Капча пройдена!";
        // Продолжаем обработку формы
    } else {
        // Капча не пройдена
        echo "Ошибка проверки капчи!";
    }
}
?>