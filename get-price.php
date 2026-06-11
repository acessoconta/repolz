<?php
/**
 * ENDPOINT PÚBLICO - OBTER PREÇO ATUAL
 * Retorna o preço configurado para o checkout
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$configFile = __DIR__ . '/checkout-config.json';

// Configuração padrão
$defaultPrice = '21.15';
$defaultName = 'Taxa de Cadastro - Essa taxa será reembolsada 100% após o saque';

// Lê preço do arquivo
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if ($config && isset($config['product_price'])) {
        echo json_encode([
            'success' => true,
            'price' => $config['product_price'],
            'name' => $config['product_name'] ?? $defaultName,
            'formatted_price' => 'R$ ' . number_format((float)$config['product_price'], 2, ',', '.')
        ]);
        exit;
    }
}

// Retorna preço padrão se não encontrar configuração
echo json_encode([
    'success' => true,
    'price' => $defaultPrice,
    'name' => $defaultName,
    'formatted_price' => 'R$ ' . number_format((float)$defaultPrice, 2, ',', '.')
]);
