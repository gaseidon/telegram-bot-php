<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'cx94920_tg');
define('DB_PASS', 'MUzzDj16');
define('DB_NAME', 'cx94920_tg');

define('BOT_TOKEN', '7820177935:AAH0kHBF9rri0N9qH8etx7p_h9I3gq6xbrY');


function sendMessage($chatId, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $postData = http_build_query([
        'chat_id' => $chatId,
        'text' => $text
    ]);

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData
        ]
    ];

    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}


function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}


function getUser($chatId) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO users (chat_id, balance) VALUES (?, 0.00)");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        return ['chat_id' => $chatId, 'balance' => 0.00];
    } else {
        return $result->fetch_assoc();
    }
}


function updateBalance($chatId, $amount) {
    $conn = connectDB();
    $conn->begin_transaction();

    try {
        // Получаем текущий баланс
        $stmt = $conn->prepare("SELECT balance FROM users WHERE chat_id = ? FOR UPDATE");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $newBalance = $user['balance'] + $amount;

        if ($newBalance < 0) {
            $conn->rollback();
            return false;
        }

        // Обновляем баланс
        $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE chat_id = ?");
        $stmt->bind_param("di", $newBalance, $chatId);
        $stmt->execute();

        $conn->commit();
        return $newBalance;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}


$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'];


    $user = getUser($chatId);


    $amount = str_replace(',', '.', $text);
    if (is_numeric($amount)) {
        $amount = (float)$amount;
        $newBalance = updateBalance($chatId, $amount);

        if ($newBalance === false) {
            sendMessage($chatId, "Ошибка: Недостаточно средств на счете.");
        } else {
            sendMessage($chatId, "Баланс обновлен: $" . number_format($newBalance, 2));
        }
    } else {
        sendMessage($chatId, "Пожалуйста, введите число для начисления или списания средств.");
    }
}
