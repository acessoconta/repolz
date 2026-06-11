<?php
header('Content-Type: application/json');

// Habilita o log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Recebe o payload do webhook
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Log do payload recebido
error_log("[Webhook] 🔄 Iniciando processamento do webhook");
error_log("[Webhook] 📦 Payload recebido: " . $payload);

// Verifica se o payload é válido
if (!$event || !isset($event['objectId']) || !isset($event['data']['status'])) {
    error_log("[Webhook] ❌ Payload inválido recebido. Campos necessários não encontrados");
    error_log("[Webhook] 🔍 Campos disponíveis: " . print_r(array_keys($event ?? []), true));
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

try {
    error_log("[Webhook] ℹ️ Processando pagamento ID: " . $event['objectId'] . " com status: " . $event['data']['status']);
    
    // Conecta ao SQLite
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("[Webhook] ✅ Conexão com banco de dados estabelecida");

    // Atualiza o status do pagamento no banco de dados
    $stmt = $db->prepare("UPDATE pedidos SET status = :status, updated_at = :updated_at WHERE transaction_id = :transaction_id");
    
    $novoStatus = $event['data']['status'] === 'paid' ? 'paid' : $event['data']['status'];
    error_log("[Webhook] 🔄 Atualizando status para: " . $novoStatus);
    
    $result = $stmt->execute([
        'status' => $novoStatus,
        'updated_at' => date('c'),
        'transaction_id' => $event['objectId']
    ]);

    if ($stmt->rowCount() === 0) {
        error_log("[Webhook] ⚠️ Nenhum pedido encontrado com o ID: " . $event['objectId']);
        error_log("[Webhook] 🔍 Verificando se o pedido existe no banco...");
        
        // Verifica se o pedido existe
        $checkStmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $checkStmt->execute(['transaction_id' => $event['objectId']]);
        $pedidoExiste = $checkStmt->fetch();
        
        if ($pedidoExiste) {
            error_log("[Webhook] ℹ️ Pedido encontrado mas status não foi alterado. Status atual: " . $pedidoExiste['status']);
        } else {
            error_log("[Webhook] ❌ Pedido não existe no banco de dados");
        }
        
        http_response_code(404);
        echo json_encode(['error' => 'Pedido não encontrado']);
        exit;
    }

    error_log("[Webhook] ✅ Status atualizado com sucesso no banco de dados");

    // Responde imediatamente ao webhook
    http_response_code(200);
    echo json_encode(['success' => true]);
    
    // Fecha a conexão com o cliente
    if (function_exists('fastcgi_finish_request')) {
        error_log("[Webhook] 📤 Fechando conexão com o cliente via fastcgi_finish_request");
        fastcgi_finish_request();
    } else {
        error_log("[Webhook] ⚠️ fastcgi_finish_request não disponível");
    }
    
    // Continua o processamento em background
    if ($event['data']['status'] === 'paid') {
        error_log("[Webhook] ✅ Pagamento aprovado, iniciando processamento em background");

        // Busca os dados do pedido
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $event['objectId']]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pedido) {
            error_log("[Webhook] ✅ Dados do pedido recuperados do banco");
            error_log("[Webhook] 📊 Dados do pedido: " . print_r($pedido, true));

            // Decodifica os parâmetros UTM do banco
            $utmParams = json_decode($pedido['utm_params'], true);
            error_log("[Webhook] 📊 UTM Params brutos do banco: " . print_r($utmParams, true));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[Webhook] ⚠️ Erro ao decodificar UTM params: " . json_last_error_msg());
            }

            // Extrai os parâmetros UTM
            $trackingParameters = [
                'src' => $utmParams['utm_source'] ?? null,
                'sck' => $utmParams['sck'] ?? null,
                'utm_source' => $utmParams['utm_source'] ?? null,
                'utm_campaign' => $utmParams['utm_campaign'] ?? null,
                'utm_medium' => $utmParams['utm_medium'] ?? null,
                'utm_content' => $utmParams['utm_content'] ?? null,
                'utm_term' => $utmParams['utm_term'] ?? null,
                'fbclid' => $utmParams['fbclid'] ?? null,
                'gclid' => $utmParams['gclid'] ?? null,
                'ttclid' => $utmParams['ttclid'] ?? null,
                'xcod' => $utmParams['xcod'] ?? null
            ];

            // Remove valores null
            $trackingParameters = array_filter($trackingParameters);

            $utmifyData = [
                'orderId' => $event['objectId'],
                'platform' => 'Skalepay',
                'paymentMethod' => 'pix',
                'status' => 'paid',
                'createdAt' => $event['data']['createdAt'] ?? $pedido['created_at'],
                'approvedDate' => $event['data']['paidAt'] ?? date('Y-m-d H:i:s'),
                'paidAt' => $event['data']['paidAt'] ?? date('Y-m-d H:i:s'),
                'refundedAt' => null,
                'customer' => [
                    'name' => $event['data']['customer']['name'] ?? $pedido['nome'],
                    'email' => $event['data']['customer']['email'] ?? $pedido['email'],
                    'phone' => $event['data']['customer']['phone'] ?? null,
                    'document' => [
                        'number' => $event['data']['customer']['document']['number'] ?? $pedido['cpf'],
                        'type' => 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => $event['data']['items'][0]['id'] ?? uniqid('PROD_'),
                        'title' => $event['data']['items'][0]['title'] ?? 'Liberação de Benefício',
                        'quantity' => 1,
                        'unitPrice' => $event['data']['amount'] ?? $pedido['valor']
                    ]
                ],
                'amount' => $event['data']['amount'] ?? $pedido['valor'],
                'fee' => [
                    'fixedAmount' => $event['data']['fee']['fixedAmount'] ?? 0,
                    'netAmount' => $event['data']['fee']['netAmount'] ?? $pedido['valor']
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            error_log("[Webhook] 📦 Payload completo para utmify: " . json_encode($utmifyData));

            // Envia para utmify.php
            $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $utmifyUrl = $serverUrl . "/amazon/utmify.php";
            error_log("[Webhook] 🌐 Enviando dados para URL: " . $utmifyUrl);

            $ch = curl_init($utmifyUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($utmifyData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30
            ]);

            $utmifyResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            error_log("[Webhook] 📤 Resposta do utmify (HTTP $httpCode): " . $utmifyResponse);
            if ($curlError) {
                error_log("[Webhook] ❌ Erro ao enviar para utmify: " . $curlError);
            } else {
                error_log("[Webhook] 📊 Resposta decodificada: " . print_r(json_decode($utmifyResponse, true), true));
            }
            
            curl_close($ch);

            // ── xTracky — paid (server-side, confiável) ──────────────────────
            $xtrackyToken  = '';
            $xtrackyUrl    = 'https://api.xtracky.com/api/integrations/api';
            $xtrackyLogDir = __DIR__ . '/../logs';
            if (!is_dir($xtrackyLogDir)) @mkdir($xtrackyLogDir, 0755, true);
            $xtrackyLog = $xtrackyLogDir . '/xtracky-' . date('Y-m-d') . '.log';

            function xtrackyWriteLog($file, $etapa, $dados = []) {
                $linha = '[' . date('Y-m-d H:i:s') . '] [' . $etapa . ']';
                if (!empty($dados)) $linha .= ' ' . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                @file_put_contents($file, $linha . "\n", FILE_APPEND | LOCK_EX);
            }

            $amountCents   = (int)($event['data']['amount'] ?? $pedido['valor'] ?? 0);
            $utmSourceVal  = $utmParams['utm_source'] ?? '';

            $xtrackyPayload = [
                'orderId'    => (string)$event['objectId'],
                'amount'     => $amountCents,
                'status'     => 'paid',
                'utm_source' => $utmSourceVal,
                'token'      => $xtrackyToken,
            ];

            xtrackyWriteLog($xtrackyLog, 'PAYLOAD_ENVIADO', $xtrackyPayload);
            error_log("[xTracky] 📤 Enviando paid para xTracky: " . json_encode($xtrackyPayload));

            $chXt = curl_init($xtrackyUrl);
            curl_setopt_array($chXt, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($xtrackyPayload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $xtResponse  = curl_exec($chXt);
            $xtHttpCode  = curl_getinfo($chXt, CURLINFO_HTTP_CODE);
            $xtCurlErr   = curl_error($chXt);
            curl_close($chXt);

            xtrackyWriteLog($xtrackyLog, 'RESPOSTA', [
                'http_code' => $xtHttpCode,
                'response'  => $xtResponse,
                'curl_err'  => $xtCurlErr ?: null,
                'orderId'   => (string)$event['objectId'],
            ]);

            if ($xtHttpCode >= 200 && $xtHttpCode < 300) {
                error_log("[xTracky] ✅ paid enviado com sucesso (HTTP $xtHttpCode): $xtResponse");
            } else {
                error_log("[xTracky] ❌ Erro ao enviar paid (HTTP $xtHttpCode): $xtResponse | curl_err: $xtCurlErr");
            }
            // ── fim xTracky ───────────────────────────────────────────────────

            error_log("[Webhook] ✅ Processamento em background concluído");
        } else {
            error_log("[Webhook] ❌ Não foi possível recuperar os dados do pedido do banco");
        }
    } else {
        error_log("[Webhook] ℹ️ Status não é APPROVED, pulando processamento em background");
    }

} catch (Exception $e) {
    error_log("[Webhook] ❌ Erro: " . $e->getMessage());
    error_log("[Webhook] 🔍 Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
} 