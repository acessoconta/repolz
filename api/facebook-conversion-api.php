<?php
// Headers já definidos pelo roteador
header('Content-Type: application/json');

// Configurações da API de Conversões do Facebook
$PIXEL_ID = '1372007041458155'; // Seu Pixel ID
$ACCESS_TOKEN = 'EAAOZBcaU5ZAqUBRWto9BmyVbtaOoh2QfXxKYii5mZCZB3luAxKi2ZBYhNNZBkANeJx9dRJ1fxTVBt6Ro0Wxbk23i3BPqpo8pRaHGWNKZAuMMaPQTGMNm3GZBTpzD0Ki8MYlOaLloQq5ZBgqrTpUpXGxmFwrLoXpvHyfdUfqbGwNu1ripJtdw9eE0PS6kB4ADujgZDZD'; // Substitua pelo seu token de acesso
$API_VERSION = 'v21.0';

// Recebe dados JSON do body da requisição
$input = json_decode(file_get_contents('php://input'), true);

// Valida dados obrigatórios
if (!isset($input['event_name']) || !isset($input['user_data'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Dados obrigatórios não fornecidos'
    ]);
    exit;
}

try {
    // Função para converter em hash SHA256
    function hashData($data) {
        if (empty($data)) return null;
        return hash('sha256', strtolower(trim($data)));
    }

    // Prepara os dados do usuário com hash
    $userData = [
        'client_ip_address' => $input['user_data']['client_ip_address'] ?? $_SERVER['REMOTE_ADDR'],
        'client_user_agent' => $input['user_data']['client_user_agent'] ?? $_SERVER['HTTP_USER_AGENT']
    ];

    // Adiciona email com hash se fornecido
    if (!empty($input['user_data']['email'])) {
        $userData['em'] = [hashData($input['user_data']['email'])];
    }

    // Adiciona telefone com hash se fornecido
    if (!empty($input['user_data']['phone'])) {
        $phone = preg_replace('/\D/', '', $input['user_data']['phone']);
        $userData['ph'] = [hashData($phone)];
    }

    // Adiciona nome com hash se fornecido
    if (!empty($input['user_data']['first_name'])) {
        $userData['fn'] = [hashData($input['user_data']['first_name'])];
    }

    if (!empty($input['user_data']['last_name'])) {
        $userData['ln'] = [hashData($input['user_data']['last_name'])];
    }

    // Adiciona CPF com hash se fornecido
    if (!empty($input['user_data']['cpf'])) {
        $cpf = preg_replace('/\D/', '', $input['user_data']['cpf']);
        $userData['external_id'] = [hashData($cpf)];
    }

    // Adiciona cookies do Facebook se fornecidos
    if (!empty($input['user_data']['fbc'])) {
        $userData['fbc'] = $input['user_data']['fbc'];
    }

    if (!empty($input['user_data']['fbp'])) {
        $userData['fbp'] = $input['user_data']['fbp'];
    }

    // Prepara custom_data
    $customData = [
        'currency' => $input['custom_data']['currency'] ?? 'BRL',
        'value' => $input['custom_data']['value'] ?? 86.40
    ];

    // Adiciona content_ids se fornecido
    if (!empty($input['custom_data']['content_ids'])) {
        $customData['content_ids'] = $input['custom_data']['content_ids'];
    }

    // Adiciona content_type se fornecido
    if (!empty($input['custom_data']['content_type'])) {
        $customData['content_type'] = $input['custom_data']['content_type'];
    }

    // Prepara o evento
    $event = [
        'event_name' => $input['event_name'],
        'event_time' => $input['event_time'] ?? time(),
        'event_source_url' => $input['event_source_url'] ?? (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'https://' . $_SERVER['HTTP_HOST']),
        'action_source' => $input['action_source'] ?? 'website',
        'user_data' => $userData,
        'custom_data' => $customData
    ];

    // Adiciona event_id se fornecido (para deduplicação)
    if (!empty($input['event_id'])) {
        $event['event_id'] = $input['event_id'];
    }

    // Adiciona transaction_id se fornecido
    if (!empty($input['transaction_id'])) {
        $event['event_id'] = $input['transaction_id'];
    }

    // Prepara o payload
    $payload = [
        'data' => [$event]
    ];

    // Log dos dados sendo enviados (sem informações sensíveis)
    error_log("[Facebook CAPI] 📤 Enviando evento: " . $input['event_name']);
    error_log("[Facebook CAPI] 💰 Valor: " . $customData['value'] . " " . $customData['currency']);

    // Envia para a API de Conversões do Facebook
    $url = "https://graph.facebook.com/{$API_VERSION}/{$PIXEL_ID}/events";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'data' => json_encode($payload['data']),
        'access_token' => $ACCESS_TOKEN
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[Facebook CAPI] ❌ Erro cURL: " . $curlError);
        throw new Exception("Erro na requisição: " . $curlError);
    }

    $result = json_decode($response, true);

    error_log("[Facebook CAPI] 📊 HTTP Status: " . $httpCode);
    error_log("[Facebook CAPI] 📄 Resposta: " . $response);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode([
            'success' => true,
            'message' => 'Evento enviado com sucesso para Facebook CAPI',
            'facebook_response' => $result
        ]);
    } else {
        throw new Exception("Erro na API do Facebook: HTTP " . $httpCode . " - " . $response);
    }

} catch (Exception $e) {
    error_log("[Facebook CAPI] ❌ Erro: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
