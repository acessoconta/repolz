<?php
/**
 * CONTROLADOR DE CONFIGURAÇÕES DO CHECKOUT
 */
session_start();

// Verificar se está logado (exceto para GET)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !isset($_SESSION['gateway_admin_logged'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$configFile = __DIR__ . '/checkout-config.json';

// Função para ler configurações
function readConfig($configFile) {
    if (!file_exists($configFile)) {
        return [
            'product_price' => '39.90',
            'product_name' => 'JBL PartyBox Stage 320BR',
            'product_description' => 'A Rainha das Festas Chegou',
            'product_image' => 'https://cloudfox-digital-products.s3.amazonaws.com/uploads/public/products/KjQmnHJELkxf0X29iK1tED5W4EiLm2pg6SVIVXb3.png',
            'company_name' => 'Compra Segura',
            'company_logo' => 'https://cloudfox-digital-products.s3.amazonaws.com/uploads/user/7YL9jZDV96gp4qm/public/stores/nQ7kZ7nLVD30eJL/logo/MtvVsDb1gO3z97UxLtcWCwJjT6PMBUY582wIGX7d.png',
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    $content = file_get_contents($configFile);
    return json_decode($content, true) ?: [];
}

// Função para salvar configurações
function saveConfig($configFile, $config) {
    $config['last_updated'] = date('Y-m-d H:i:s');
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Processar requisições
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Retornar configurações atuais
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_config') {
        $config = readConfig($configFile);
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_config') {
        $price = $_POST['price'] ?? '';
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $image = $_POST['image'] ?? '';
        $companyName = $_POST['company_name'] ?? '';
        $companyLogo = $_POST['company_logo'] ?? '';
        $isDigital = $_POST['is_digital'] ?? 'false';
        $depoimentosEnabled = $_POST['depoimentos_enabled'] ?? 'true';
        $showSafeBadge = $_POST['show_safe_badge'] ?? 'true';
        $safeBadgeImage = $_POST['safe_badge_image'] ?? '';
        $showCompanyLogo = $_POST['show_company_logo'] ?? 'true';
        $offersData = $_POST['offers'] ?? '';
        $freteData = $_POST['frete'] ?? '';
        $depoimentosData = $_POST['depoimentos'] ?? '';
        $colorsData = $_POST['colors'] ?? '';
        
        // Validar preço
        if (empty($price) || !is_numeric(str_replace(',', '.', $price))) {
            echo json_encode(['success' => false, 'message' => 'Preço inválido']);
            exit;
        }
        
        // Validar nome do produto
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nome do produto é obrigatório']);
            exit;
        }
        
        // Ler configuração atual
        $config = readConfig($configFile);
        
        // Atualizar valores
        $config['product_price'] = str_replace(',', '.', $price);
        $config['product_name'] = $name;
        
        if (!empty($description)) {
            $config['product_description'] = $description;
        }
        
        if (!empty($image)) {
            $config['product_image'] = $image;
        }
        
        if (!empty($companyName)) {
            $config['company_name'] = $companyName;
        }
        
        if (!empty($companyLogo)) {
            $config['company_logo'] = $companyLogo;
        }
        
        // Salvar configuração de produto digital
        $config['is_digital'] = ($isDigital === 'true' || $isDigital === true);
        
        // Salvar configuração de depoimentos
        $config['depoimentos_enabled'] = ($depoimentosEnabled === 'true' || $depoimentosEnabled === true);
        
        // Salvar configuração do badge seguro
        $config['show_safe_badge'] = ($showSafeBadge === 'true' || $showSafeBadge === true);
        
        // Salvar imagem do badge seguro se fornecida
        if (!empty($safeBadgeImage)) {
            $config['safe_badge_image'] = $safeBadgeImage;
        } else {
            $config['safe_badge_image'] = '';
        }
        
        // Salvar configuração da logo da empresa
        $config['show_company_logo'] = ($showCompanyLogo === 'true' || $showCompanyLogo === true);
        
        // Processar ofertas se fornecidas
        if (!empty($offersData)) {
            $offers = json_decode($offersData, true);
            if ($offers !== null) {
                $config['offers'] = $offers;
            }
        }
        
        // Processar frete se fornecido
        if (!empty($freteData)) {
            $frete = json_decode($freteData, true);
            if ($frete !== null) {
                $config['frete'] = $frete;
            }
        }
        
        // Processar depoimentos se fornecidos
        if (!empty($depoimentosData)) {
            $depoimentos = json_decode($depoimentosData, true);
            if ($depoimentos !== null) {
                $config['depoimentos'] = $depoimentos;
            }
        }
        
        // Processar cores se fornecidas
        if (!empty($colorsData)) {
            $colors = json_decode($colorsData, true);
            if ($colors !== null) {
                $config['colors'] = $colors;
            }
        }
        
        // Processar contador se fornecido
        if (!empty($_POST['contador'])) {
            $contador = json_decode($_POST['contador'], true);
            if ($contador !== null) {
                $config['contador'] = $contador;
            }
        }
        
        // Salvar configurações
        if (saveConfig($configFile, $config)) {
            echo json_encode([
                'success' => true,
                'message' => 'Configurações salvas com sucesso!',
                'config' => $config
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar configurações']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>