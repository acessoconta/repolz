<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

// ---- Configurações Mangofy ----
$MANGOFY_STORE_CODE = '637fa1ec5a1a869958178977646647b9';
$MANGOFY_TOKEN      = '2c8d6500e77fdd9839e78585e10c506937slc9z9m0ajc2zvqthb8wmzpydmqok';
$MANGOFY_API_URL    = 'https://checkout.mangofy.com.br/api/v1/payment';

// payment_code retornado pela Mangofy no gerarpix (ex: "vpaon11lzo")
$paymentCode =
    ($input['id']            ?? null) ?:
    ($input['transactionId'] ?? null) ?:
    ($_GET['transactionId']  ?? null);

if (!$paymentCode) {
    echo json_encode(['status' => 'waiting_payment', 'message' => 'ID da transação não encontrado.']);
    exit;
}

$ch = curl_init($MANGOFY_API_URL.'/'.$paymentCode);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: '.$MANGOFY_TOKEN,
        'Store-Code: '.$MANGOFY_STORE_CODE,
    ],
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['status' => 'error', 'message' => 'Erro CURL: '.$curlError]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao verificar status', 'httpCode' => $httpCode]);
    exit;
}

$decoded = json_decode($response, true);
if ($decoded === null) {
    echo json_encode(['status' => 'error', 'message' => 'Resposta inválida']);
    exit;
}

// Mangofy retorna: { data: { payment_status: "approved" } }
$statusRaw = $decoded['data']['payment_status'] ?? $decoded['payment_status'] ?? $decoded['status'] ?? 'waiting_payment';

$statusMap = [
    'paid'        => 'paid',
    'approved'    => 'paid',
    'completed'   => 'paid',
    'pending'     => 'waiting_payment',
    'waiting'     => 'waiting_payment',
    'cancelled'   => 'cancelled',
    'canceled'    => 'cancelled',
    'refunded'    => 'cancelled',
    'chargeback'  => 'cancelled',
    'expired'     => 'cancelled',
];

$status = $statusMap[strtolower($statusRaw)] ?? 'waiting_payment';

echo json_encode(['status' => $status]);
