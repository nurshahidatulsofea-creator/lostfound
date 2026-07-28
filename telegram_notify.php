<?php
function sendTelegram($user_id, $message) {
    global $conn;
  
    $query = mysqli_query($conn, "SELECT telegram_chat_id FROM users WHERE user_id = '$user_id'");
    $user = mysqli_fetch_assoc($query);
    
    if (!$user || empty($user['telegram_chat_id'])) {
        return false;
    }
    
    $bot_token = '8888736756:AAHQ8Zy-xml42ORAp16VmkGkW1R-P6oJ3Nc';
    $chat_id = $user['telegram_chat_id'];
    
  
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code == 200;
}


function saveTelegramChatId($conn, $user_id, $chat_id) {
    mysqli_query($conn, "UPDATE users SET telegram_chat_id = '$chat_id' WHERE user_id = '$user_id'");
}
?>