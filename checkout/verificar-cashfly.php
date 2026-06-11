<?php
/**
 * CASHFLY - Verificar status da cobrança
 *
 * Uso:
 *   GET verificar-cashfly.php?id=UUID          → consulta SQLite local
 *   GET verificar-cashfly.php?id=UUID&api=1    → consulta diretamente a API CashFly
 *
 * API: GET https://api.gw.cashflypay.com/api/v1/transactions/pix-in?id={UUID}
 * Documentação: https://painel.cashflypay.com/docs
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ============================================================
// CONFIGURAÇÕES
// ============================================================
define('CASHFLY_API_KEY',    'SUA_API_KEY_AQUI');
define('CASHFLY_PUBLIC_KEY', 'SUA_PUBLIC_KEY_AQUI');
define('CASHFLY_BASE_URL',   'https://api.gw.cashflypay.com');
define('CASHFLY_USER_AGENT', 'MeuCheckout/1.0 (+suporte@seusite.com.br)');
// ============================================================

// ----------------------------------------------------------
// 1. Valida parâmetro obrigatório
// ----------------------------------------------------------
$id = trim($_GET['id'] ?? '');
$id = preg_replace('/[^a-zA-Z0-9\-]/', '', $id);

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetro id é obrigatório.']);
    exit;
}

$consultaApi = isset($_GET['api']) && $_GET['api'] === '1';

try {
    // ----------------------------------------------------------
    // 2. Consulta SQLite local (sempre)
    // ----------------------------------------------------------
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :tid");
    $stmt->execute(['tid' => $id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    // ----------------------------------------------------------
    // 3. Consulta API CashFly (opcional, ?api=1)
    // ----------------------------------------------------------
    $apiData = null;
    if ($consultaApi) {
        $auth = base64_encode(CASHFLY_API_KEY . ':' . CASHFLY_PUBLIC_KEY);
        $url  = CASHFLY_BASE_URL . '/api/v1/transactions/pix-in?id=' . urlencode($id);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $auth,
                'User-Agent: ' . CASHFLY_USER_AGENT,
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $apiData = json_decode($response, true);

            // Sincroniza status local com o retorno da API
            if ($apiData && isset($apiData['status']) && $pedido) {
                $novoStatus = strtolower($apiData['status']);
                $db->prepare("UPDATE pedidos SET status = :status, updated_at = :updated_at WHERE transaction_id = :tid")
                   ->execute([
                       'status'     => $novoStatus,
                       'updated_at' => date('c'),
                       'tid'        => $id,
                   ]);
                $pedido['status'] = $novoStatus;
            }
        } else {
            error_log("[CashFly Verificar] ⚠️ API retornou HTTP {$httpCode} para id={$id}");
        }
    }

    // ----------------------------------------------------------
    // 4. Resposta
    // ----------------------------------------------------------
    if (!$pedido && !$apiData) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'status'  => 'not_found',
            'message' => 'Transação não encontrada.',
        ]);
        exit;
    }

    // Monta resposta unificada
    $statusFinal = $pedido['status'] ?? strtolower($apiData['status'] ?? 'unknown');

    $resposta = [
        'success'        => true,
        'status'         => $statusFinal,
        'transaction_id' => $id,
        'paid'           => $statusFinal === 'paid',
        'data'           => [
            'amount'     => $pedido['valor']      ?? $apiData['amount']    ?? null,
            'created_at' => $pedido['created_at'] ?? $apiData['createdAt'] ?? null,
            'updated_at' => $pedido['updated_at'] ?? $apiData['updatedAt'] ?? null,
            'customer'   => [
                'name'     => $pedido['nome']  ?? $apiData['customer']['name']  ?? null,
                'email'    => $pedido['email'] ?? $apiData['customer']['email'] ?? null,
                'document' => $pedido['cpf']   ?? $apiData['customer']['documentNumber'] ?? null,
            ],
        ],
    ];

    // Inclui dados PIX da API se disponíveis
    if ($apiData && isset($apiData['pix'])) {
        $resposta['data']['pix'] = [
            'qrcode'     => $apiData['pix']['qrcode']     ?? null,
            'expiresAt'  => $apiData['pix']['expiresAt']  ?? null,
            'endToEndId' => $apiData['pix']['endToEndId'] ?? null,
        ];
    }

    echo json_encode($resposta);

} catch (Exception $e) {
    error_log("[CashFly Verificar] ❌ Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => 'Erro ao verificar o pagamento.',
    ]);
}
