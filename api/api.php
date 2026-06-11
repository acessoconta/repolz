<?php
// Habilitar CORS para permitir requisições de qualquer origem
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Se for uma requisição OPTIONS, apenas retorna os headers e encerra
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Lê o corpo da requisição
$input = file_get_contents('php://input');
$postData = [];
parse_str($input, $postData);

// Verifica se o CPF está no corpo da requisição ou em $_POST
$cpf = '';
if (isset($postData['cpf'])) {
    $cpf = $postData['cpf'];
} else if (isset($_POST['cpf'])) {
    $cpf = $_POST['cpf'];
}

// Log para debug
error_log("Requisição recebida - INPUT: " . $input);
error_log("POST data: " . print_r($_POST, true));
error_log("CPF recebido: " . $cpf);

// Verifica se o CPF foi enviado
if (empty($cpf)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'CPF não fornecido']);
    exit;
}

// Remove caracteres não numéricos do CPF
$cpf = preg_replace('/[^0-9]/', '', $cpf);

// Verifica se o CPF tem 11 dígitos
if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'CPF inválido']);
    exit;
}

// Token da API (você deve fornecer o token correto)
$token = '5621';

// URL da API externa
$apiUrl = "https://searchapi.dnnl.live/consulta?token_api={$token}&cpf={$cpf}";

// Configuração do contexto para a requisição
$context = stream_context_create([
    'http' => [
        'ignore_errors' => true
    ]
]);

// Faz a requisição para a API externa
$response = file_get_contents($apiUrl, false, $context);

// Verifica se a requisição foi bem sucedida
if ($response === false) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Erro ao consultar a API externa']);
    exit;
}

// Retorna a resposta da API externa
echo $response;
exit;
