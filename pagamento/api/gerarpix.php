<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

// ---- Configurações Mangofy ----
$MANGOFY_STORE_CODE = '637fa1ec5a1a869958178977646647b9';
$MANGOFY_TOKEN      = '2c8d6500e77fdd9839e78585e10c506937slc9z9m0ajc2zvqthb8wmzpydmqok';
$MANGOFY_API_URL    = 'https://checkout.mangofy.com.br/api/v1/payment';

function gerarNome() {
    $nomes     = ['Joao','Maria','Pedro','Ana','Carlos','Mariana','Lucas','Juliana','Fernando','Patricia'];
    $sobrenomes= ['Silva','Santos','Oliveira','Souza','Rodrigues','Ferreira','Alves','Pereira','Gomes','Martins'];
    return $nomes[array_rand($nomes)].' '.$sobrenomes[array_rand($sobrenomes)].' '.$sobrenomes[array_rand($sobrenomes)];
}

function gerarCpf() {
    $n = [];
    for ($i = 0; $i < 9; $i++) $n[$i] = rand(0,9);
    $soma = 0;
    for ($i = 0; $i < 9; $i++) $soma += $n[$i] * (10 - $i);
    $r = 11 - ($soma % 11); $dv1 = $r > 9 ? 0 : $r;
    $soma = 0;
    for ($i = 0; $i < 9; $i++) $soma += $n[$i] * (11 - $i);
    $soma += $dv1 * 2;
    $r = 11 - ($soma % 11); $dv2 = $r > 9 ? 0 : $r;
    return implode('', $n).$dv1.$dv2;
}

function gerarTelefone() {
    $ddd = ['11','21','31','41','51','61','71','81','91'];
    return $ddd[array_rand($ddd)].'9'.str_pad((string)rand(0,99999999), 8, '0', STR_PAD_LEFT);
}

function getClientIp() {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '177.0.0.1';
}

// amount em CENTAVOS vindo do JS
$amount = isset($input['amount']) ? intval($input['amount']) : 0;
if ($amount < 500) {
    echo json_encode(['success' => false, 'error' => 'Valor mínimo de R$ 5,00']);
    exit;
}

$userInfo = $input['userInfo'] ?? [];
$nome     = !empty($userInfo['nome']) ? $userInfo['nome'] : gerarNome();
$cpf      = !empty($userInfo['cpf'])  ? preg_replace('/\D/', '', $userInfo['cpf']) : gerarCpf();
$telefone = gerarTelefone();
$email    = strtolower(str_replace(' ', '.', explode(' ', $nome)[0])).'+'.uniqid().'@email.com';
$clientIp = getClientIp();

$payload = [
    'external_code'   => 'pedido-'.uniqid(),
    'payment_method'  => 'pix',
    'payment_format'  => 'regular',
    'installments'    => 1,
    'payment_amount'  => $amount,          // inteiro em centavos
    'shipping_amount' => 0,
    'postback_url'    => 'https://'.($_SERVER['HTTP_HOST'] ?? 'localhost').'/postback',
    'items'           => [
        [
            'title'    => 'Pagamento de Servico',
            'price'    => $amount,          // centavos
            'quantity' => 1,
        ]
    ],
    'customer' => [
        'name'     => $nome,
        'document' => $cpf,
        'email'    => $email,
        'phone'    => $telefone,
        'ip'       => $clientIp,
    ],
    'pix' => [
        'expires_in_days' => 1,
    ],
];

$ch = curl_init($MANGOFY_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: '.$MANGOFY_TOKEN,
        'Store-Code: '.$MANGOFY_STORE_CODE,
    ],
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Erro de conexão com a API', 'detail' => $curlError]);
    exit;
}

$decoded = json_decode($response, true);
if ($decoded === null) {
    echo json_encode(['success' => false, 'error' => 'Resposta inválida da API', 'raw' => $response, 'httpCode' => $httpCode]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    echo json_encode(['success' => false, 'error' => 'Erro retornado pela API de pagamento', 'response' => $decoded, 'httpCode' => $httpCode]);
    exit;
}

// Resposta real da Mangofy: pix.pix_qrcode_text e payment_code
$pixCode       = $decoded['pix']['pix_qrcode_text'] ?? null;
$transactionId = $decoded['payment_code'] ?? $decoded['id'] ?? null;

if (!$pixCode) {
    echo json_encode(['success' => false, 'error' => 'API não retornou código PIX', 'response' => $decoded]);
    exit;
}

echo json_encode([
    'success' => true,
    'amount'  => $amount,
    'data'    => [
        'transactionId' => $transactionId,
        'pix'           => [
            'pix_qr_code' => $pixCode,
            'qr_code'     => null,
        ]
    ]
]);
