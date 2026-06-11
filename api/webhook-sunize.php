<?php
header('Content-Type: application/json');

// Habilita o log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Define timezone para horário de Brasília
date_default_timezone_set('America/Sao_Paulo');

// Recebe o payload do webhook
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Log do payload recebido
error_log("[Webhook] 🔄 Iniciando processamento do webhook");
error_log("[Webhook] 📦 Payload recebido: " . $payload);

// Verifica se o payload é válido - Formato Sunize
if (!$event || !isset($event['id']) || !isset($event['status'])) {
    error_log("[Webhook] ❌ Payload inválido recebido. Campos necessários não encontrados");
    error_log("[Webhook] 🔍 Campos disponíveis: " . print_r(array_keys($event ?? []), true));
    http_response_code(200);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

function getUpsellTitle($valor) {
    // Mapeamento de valores para nomes de upsell
    switch($valor) {
        case 4790:
            return 'Taxa de verificação';
        case 2890:
            return 'Taxa TENF';
        case 4569:
            return 'Taxa IOF';
        case 8500:
            return 'Taxa de Regularização';
        case 1825:
            return 'Validação Bancaria';
        case 3990:
            return 'Taxa de Validação';
        case 5573:
            return 'Front'; // Valor original do checkout
        case 2490:
            return 'Indenização Adicional'; // Valor padrão do checkoutup
        default:
            return 'Produto ' . ($valor/100); // Para outros valores não mapeados
    }
}

try {
    // Extrair os dados relevantes do formato Sunize
    $transactionId = $event['id'];
    $externalId = $event['external_id'] ?? null;
    $status = $event['status'];
    $totalAmount = $event['total_amount'] ?? 0;
    $paymentMethod = $event['payment_method'] ?? 'PIX';
    
    error_log("[Webhook] ℹ️ Processando pagamento ID: " . $transactionId . " (External ID: " . $externalId . ") com status: " . $status);
    
    // Conecta ao SQLite
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("[Webhook] ✅ Conexão com banco de dados estabelecida");

    // Atualiza o status do pagamento no banco de dados
    $stmt = $db->prepare("UPDATE pedidos SET status = :status, updated_at = :updated_at WHERE transaction_id = :transaction_id");
    
    // Mapeia os status da Sunize
    $statusMap = [
        'AUTHORIZED' => 'paid',
        'PENDING' => 'pending',
        'CHARGEBACK' => 'refunded',
        'FAILED' => 'failed',
        'IN_DISPUTE' => 'in_dispute'
    ];
    
    $novoStatus = $statusMap[strtoupper($status)] ?? strtolower($status);
    error_log("[Webhook] 🔄 Atualizando status para: " . $novoStatus);
    
    $result = $stmt->execute([
        'status' => $novoStatus,
        'updated_at' => date('c'),
        'transaction_id' => $transactionId
    ]);

    if ($stmt->rowCount() === 0) {
        error_log("[Webhook] ⚠️ Nenhum pedido encontrado com o ID: " . $transactionId);
        error_log("[Webhook] 🔍 Verificando se o pedido existe no banco...");
        
        // Verifica se o pedido existe
        $checkStmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $checkStmt->execute(['transaction_id' => $transactionId]);
        $pedidoExiste = $checkStmt->fetch();
        
        if ($pedidoExiste) {
            error_log("[Webhook] ℹ️ Pedido encontrado mas status não foi alterado. Status atual: " . $pedidoExiste['status']);
        } else {
            error_log("[Webhook] ❌ Pedido não existe no banco de dados");
        }
        
        http_response_code(200);
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
    if (strtoupper($status) === 'AUTHORIZED') {
        error_log("[Webhook] ✅ Pagamento autorizado, iniciando processamento em background");

        // Busca os dados do pedido
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE transaction_id = :transaction_id");
        $stmt->execute(['transaction_id' => $transactionId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pedido) {
            error_log("[Webhook] ✅ Dados do pedido recuperados do banco");
            error_log("[Webhook] 📊 Dados do pedido: " . print_r($pedido, true));

            // Decodifica os parâmetros UTM do banco
            $utmParamsFromDb = json_decode($pedido['utm_params'], true);
            error_log("[Webhook] 📊 UTM Params do banco: " . print_r($utmParamsFromDb, true));
            
            // Extrai os parâmetros UTM
            $trackingParameters = [
                'src' => $utmParamsFromDb['src'] ?? null,
                'sck' => $utmParamsFromDb['sck'] ?? null,
                'utm_source' => $utmParamsFromDb['utm_source'] ?? null,
                'utm_campaign' => $utmParamsFromDb['utm_campaign'] ?? null,
                'utm_medium' => $utmParamsFromDb['utm_medium'] ?? null,
                'utm_content' => $utmParamsFromDb['utm_content'] ?? null,
                'utm_term' => $utmParamsFromDb['utm_term'] ?? null,
                'fbclid' => $utmParamsFromDb['fbclid'] ?? null,
                'gclid' => $utmParamsFromDb['gclid'] ?? null,
                'ttclid' => $utmParamsFromDb['ttclid'] ?? null,
                'xcod' => $utmParamsFromDb['xcod'] ?? null
            ];

            // Remove valores null
            $trackingParameters = array_filter($trackingParameters);

            // Usa dados do banco
            $customerName = $pedido['nome'];
            $customerEmail = $pedido['email'];
            $customerDocument = $pedido['cpf'];
            
            // Converte total_amount de reais para centavos
            $finalAmount = (int)($totalAmount * 100);
            if ($finalAmount <= 0) {
                $finalAmount = $pedido['valor'];
            }
            
            error_log("[Webhook] 📊 Dados finais - Customer: " . $customerName);
            error_log("[Webhook] 📊 Dados finais - Document: " . $customerDocument);
            error_log("[Webhook] 📊 Dados finais - Amount: " . $finalAmount . " centavos");

            // Estrutura do payload para o utmify
            $utmifyData = [
                'orderId' => $transactionId,
                'platform' => 'Sunize',
                'paymentMethod' => strtolower($paymentMethod),
                'status' => 'paid',
                'createdAt' => $pedido['created_at'],
                'approvedDate' => date('Y-m-d H:i:s'),
                'paidAt' => date('Y-m-d H:i:s'),
                'refundedAt' => null,
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => null,
                    'document' => [
                        'number' => $customerDocument,
                        'type' => 'CPF'
                    ],
                    'country' => 'BR',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                ],
                'items' => [
                    [
                        'id' => uniqid('PROD_'),
                        'title' => getUpsellTitle($finalAmount),
                        'quantity' => 1,
                        'unitPrice' => $finalAmount
                    ]
                ],
                'amount' => $finalAmount,
                'fee' => [
                    'fixedAmount' => 0,
                    'netAmount' => $finalAmount
                ],
                'trackingParameters' => $trackingParameters,
                'isTest' => false
            ];

            error_log("[Webhook] 📦 Payload completo para utmify: " . json_encode($utmifyData));

            // Envia para utmify.php
             // Construir URL do utmify.php de forma robusta com fallbacks
            $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            
            // Método 1: Usar DOCUMENT_ROOT (mais comum)
            $scriptDir = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
            $utmifyUrl = $serverUrl . $scriptDir . "/utmify.php";
            
            // Método 2: Fallback usando SCRIPT_NAME se o método 1 falhar
            if (empty($scriptDir) || $scriptDir === __DIR__) {
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                $utmifyUrl = $serverUrl . $scriptDir . "/utmify.php";
                error_log("[Webhook] ⚠️ Usando fallback SCRIPT_NAME para construir URL");
            }

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

            $amountCents   = (int)($finalAmount ?? $pedido['valor'] ?? 0);
            $utmSourceVal  = $utmParamsFromDb['utm_source'] ?? '';

            $xtrackyPayload = [
                'orderId'    => (string)$transactionId,
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
                'orderId'   => (string)$transactionId,
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
        error_log("[Webhook] ℹ️ Status não é AUTHORIZED, pulando processamento em background. Status recebido: " . $status);
    }

} catch (Exception $e) {
    error_log("[Webhook] ❌ Erro: " . $e->getMessage());
    error_log("[Webhook] 🔍 Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}