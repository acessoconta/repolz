<?php
/**
 * ROTEADOR DE PAGAMENTO
 * 
 * Este arquivo redireciona automaticamente para o gateway configurado
 * em payment-config.php
 * 
 * NÃO EDITE ESTE ARQUIVO!
 * Para trocar de gateway, edite o arquivo payment-config.php
 */

// Carrega configuração do gateway
require_once __DIR__ . '/payment-config.php';

error_log("[Router] 🔄 Redirecionando para gateway: " . getActiveGateway());
error_log("[Router] 📄 Arquivo de destino: " . $PAYMENT_FILE);

// Verifica se o arquivo do gateway existe
if (!file_exists(__DIR__ . '/' . $PAYMENT_FILE)) {
    error_log("[Router] ❌ ERRO: Arquivo do gateway não encontrado: " . $PAYMENT_FILE);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gateway de pagamento não encontrado. Contate o suporte.'
    ]);
    exit;
}

// Redireciona todos os dados (POST, GET, FILES, etc.) para o gateway configurado
require __DIR__ . '/' . $PAYMENT_FILE;
