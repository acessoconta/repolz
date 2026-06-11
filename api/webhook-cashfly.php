<?php
/**
 * CASHFLY - Webhook de Notificações
 * Recebe eventos em tempo real da API CashFly.
 *
 * Eventos tratados:
 *   transaction.paid              → pagamento confirmado
 *   transaction.failed            → pagamento falhou
 *   transaction.cancelled         → cobrança cancelada
 *   transaction.expired           → cobrança expirada
 *   transaction.refunded          → estorno total
 *   transaction.partially_refunded→ estorno parcial
 *
 * Documentação: https://painel.cashflypay.com/docs
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// ============================================================
// CONFIGURAÇÃO — mesma chave usada em pagamento-cashfly.php
// ============================================================
define('CASHFLY_WEBHOOK_SECRET', '');   // preencha se configurou webhookSecret
// ============================================================

// ----------------------------------------------------------
// 1. Lê o payload
// ----------------------------------------------------------
$rawBody = file_get_contents('php://input');
$event   = json_decode($rawBody, true);

error_log("[CashFly Webhook] 📥 Payload recebido: {$rawBody}");

// ----------------------------------------------------------
// 2. Valida assinatura HMAC (se webhookSecret configurado)
// ----------------------------------------------------------
if (CASHFLY_WEBHOOK_SECRET !== '') {
    $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
    $expected  = hash_hmac('sha256', $rawBody, CASHFLY_WEBHOOK_SECRET);

    if (!hash_equals($expected, $signature)) {
        error_log("[CashFly Webhook] ❌ Assinatura inválida.");
        http_response_code(401);
        echo json_encode(['error' => 'Assinatura inválida']);
        exit;
    }
}

// ----------------------------------------------------------
// 3. Valida estrutura mínima do evento
//    Esperado: { id, type, event, objectId, data: { id, status, ... } }
// ----------------------------------------------------------
if (
    !$event ||
    !isset($event['id'], $event['type'], $event['event'], $event['data']['id'])
) {
    error_log("[CashFly Webhook] ⚠️ Payload inválido ou incompleto.");
    http_response_code(200); // sempre 200 para evitar reenvios infinitos
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

$eventName     = $event['event'];           // ex: "transaction.paid"
$data          = $event['data'];
$transactionId = $data['id'];
$rawStatus     = strtolower($data['status'] ?? '');

error_log("[CashFly Webhook] ℹ️ Evento: {$eventName} | ID: {$transactionId} | Status: {$rawStatus}");

// ----------------------------------------------------------
// 4. Mapeia status da API para status interno
// ----------------------------------------------------------
$STATUS_MAP = [
    'paid'               => 'paid',
    'failed'             => 'failed',
    'cancelled'          => 'cancelled',
    'expired'            => 'expired',
    'refunded'           => 'refunded',
    'partially_refunded' => 'partially_refunded',
    'pending'            => 'pending',
    'processing'         => 'processing',
];

$novoStatus = $STATUS_MAP[$rawStatus] ?? $rawStatus;

try {
    // ----------------------------------------------------------
    // 5. Atualiza SQLite
    // ----------------------------------------------------------
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("UPDATE pedidos
        SET status = :status, updated_at = :updated_at
        WHERE transaction_id = :tid");
    $stmt->execute([
        'status'     => $novoStatus,
        'updated_at' => date('c'),
        'tid'        => $transactionId,
    ]);

    if ($stmt->rowCount() === 0) {
        error_log("[CashFly Webhook] ⚠️ Pedido não encontrado no banco: {$transactionId}");
    } else {
        error_log("[CashFly Webhook] ✅ Status atualizado → {$novoStatus}");
    }

    // ----------------------------------------------------------
    // 6. Responde imediatamente ao CashFly (HTTP 200)
    // ----------------------------------------------------------
    http_response_code(200);
    echo json_encode(['success' => true]);

    // Fecha conexão para não atrasar o processamento em background
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // ----------------------------------------------------------
    // 7. Processamento em background (após responder)
    //    Executa apenas quando pagamento é confirmado
    // ----------------------------------------------------------
    if ($eventName === 'transaction.paid') {

        // Recupera dados completos do pedido
        $pedidoStmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :tid");
        $pedidoStmt->execute(['tid' => $transactionId]);
        $pedido = $pedidoStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            error_log("[CashFly Webhook] ❌ Pedido não encontrado para background: {$transactionId}");
            exit;
        }

        $utmParams = json_decode($pedido['utm_params'] ?? '{}', true) ?? [];
        $customer  = $data['customer'] ?? [];
        $pix       = $data['pix']      ?? [];
        $amount    = $data['amount']   ?? $pedido['valor'];
        $paidAt    = $data['paidAt']   ?? date('Y-m-d H:i:s');

        // Envia para utmify (aprovação)
        _enviaUtmify($transactionId, $customer, $pix, $amount, $paidAt, $utmParams, $pedido);
    }

} catch (Exception $e) {
    error_log("[CashFly Webhook] ❌ Exceção: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
}

// ============================================================
// Helpers
// ============================================================

function _enviaUtmify(
    string $orderId,
    array  $customer,
    array  $pix,
    int    $amount,
    string $paidAt,
    array  $utmParams,
    array  $pedido
): void {
    $utmifyData = [
        'orderId'       => $orderId,
        'platform'      => 'MinhaPlataforma',
        'paymentMethod' => 'pix',
        'status'        => 'paid',
        'createdAt'     => $pedido['created_at'],
        'approvedDate'  => $paidAt,
        'paidAt'        => $paidAt,
        'refundedAt'    => null,
        'customer'      => [
            'name'     => $customer['name']     ?? $pedido['nome'],
            'email'    => $customer['email']    ?? $pedido['email'],
            'phone'    => $customer['phone']    ?? null,
            'document' => $customer['document'] ?? $pedido['cpf'],
            'country'  => 'BR',
        ],
        'products' => [[
            'id'          => uniqid('PROD_'),
            'name'        => 'Produto',
            'quantity'    => 1,
            'priceInCents'=> $amount,
        ]],
        'trackingParameters' => array_filter([
            'utm_source'   => $utmParams['utm_source']   ?? null,
            'utm_medium'   => $utmParams['utm_medium']   ?? null,
            'utm_campaign' => $utmParams['utm_campaign'] ?? null,
            'utm_content'  => $utmParams['utm_content']  ?? null,
            'utm_term'     => $utmParams['utm_term']     ?? null,
            'sck'          => $utmParams['sck']          ?? null,
            'src'          => $utmParams['src']          ?? null,
            'xcod'         => $utmParams['xcod']         ?? null,
        ]),
        'commission' => [
            'totalPriceInCents'    => $amount,
            'gatewayFeeInCents'    => 0,
            'userCommissionInCents'=> $amount,
        ],
        'isTest' => false,
    ];

    $serverProto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $serverHost  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir   = str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '', __DIR__);
    $utmifyUrl   = "{$serverProto}://{$serverHost}{$scriptDir}/utmify.php";

    $ch = curl_init($utmifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($utmifyData),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("[CashFly Webhook] 📊 UTMify → HTTP {$code}: {$resp}");
}
