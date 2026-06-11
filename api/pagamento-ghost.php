<?php



// Habilita o log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Função para gerar CPF válido
function gerarCPF() {
    $cpf = '';
    for ($i = 0; $i < 9; $i++) {
        $cpf .= rand(0, 9);
    }

    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $digito1;

    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $digito2;

    $invalidos = [
        '00000000000', '11111111111', '22222222222', '33333333333', 
        '44444444444', '55555555555', '66666666666', '77777777777', 
        '88888888888', '99999999999'
    ];

    if (in_array($cpf, $invalidos)) {
        return gerarCPF();
    }

    return $cpf;
}

try {

    $input = file_get_contents('php://input');
    $jsonData = json_decode($input, true);
    
    // Se não conseguiu decodificar JSON, tenta usar $_POST como fallback
    if (!$jsonData) {
        $jsonData = $_POST;
    }
    
    error_log("[Pagamento] 📦 Dados recebidos: " . json_encode($jsonData));

    // Conecta ao SQLite (arquivo de banco de dados)
    $dbPath = __DIR__ . '/database.sqlite'; // Caminho para o arquivo SQLite
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verifica se a tabela 'pedidos' existe e cria se necessário
    $db->exec("CREATE TABLE IF NOT EXISTS pedidos (
        transaction_id TEXT PRIMARY KEY,
        status TEXT NOT NULL,
        valor INTEGER NOT NULL,
        nome TEXT,
        email TEXT,
        cpf TEXT,
        telefone TEXT,
        utm_params TEXT,
        created_at TEXT,
        updated_at TEXT
    )");

   
    $valor = isset($jsonData['valor']) ? intval($jsonData['valor']) : 6190; // Valor dinâmico ou fallback

    $valor_centavos = $valor;
    if (!$valor || $valor <= 0) {
        throw new Exception('Valor inválido');
    }
    
    error_log("[Pagamento] 💵 Valor final a ser cobrado: R$ " . number_format($valor / 100, 2, ',', '.'));

    // Gera dados do cliente
    $nomes_masculinos = [
        'João', 'Pedro', 'Lucas', 'Miguel', 'Arthur', 'Gabriel', 'Bernardo', 'Rafael',
        'Gustavo', 'Felipe', 'Daniel', 'Matheus', 'Bruno', 'Thiago', 'Carlos'
    ];

    $nomes_femininos = [
        'Maria', 'Ana', 'Julia', 'Sofia', 'Isabella', 'Helena', 'Valentina', 'Laura',
        'Alice', 'Manuela', 'Beatriz', 'Clara', 'Luiza', 'Mariana', 'Sophia'
    ];

    $sobrenomes = [
        'Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 
        'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Carvalho', 
        'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira', 'Barbosa'
    ];

    // Parâmetros UTM
    $utmParams = [
        'utm_source' => $jsonData['utm_source'] ?? null,
        'utm_medium' => $jsonData['utm_medium'] ?? null,
        'utm_campaign' => $jsonData['utm_campaign'] ?? null,
        'utm_content' => $jsonData['utm_content'] ?? null,
        'utm_term' => $jsonData['utm_term'] ?? null,
        'xcod' => $jsonData['xcod'] ?? null,
        'sck' => $jsonData['sck'] ?? null,
        'src' => $jsonData['src'] ?? null
    ];

    $utmParams = array_filter($utmParams, function($value) {
        return $value !== null && $value !== '';
    });

    error_log("[Pagamento] 📊 Parâmetros UTM recebidos: " . json_encode($utmParams));

    $utmQuery = http_build_query($utmParams);

    // Usa os dados enviados pelo formulário, se disponíveis, senão gera dados falsos como fallback
    // Verifica pelos nomes corretos dos campos enviados pelo JavaScript
    if (isset($jsonData['name']) && !empty($jsonData['name'])) {
        $nome_cliente = $jsonData['name'];
        error_log("[Pagamento] 📝 Usando nome do cliente enviado via FormData: " . $nome_cliente);
    } elseif (isset($jsonData['nome']) && !empty($jsonData['nome'])) {
        $nome_cliente = $jsonData['nome'];
        error_log("[Pagamento] 📝 Usando nome do cliente enviado via JSON: " . $nome_cliente);
    } else {
        // Gera dados falsos como fallback
        $genero = rand(0, 1);
        $nome = $genero ? 
            $nomes_masculinos[array_rand($nomes_masculinos)] : 
            $nomes_femininos[array_rand($nomes_femininos)];
        
        $sobrenome1 = $sobrenomes[array_rand($sobrenomes)];
        $sobrenome2 = $sobrenomes[array_rand($sobrenomes)];
        
        $nome_cliente = "$nome $sobrenome1 $sobrenome2";
        error_log("[Pagamento] 📝 Usando nome falso gerado como fallback: " . $nome_cliente);
    }

    if (isset($jsonData['email']) && !empty($jsonData['email'])) {
        $email = $jsonData['email'];
        error_log("[Pagamento] 📝 Usando email do cliente enviado: " . $email);
    } else {
        $email = "clienteteste@gmail.com";
        error_log("[Pagamento] 📝 Usando email falso como fallback: " . $email);
    }

    if (isset($jsonData['document']) && !empty($jsonData['document'])) {
        $cpf = preg_replace('/[^0-9]/', '', $jsonData['document']);
        // Valida se o CPF tem pelo menos 11 dígitos após a limpeza
        if (strlen($cpf) < 11) {
            error_log("[Pagamento] ⚠️ CPF inválido ou vazio após limpeza: '" . ($jsonData['document'] ?? '') . "', gerando CPF falso");
            $cpf = gerarCPF();
        } else {
            error_log("[Pagamento] 📝 Usando CPF do cliente enviado via FormData: " . $cpf);
        }
    } elseif (isset($jsonData['cpf']) && !empty($jsonData['cpf'])) {
        $cpf = preg_replace('/[^0-9]/', '', $jsonData['cpf']);
        if (strlen($cpf) < 11) {
            error_log("[Pagamento] ⚠️ CPF inválido ou vazio após limpeza: '" . ($jsonData['cpf'] ?? '') . "', gerando CPF falso");
            $cpf = gerarCPF();
        } else {
            error_log("[Pagamento] 📝 Usando CPF do cliente enviado via JSON: " . $cpf);
        }
    } else {
        $cpf = gerarCPF();
        error_log("[Pagamento] 📝 Usando CPF falso gerado como fallback: " . $cpf);
    }

    if (isset($jsonData['telephone']) && !empty($jsonData['telephone'])) {
        $telefone = preg_replace('/[^0-9]/', '', $jsonData['telephone']);
        // Remove o prefixo 55 se estiver no início e o número tiver mais de 11 dígitos
        if (strlen($telefone) > 11 && substr($telefone, 0, 2) === '55') {
            $telefone = substr($telefone, 2);
        }
        // Valida se o telefone tem pelo menos 10 dígitos
        if (strlen($telefone) < 10) {
            error_log("[Pagamento] ⚠️ Telefone inválido ou vazio após limpeza: '" . ($jsonData['telephone'] ?? '') . "', usando telefone falso");
            $telefone = "11999999999";
        } else {
            error_log("[Pagamento] 📝 Usando telefone do cliente enviado via FormData: " . $telefone);
        }
    } elseif (isset($jsonData['telefone']) && !empty($jsonData['telefone'])) {
        $telefone = preg_replace('/[^0-9]/', '', $jsonData['telefone']);
        // Remove o prefixo 55 se estiver no início e o número tiver mais de 11 dígitos
        if (strlen($telefone) > 11 && substr($telefone, 0, 2) === '55') {
            $telefone = substr($telefone, 2);
        }
        if (strlen($telefone) < 10) {
            error_log("[Pagamento] ⚠️ Telefone inválido ou vazio após limpeza: '" . ($jsonData['telefone'] ?? '') . "', usando telefone falso");
            $telefone = "11999999999";
        } else {
            error_log("[Pagamento] 📝 Usando telefone do cliente enviado via JSON: " . $telefone);
        }
    } else {
        $telefone = "11999999999";
        error_log("[Pagamento] 📝 Usando telefone falso como fallback: " . $telefone);
    }

    // Configurações da API GhostsPay v1
$apiUrl = 'https://api.ghostspaysv1.com/api/generate-transaction';
$secretKey = 'sk_auto_O8FfH03dfEDhdLToDTHOHoNL82HXwyUE';
$publicKey = 'pk_auto_WxojFS9LP7quGa11Y6m0oD5ryScaG2Np'; 

    error_log("[GhostsPay] 📝 Preparando dados para envio: " . json_encode([
        'valor' => $valor,
        'valor_centavos' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf
    ]));

    // Cria o payload para a API GhostsPay v1
    $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $postbackUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['REQUEST_URI']) . '/webhook-ghost.php';

    // Gera um product_id único (UUID v4)
    $productId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $data = [
        "client_name" => $nome_cliente,
        "client_email" => $email,
        "client_document" => $cpf,
        "client_mobile_phone" => $telefone,
        "post_back_url" => $postbackUrl,
        "products" => [
            [
                "product_id" => $productId,
                "quantity" => 1,
                "value" => round($valor_centavos / 100, 2)  // API espera valor em reais (float), não centavos
            ]
        ]
    ];

    error_log("[GhostsPay] 🌐 URL da requisição: " . $apiUrl);
    error_log("[GhostsPay] 📦 Dados enviados: " . json_encode($data));
    error_log("[GhostsPay] 💰 Valor enviado: R$ " . number_format($valor_centavos / 100, 2, ',', '.') . " (convertido de " . $valor_centavos . " centavos)");

    // Prepara a autenticação com headers X-Secret-Key e X-Public-Key
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Secret-Key: ' . $secretKey,
        'X-Public-Key: ' . $publicKey,
        'Content-Type: application/json'
    ]);

    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    error_log("[GhostsPay] 🔍 Detalhes da requisição cURL:\n" . $verboseLog);

    if ($curlError) {
        error_log("[GhostsPay] ❌ Erro cURL: " . $curlError . " (errno: " . $curlErrno . ")");
        throw new Exception("Erro na requisição: " . $curlError);
    }

    curl_close($ch);

    error_log("[GhostsPay] 📊 HTTP Status Code: " . $httpCode);
    error_log("[GhostsPay] 📄 Resposta bruta: " . $response);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Erro na API: HTTP " . $httpCode . " - " . $response);
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar resposta: " . json_last_error_msg() . " - Resposta: " . $response);
    }

    // Verifica se há erro explícito na resposta
    if (isset($result['error']) || isset($result['errors'])) {
        $errorMessage = '';
        
        if (isset($result['message'])) {
            $errorMessage = $result['message'];
        }
        if (isset($result['error'])) {
            $errorMessage .= ($errorMessage ? ' | ' : '') . $result['error'];
        }
        if (isset($result['errors']) && is_array($result['errors'])) {
            $errorDetails = [];
            foreach ($result['errors'] as $field => $messages) {
                if (is_array($messages)) {
                    $errorDetails[] = "$field: " . implode(', ', $messages);
                } else {
                    $errorDetails[] = "$field: $messages";
                }
            }
            $errorMessage .= ($errorMessage ? ' | Detalhes: ' : 'Detalhes: ') . implode('; ', $errorDetails);
        }
        
        error_log("[GhostsPay] ❌ Erro retornado pela API: " . $errorMessage);
        throw new Exception("API retornou erro: " . $errorMessage);
    }

    // Verifica se a API retornou sucesso=false explicitamente
    if (isset($result['success']) && $result['success'] === false) {
        $errorMessage = $result['message'] ?? 'Erro desconhecido';
        error_log("[GhostsPay] ❌ API retornou success=false: " . $errorMessage);
        throw new Exception("API retornou erro: " . $errorMessage);
    }


    $transactionId = $result['data']['id'] ?? $result['data']['transaction_id'] ?? null;
    $pixCode = $result['data']['pix']['qrCode'] ?? $result['data']['pix']['code'] ?? null;
    
    if (!$transactionId) {
        $transactionId = $result['transaction_id'] ?? $result['id'] ?? null;
    }
    if (!$pixCode) {
        $pixCode = $result['pix']['qrCode'] ?? $result['pix']['code'] ?? null;
    }
    
    error_log("[GhostsPay] 🔍 IDs extraídos - transaction_id: " . ($transactionId ?? 'null') . ", pixCode: " . ($pixCode ? 'presente' : 'null'));

    error_log("[GhostsPay] 🔍 Estrutura da resposta: " . print_r($result, true));

    if (!$transactionId) {
        throw new Exception("ID da transação não encontrado na resposta da API");
    }

    if (!$pixCode) {
        error_log("[GhostsPay] ⚠️ Código PIX não encontrado na resposta");
    }

    $stmt = $db->prepare("INSERT INTO pedidos (transaction_id, status, valor, nome, email, cpf, telefone, utm_params, created_at) 
        VALUES (:transaction_id, 'pending', :valor, :nome, :email, :cpf, :telefone, :utm_params, :created_at)");
    $stmt->execute([
        'transaction_id' => $transactionId,
        'valor' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf,
        'telefone' => $telefone,
        'utm_params' => json_encode($utmParams),
        'created_at' => date('c')
    ]);

    session_start();
    $_SESSION['payment_id'] = $transactionId;

    error_log("[GhostsPay] 💳 Transação criada com sucesso: " . $transactionId);
    error_log("[GhostsPay] 📄 Resposta completa da API: " . $response);
    error_log("[GhostsPay] 🔑 Token gerado: " . $transactionId);

    error_log("[Sistema] 📡 Iniciando comunicação com otimizey-pendente.php");

   
    $otimizeyData = [
        'externalUserRef' => $email, // Referência do usuário (E-mail ou CPF)
        'product' => [
            'id' => 'produto-checkout', // ID do produto
            'name' => 'emagreca em 21 dias', // Nome do produto
            'price' => floatval($valor_centavos / 100) // Preço do produto
        ],
        'orderId' => $transactionId,
        'paymentMethod' => 'pix',
        'status' => 'waiting_payment',
        'totalPrice' => floatval($valor_centavos / 100), // Valor total como float
        'receivedPrice' => floatval($valor_centavos / 100), // Preço recebido como float
        'name' => $nome_cliente, // Nome completo do cliente
        'phone' => $telefone // Telefone do cliente
    ];

    // Adiciona parâmetros UTM apenas se existirem
    if (isset($utmParams['sck']) && !empty($utmParams['sck'])) {
        $otimizeyData['sck'] = $utmParams['sck'];
    }
    if (isset($utmParams['src']) && !empty($utmParams['src'])) {
        $otimizeyData['src'] = $utmParams['src'];
    }
    if (isset($utmParams['utm_source']) && !empty($utmParams['utm_source'])) {
        $otimizeyData['utmSource'] = $utmParams['utm_source'];
    }
    if (isset($utmParams['utm_medium']) && !empty($utmParams['utm_medium'])) {
        $otimizeyData['utmMedium'] = $utmParams['utm_medium'];
    }
    if (isset($utmParams['utm_campaign']) && !empty($utmParams['utm_campaign'])) {
        $otimizeyData['utmCampaign'] = $utmParams['utm_campaign'];
    }
    if (isset($utmParams['utm_content']) && !empty($utmParams['utm_content'])) {
        $otimizeyData['utmContent'] = $utmParams['utm_content'];
    }

    error_log("[Otimizey] 📦 Preparando dados para envio ao otimizey-pendente.php: " . json_encode($otimizeyData));

    $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $currentDir = dirname($_SERVER['REQUEST_URI']);
    $otimizeyUrl = $serverUrl . $currentDir . "/otimizey-pendente.php";
    error_log("[Sistema] 🌐 URL Otimizey pendente construída dinamicamente: " . $otimizeyUrl);
    
    $ch = curl_init($otimizeyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($otimizeyData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $otimizeyResponse = curl_exec($ch);
    $otimizeyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $otimizeyError = curl_error($ch);
    $otimizeyErrno = curl_errno($ch);
    
    error_log("[Sistema] 🔍 Detalhes da requisição Otimizey: " . print_r([
        'url' => $otimizeyUrl,
        'status' => $otimizeyHttpCode,
        'resposta' => $otimizeyResponse,
        'erro' => $otimizeyError,
        'errno' => $otimizeyErrno
    ], true));
    
    curl_close($ch);

    error_log("[Sistema] ✉️ Resposta do otimizey-pendente.php: " . $otimizeyResponse);
    error_log("[Sistema] 📊 Status code do otimizey-pendente.php: " . $otimizeyHttpCode);

    $otimizeyResponseDecoded = json_decode($otimizeyResponse, true);

    if ($otimizeyHttpCode !== 200) {
        error_log("[Sistema] ❌ Erro ao enviar dados para otimizey-pendente.php: " . $otimizeyResponse);
        if ($otimizeyResponseDecoded) {
            error_log("[Sistema] 📋 Detalhes do erro Otimizey: " . json_encode($otimizeyResponseDecoded, JSON_PRETTY_PRINT));
        }
    } else {
        error_log("[Sistema] ✅ Dados enviados com sucesso para otimizey-pendente.php");
        if ($otimizeyResponseDecoded) {
            error_log("[Sistema] 📋 Resposta Otimizey: " . json_encode($otimizeyResponseDecoded, JSON_PRETTY_PRINT));
        }
    }

    // ===== ENVIO PARA UTMIFY =====
    error_log("[Sistema] 📡 Iniciando comunicação com utmify-pendente.php");

    $utmifyData = [
        'orderId' => $transactionId,
        'platform' => 'GhostsPay',
        'paymentMethod' => 'pix',
        'status' => 'waiting_payment',
        'createdAt' => date('Y-m-d H:i:s'),
        'approvedDate' => null,
        'refundedAt' => null,
        'customer' => [
            'name' => $nome_cliente,
            'email' => $email,
            'phone' => $telefone,
            'document' => $cpf,
            'country' => 'BR',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ],
        'products' => [
            [
                'id' => $productId,
                'name' => 'Produto Checkout',
                'planId' => null,
                'planName' => null,
                'quantity' => 1,
                'priceInCents' => $valor_centavos
            ]
        ],
        'trackingParameters' => $utmParams,
        'commission' => [
            'totalPriceInCents' => $valor_centavos,
            'gatewayFeeInCents' => 0,
            'userCommissionInCents' => $valor_centavos
        ],
        'isTest' => false
    ];

    error_log("[Utmify] 📦 Preparando dados para envio ao utmify-pendente.php: " . json_encode($utmifyData));

    // Constrói URL do utmify-pendente.php de forma mais robusta
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $utmifyUrl = $protocol . "://" . $host . $scriptPath . "/utmify-pendente.php";
    
    error_log("[Utmify] 🌐 URL construída: " . $utmifyUrl);

    // Prepara o payload JSON
    $utmifyPayload = json_encode($utmifyData);
    error_log("[Utmify] 📋 Payload JSON: " . $utmifyPayload);
    
    $ch = curl_init($utmifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $utmifyPayload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    error_log("[Utmify] 🚀 Iniciando requisição para: " . $utmifyUrl);
    
    $utmifyResponse = curl_exec($ch);
    $utmifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $utmifyError = curl_error($ch);
    $utmifyErrno = curl_errno($ch);
    
    $curlInfo = curl_getinfo($ch);
    
    error_log("[Utmify] 🔍 Detalhes completos da requisição: " . json_encode([
        'url' => $utmifyUrl,
        'http_code' => $utmifyHttpCode,
        'total_time' => $curlInfo['total_time'],
        'connect_time' => $curlInfo['connect_time'],
        'size_download' => $curlInfo['size_download'],
        'resposta' => $utmifyResponse,
        'erro' => $utmifyError,
        'errno' => $utmifyErrno
    ], JSON_PRETTY_PRINT));
    
    curl_close($ch);

    error_log("[Utmify] ✉️ Resposta do utmify-pendente.php: " . $utmifyResponse);
    error_log("[Utmify] 📊 Status HTTP: " . $utmifyHttpCode);

    $utmifyResponseDecoded = json_decode($utmifyResponse, true);

    if ($utmifyErrno !== 0) {
        error_log("[Utmify] ❌ Erro cURL ao enviar para utmify-pendente.php: " . $utmifyError . " (errno: " . $utmifyErrno . ")");
    } elseif ($utmifyHttpCode !== 200) {
        error_log("[Utmify] ⚠️ Status HTTP não-200 do utmify-pendente.php: " . $utmifyHttpCode . " - Resposta: " . $utmifyResponse);
        if ($utmifyResponseDecoded) {
            error_log("[Utmify] 📋 Detalhes do erro: " . json_encode($utmifyResponseDecoded, JSON_PRETTY_PRINT));
        }
    } else {
        error_log("[Utmify] ✅ Dados enviados com sucesso para utmify-pendente.php");
        if ($utmifyResponseDecoded) {
            error_log("[Utmify] 📦 Resposta decodificada: " . json_encode($utmifyResponseDecoded, JSON_PRETTY_PRINT));
        }
    }
    // ===== FIM DO ENVIO PARA UTMIFY =====

    $responseData = [
        'success' => true,
        'token' => $transactionId,
        'pixCode' => $pixCode,
        'qrCodeUrl' => $pixCode ? 
            'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($pixCode) . '&size=300x300&charset-source=UTF-8&charset-target=UTF-8&qzone=1&format=png&ecc=L' : 
            null,
        'valor' => $valor,
        'logs' => [
            'utmParams' => $utmParams,
            'transacao' => [
                'valor' => $valor,
                'cliente' => $nome_cliente,
                'email' => $email,
                'cpf' => $cpf
            ],
            'otimizeyResponse' => [
                'status' => $otimizeyHttpCode,
                'resposta' => $otimizeyResponse,
                'resposta_decodificada' => $otimizeyResponseDecoded
            ],
            'utmifyResponse' => [
                'status' => $utmifyHttpCode,
                'resposta' => $utmifyResponse,
                'resposta_decodificada' => $utmifyResponseDecoded
            ]
        ]
    ];

    // Verificação adicional para garantir que o QR Code está sendo gerado
    if ($pixCode && !$responseData['qrCodeUrl']) {
        error_log("[GhostsPay] ⚠️ QR Code URL não foi gerado mesmo com pixCode disponível");
        $responseData['qrCodeUrl'] = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($pixCode) . '&size=300x300&charset-source=UTF-8&charset-target=UTF-8&qzone=1&format=png&ecc=L';
    }

    error_log("[GhostsPay] 📤 Enviando resposta ao frontend: " . json_encode($responseData));
    error_log("[GhostsPay] 🧾 Detalhes da resposta - Token: " . $responseData['token'] . ", PixCode: " . ($responseData['pixCode'] ? 'Disponível' : 'Não disponível') . ", QR Code URL: " . ($responseData['qrCodeUrl'] ? 'Gerado' : 'Não gerado'));
    
    echo json_encode($responseData);

} catch (Exception $e) {
    error_log("[GhostsPay] ❌ Erro: " . $e->getMessage());
    error_log("[GhostsPay] 🔍 Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar o PIX: ' . $e->getMessage()
    ]);
}
?>