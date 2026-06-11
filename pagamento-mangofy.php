<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);


date_default_timezone_set('America/Sao_Paulo');


function getClientIP() {
    
    $headers = [
        'HTTP_CF_CONNECTING_IP',    
        'HTTP_X_REAL_IP',            
        'HTTP_X_FORWARDED_FOR',      
        'HTTP_CLIENT_IP',            
        'REMOTE_ADDR'                
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                error_log("[IP] ✅ IP real capturado via $header: $ip");
                return $ip;
            }
            
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                error_log("[IP] ⚠️ IP capturado via $header (pode ser privado): $ip");
                return $ip;
            }
        }
    }
    
    error_log("[IP] ❌ Não foi possível capturar IP do cliente");
    return 'IP_DESCONHECIDO';
}

$client_ip = getClientIP();
error_log("[IP] 🌐 IP detectado do cliente: $client_ip");


$logDir = __DIR__ . '/logs';
$logFilePath = $logDir . '/pix-requests.log';


if (!file_exists($logDir)) {
    $created = @mkdir($logDir, 0777, true);
    if (!$created) {
        error_log("[ERRO CRÍTICO] ❌ Não foi possível criar diretório: $logDir");
    }
}


if (!is_writable($logDir)) {
    error_log("[ERRO CRÍTICO] ❌ Diretório não tem permissão de escrita: $logDir");
    @chmod($logDir, 0777);
}


$logLineInicial = date('Y-m-d H:i:s') . ' | IP: ' . $client_ip . ' | Status: REQUISIÇÃO INICIADA' . PHP_EOL;
$bytes = @file_put_contents($logFilePath, $logLineInicial, FILE_APPEND | LOCK_EX);

if ($bytes === false) {
    error_log("[ERRO CRÍTICO] ❌ Falha ao escrever no arquivo de log: $logFilePath");
    error_log("[ERRO CRÍTICO] 📁 Diretório existe? " . (file_exists($logDir) ? 'SIM' : 'NÃO'));
    error_log("[ERRO CRÍTICO] 🔓 Diretório gravável? " . (is_writable($logDir) ? 'SIM' : 'NÃO'));
} else {
    error_log("[LOG TXT] ✅ Log inicial salvo com sucesso: $bytes bytes em $logFilePath");
}


$blocked_ips = [
    '2804:14d:8e85:8025:5184:a4d6:5ad1:4270',
    '149.102.234.142',
    
];


if (in_array($client_ip, $blocked_ips)) {
    error_log("[BLOQUEIO] Acesso negado para IP bloqueado: " . $client_ip);
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado'
    ]);
    exit;
}


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

// Função para gerar email dinâmico
function gerarEmail($nome) {
    $dominios = [
        '@gmail.com', '@hotmail.com', '@yahoo.com.br', '@outlook.com', 
        '@uol.com.br', '@bol.com.br', '@terra.com.br', '@ig.com.br',
        '@globo.com', '@r7.com', '@live.com', '@msn.com'
    ];
    
    // Remove acentos e espaços do nome
    $nomeEmail = strtolower($nome);
    $nomeEmail = str_replace(' ', '', $nomeEmail);
    $nomeEmail = preg_replace('/[^a-z0-9]/', '', $nomeEmail);
    
    // Adiciona números aleatórios ao final
    $numeros = rand(10, 9999);
    $dominio = $dominios[array_rand($dominios)];
    
    return $nomeEmail . $numeros . $dominio;
}

// Função para mapear valor para título do produto
function getUpsellTitle($valor) {
     switch($valor) {
        case 5940:
            return 'Curso Helton Vieira';
        case 3000:
            return 'Caixa Lote Bronze';
        case 5000:
            return 'Caixa Lote Prata';
        case 7000:
            return 'Caixa Lote Ouro';
        case 7990:
            return 'Caixa Lote Ouro';
        case 12970:
            return 'Caixa Lote Ouro';
        case 11980:
            return 'Caixa Lote Ouro';
        case 6990:
            return 'Caixa Lote Ouro';
        case 9990:
            return 'Caixa Lote Prata';
        case 8990:
            return 'Caixa Lote Prata';
        case 9980:
            return 'Caixa Lote Prata';
        case 10970:
            return 'Caixa Lote Ouro';
        case 7980:
            return 'Caixa Lote Bronze';
        case 5990:
            return 'Caixa Lote Bronze';
        case 6980:
            return 'Caixa Lote Prata';
        case 1990:
            return 'Caixa Ouro Amazon - Upsell';
        case 3990:
            return 'Caixa Lote Bronze';
        case 4990:
            return 'Caixa Lote Bronze';
        case 5980:
            return 'Caixa Lote Bronze';
        case 8970:
            return 'Caixa Lote Bronze';
        case 2790:
            return 'Taxa de Verificação';
        default:
            return 'Produto ' . ($valor/100);
    }
}

try {
    // Configurações da nova API MangoFy
    $apiUrl = 'https://checkout.mangofy.com.br/api/v1/payment';
    $secretKey = '2c8d6500e77fdd9839e78585e10c506937slc9z9m0ajc2zvqthb8wmzpydmqok';
    $apiKey = '637fa1ec5a1a869958178977646647b9';

    
    $dbPath = __DIR__ . '/database.sqlite'; 
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS pedidos (
            transaction_id TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            valor INTEGER NOT NULL,
            nome TEXT,
            email TEXT,
            cpf TEXT,
            utm_params TEXT,
            created_at TEXT,
            updated_at TEXT
        )
    ";
    
    $db->exec($createTableSQL);
    
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_status ON pedidos(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_created_at ON pedidos(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_valor ON pedidos(valor)");
    
    error_log("[Pagamento] 🔌 Conectado ao banco de dados SQLite em: " . $dbPath);
    error_log("[Pagamento] 📋 Tabela 'pedidos' verificada/criada com sucesso");

    
    $input = json_decode(file_get_contents('php://input'), true);
    
    
    $valor_centavos = $input['valor'] ?? $_POST['valor'] ?? $_GET['valor'] ?? null;
    
    
    if (!$valor_centavos || $valor_centavos <= 0) {
        $valor_centavos = 6789; 
        error_log("[Pagamento] ⚠️ Valor não recebido, usando padrão: " . $valor_centavos . " centavos");
    }
    
    $valor = $valor_centavos; 
    error_log("[Pagamento] 💰 Valor recebido: " . $valor_centavos . " centavos (R$ " . number_format($valor_centavos/100, 2, ',', '.') . ")");

    
    $nomes_masculinos = [
        'Joao', 'Pedro', 'Lucas', 'Miguel', 'Arthur', 'Gabriel', 'Bernardo', 'Rafael',
        'Gustavo', 'Felipe', 'Daniel', 'Matheus', 'Bruno', 'Thiago', 'Carlos',
        'Anderson', 'Eduardo', 'Vinicius', 'Leonardo', 'Diego', 'Rodrigo', 'Samuel',
        'Alexandre', 'Henrique', 'Igor', 'Marcelo', 'Renan', 'Vitor', 'Caio', 'Victor',
        'Antonio', 'Paulo', 'Luiz', 'Fernando', 'Roberto', 'Jorge', 'Alan', 'Elias',
        'Murilo', 'Ricardo', 'Luciano', 'Wesley', 'Adriano', 'Otavio', 'Tiago', 'Jose',
        'Jonathan', 'Cristiano', 'Leandro', 'Nathan'
    ];
    

    $nomes_femininos = [
        'Maria', 'Ana', 'Julia', 'Sofia', 'Isabella', 'Helena', 'Valentina', 'Laura',
        'Alice', 'Manuela', 'Beatriz', 'Clara', 'Luiza', 'Mariana', 'Sophia',
        'Camila', 'Larissa', 'Livia', 'Fernanda', 'Bruna', 'Leticia', 'Aline',
        'Jéssica', 'Patricia', 'Tatiane', 'Vanessa', 'Bianca', 'Juliana', 'Karina',
        'Tainara', 'Carla', 'Caroline', 'Cristina', 'Gabriela', 'Daniela', 'Evelyn',
        'Isadora', 'Renata', 'Flavia', 'Nathalia', 'Debora', 'Pamela', 'Lorena',
        'Rafaela', 'Cintia', 'Talita', 'Nicole', 'Lara', 'Clarice', 'Simone'
    ];
    

    $sobrenomes = [
        'Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves',
        'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Carvalho',
        'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira', 'Barbosa',
        'Araujo', 'Dias', 'Teixeira', 'Moura', 'Castro', 'Campos', 'Reis', 'Pinto',
        'Mendes', 'Farias', 'Cavalcante', 'Batista', 'Monteiro', 'Machado', 'Ramos',
        'Cardoso', 'Freitas', 'Borges', 'Nascimento', 'Antunes', 'Xavier', 'Miranda',
        'Figueiredo', 'Duarte', 'Coelho', 'Andrade', 'Tavares', 'Peixoto', 'Correia',
        'Barros'
    ];
    

    
    $dadosReais = [
        'nome' => $input['nome'] ?? $_POST['nome'] ?? $_GET['nome'] ?? null,
        'email' => $input['email'] ?? $_POST['email'] ?? $_GET['email'] ?? null,
        'cpf' => $input['cpf'] ?? $_POST['cpf'] ?? $_GET['cpf'] ?? null,
        'telefone' => $input['telefone'] ?? $_POST['telefone'] ?? $_GET['telefone'] ?? null
    ];
    
    
    $utmParams = [
        'utm_source' => $input['utm_source'] ?? $_POST['utm_source'] ?? $_GET['utm_source'] ?? null,
        'utm_medium' => $input['utm_medium'] ?? $_POST['utm_medium'] ?? $_GET['utm_medium'] ?? null,
        'utm_campaign' => $input['utm_campaign'] ?? $_POST['utm_campaign'] ?? $_GET['utm_campaign'] ?? null,
        'utm_content' => $input['utm_content'] ?? $_POST['utm_content'] ?? $_GET['utm_content'] ?? null,
        'utm_term' => $input['utm_term'] ?? $_POST['utm_term'] ?? $_GET['utm_term'] ?? null,
        'xcod' => $input['xcod'] ?? $_POST['xcod'] ?? $_GET['xcod'] ?? null,
        'sck' => $input['sck'] ?? $_POST['sck'] ?? $_GET['sck'] ?? null,
        'src' => $input['src'] ?? $_POST['src'] ?? $_GET['src'] ?? null,
        'utm_id' => $input['utm_id'] ?? $_POST['utm_id'] ?? $_GET['utm_id'] ?? null
    ];

    
    $utmParams = array_filter($utmParams, function($value) {
        return $value !== null && $value !== '';
    });

    error_log("[Pagamento] � Dados reais recebidos: " . json_encode($dadosReais));
    error_log("[Pagamento] 📊 Parâmetros UTM recebidos: " . json_encode($utmParams));

    $utmQuery = http_build_query($utmParams);

    
    function sanitizeInput($input, $maxLength = 255) {
        if (empty($input)) return null;
        
        
        $input = trim($input);
        
        
        $input = preg_replace('/[\x00-\x1F\x7F]/u', '', $input);
        
        
        $input = mb_substr($input, 0, $maxLength, 'UTF-8');
        
        return $input;
    }

    function validateNome($nome) {
        
        $nome = sanitizeInput($nome, 100);
        if (empty($nome)) return null;
        
        
        $sqlPatterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bEXEC\b|\bEXECUTE\b)/i',
            '/(\-\-|\/\*|\*\/|;)/i',  
            '/(\bOR\b|\bAND\b)\s*[\'\"]?\d+[\'\"]?\s*=\s*[\'\"]?\d+/i', 
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $nome)) {
                error_log("[SECURITY ALERT] � SQL Injection attempt detected in nome: " . $nome);
                return null; 
            }
        }
        
        
        if (!preg_match('/^[\p{L}\s\'\-]+$/u', $nome)) {
            error_log("[SECURITY ALERT] ⚠️ Invalid character in nome: " . $nome);
            return null;
        }
        
        return $nome;
    }

    function validateEmail($email) {
        $email = sanitizeInput($email, 255);
        if (empty($email)) return null;
        
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("[SECURITY ALERT] ⚠️ Invalid email format: " . $email);
            return null;
        }
        
        return $email;
    }

    function validateCPF($cpf) {
        $cpf = sanitizeInput($cpf, 14);
        if (empty($cpf)) return null;
        
       
        $cpf = preg_replace('/\D/', '', $cpf);
        
        
        if (strlen($cpf) !== 11) {
            return null;
        }
        
        return $cpf;
    }
    

    
    $dadosValidados = [
        'nome' => validateNome($dadosReais['nome']),
        'email' => validateEmail($dadosReais['email']),
        'cpf' => validateCPF($dadosReais['cpf']),
        'telefone' => !empty($dadosReais['telefone']) ? preg_replace('/\D/', '', $dadosReais['telefone']) : null
    ];

    
    if ($dadosReais['nome'] && !$dadosValidados['nome']) {
        error_log("[SECURITY ALERT] 🚨 Nome rejeitado por validação: " . $dadosReais['nome']);
    }
    if ($dadosReais['email'] && !$dadosValidados['email']) {
        error_log("[SECURITY ALERT] 🚨 Email rejeitado por validação: " . $dadosReais['email']);
    }
    if ($dadosReais['cpf'] && !$dadosValidados['cpf']) {
        error_log("[SECURITY ALERT] 🚨 CPF rejeitado por validação: " . $dadosReais['cpf']);
    }

    
    if (!empty($dadosValidados['nome']) && !empty($dadosValidados['cpf'])) {
        
        $nome_cliente = $dadosValidados['nome'];
        $cpf = $dadosValidados['cpf'];
        $telefone = $dadosValidados['telefone'] ?: '11999999999';
        
        // Usa email validado ou gera baseado no nome
        if (!empty($dadosValidados['email'])) {
            $email = $dadosValidados['email'];
            error_log("[Pagamento] 📧 Usando email REAL validado: " . $email);
        } else {
            $email = gerarEmail($nome_cliente);
            error_log("[Pagamento] 📧 Email gerado baseado no nome: " . $email);
        }
        
        error_log("[Pagamento] ✅ Usando dados REAIS VALIDADOS do cliente: Nome: $nome_cliente, CPF: " . substr($cpf, 0, 3) . ".***.***-" . substr($cpf, -2));
    } else {
        
        $genero = rand(0, 1);
        $nome = $genero ? 
            $nomes_masculinos[array_rand($nomes_masculinos)] : 
            $nomes_femininos[array_rand($nomes_femininos)];
        
        $sobrenome1 = $sobrenomes[array_rand($sobrenomes)];
        $sobrenome2 = $sobrenomes[array_rand($sobrenomes)];
        
        $nome_cliente = "$nome $sobrenome1 $sobrenome2";
        $email = gerarEmail($nome_cliente);
        $cpf = gerarCPF();
        $telefone = '11999999999';
        
        error_log("[Pagamento] ⚠️ Usando dados FALSOS como fallback: Nome: $nome_cliente, CPF: $cpf, Telefone: $telefone");
    }

    
    $logLineCompleto = date('Y-m-d H:i:s') . ' | IP: ' . $client_ip . ' | Nome: ' . $nome_cliente . ' | Valor: R$ ' . number_format($valor_centavos/100, 2, ',', '.') . PHP_EOL;
    $bytes = @file_put_contents($logFilePath, $logLineCompleto, FILE_APPEND | LOCK_EX);
    
    if ($bytes === false) {
        error_log("[LOG TXT] ❌ ERRO ao salvar log completo");
    } else {
        error_log("[LOG TXT] ✅ Log completo salvo: IP=$client_ip | Nome=$nome_cliente | Bytes: $bytes");
        error_log("[LOG TXT] 📂 Arquivo: $logFilePath");
    }

    // Obter título do produto baseado no valor
    $produtoTitulo = getUpsellTitle($valor_centavos);
    error_log("[Pagamento] 🏷️ Título do produto: " . $produtoTitulo);

    error_log("[MangoFy] 📝 Preparando dados para envio: " . json_encode([
        'valor' => $valor,
        'valor_centavos' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf
    ]));

      $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $currentDir = dirname($_SERVER['REQUEST_URI']);
    $postbackUrl = $serverUrl . $currentDir . "/webhook-mango.php";
    $data = [
        "store_code" => $apiKey,
        "external_code" => uniqid('deposito_'),
        "payment_method" => "pix",
        "payment_format" => "regular",
        "installments" => 1,
        "payment_amount" => $valor_centavos,
        "shipping_amount" => 0,
        "postback_url" => $postbackUrl,
        "items" => [
            [
                "code" => "1",
                "name" => $produtoTitulo,
                "amount" => $valor_centavos,
                "total" => 1
            ]
        ],
        "customer" => [
            "email" => $email,
            "name" => $nome_cliente,
            "document" => $cpf,
            "phone" => $telefone,
            "ip" => $client_ip
        ],
        "pix" => [
            "expires_in_days" => 1
        ],
        "extra" => [
            "utm_source" => $utmParams['utm_source'] ?? '',
            "utm_medium" => $utmParams['utm_medium'] ?? '',
            "utm_campaign" => $utmParams['utm_campaign'] ?? '',
            "utm_content" => $utmParams['utm_content'] ?? '',
            "utm_term" => $utmParams['utm_term'] ?? '',
            "xcod" => $utmParams['xcod'] ?? '',
            "sck" => $utmParams['sck'] ?? ''
        ]
    ];

    error_log("[MangoFy] 🌐 URL da requisição: " . $apiUrl);
    error_log("[MangoFy] 📦 Dados enviados: " . json_encode($data));

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $secretKey,
        'Store-Code: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
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
    error_log("[MangoFy] 🔍 Detalhes da requisição cURL:\n" . $verboseLog);

    if ($curlError) {
        error_log("[MangoFy] ❌ Erro cURL: " . $curlError . " (errno: " . $curlErrno . ")");
        throw new Exception("Erro na requisição: " . $curlError);
    }

    curl_close($ch);

    error_log("[MangoFy] 📊 HTTP Status Code: " . $httpCode);
    error_log("[MangoFy] 📄 Resposta bruta: " . $response);

    if ($httpCode !== 200) {
        throw new Exception("Erro na API: HTTP " . $httpCode . " - " . $response);
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar resposta: " . json_last_error_msg() . " - Resposta: " . $response);
    }

    if (!isset($result['payment_code'])) {
        throw new Exception("payment_code não encontrado na resposta da API");
    }

    // Adaptar a resposta da MangoFy para manter compatibilidade
    $payment_id = $result['payment_code'];
    
    // Buscar o código PIX da resposta da API MangoFy
    $pixCode = null;
    
    // Log da resposta completa para debug
    error_log("[MangoFy] 🔍 Analisando resposta completa: " . print_r($result, true));
    
    if (isset($result['pix']['pix_qrcode_text'])) {
        $pixCode = $result['pix']['pix_qrcode_text'];
        error_log("[MangoFy] 📱 PIX Code encontrado em 'pix.pix_qrcode_text': " . $pixCode);
    } elseif (isset($result['pix']['pix_link'])) {
        $pixCode = $result['pix']['pix_link'];
        error_log("[MangoFy] 📱 PIX Link encontrado em 'pix.pix_link': " . $pixCode);
    } elseif (isset($result['pix_code'])) {
        $pixCode = $result['pix_code'];
        error_log("[MangoFy] 📱 PIX Code encontrado em 'pix_code': " . $pixCode);
    } elseif (isset($result['pix']['code'])) {
        $pixCode = $result['pix']['code'];
        error_log("[MangoFy] 📱 PIX Code encontrado em 'pix.code': " . $pixCode);
    } else {
        error_log("[MangoFy] ⚠️ PIX Code não encontrado na resposta");
    }

    
    $stmt = $db->prepare("INSERT OR REPLACE INTO pedidos (transaction_id, status, valor, nome, email, cpf, utm_params, created_at, updated_at) 
        VALUES (:transaction_id, 'pending', :valor, :nome, :email, :cpf, :utm_params, :created_at, :updated_at)");
    $stmt->execute([
        'transaction_id' => $payment_id,
        'valor' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf,
        'utm_params' => json_encode($utmParams),
        'created_at' => date('c'),
        'updated_at' => date('c')
    ]);
    
    error_log("[Pagamento] 💾 Dados salvos/atualizados no banco SQLite com transaction_id: " . $payment_id);

    session_start();
    $_SESSION['payment_id'] = $payment_id;

    error_log("[MangoFy] 💳 Transação criada com sucesso: " . $payment_id);
    error_log("[MangoFy] 📄 Resposta completa da API: " . $response);
    error_log("[MangoFy] 🔑 Token gerado: " . $payment_id);

   
    error_log("[Sistema] 📡 Iniciando comunicação com otimizey-pendente.php");

    $otimizeyData = [
        'externalUserRef' => $email,
        'product' => [
            'id' => 'produto-checkout',
            'name' => $produtoTitulo,
            'price' => floatval($valor_centavos / 100)
        ],
        'orderId' => $payment_id,
        'paymentMethod' => 'pix',
        'status' => 'waiting_payment',
        'totalPrice' => floatval($valor_centavos / 100),
        'receivedPrice' => floatval($valor_centavos / 100),
        'name' => $nome_cliente,
        'phone' => $telefone
    ];

    
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
    

    error_log("[Sistema] 📡 Iniciando comunicação com utmify-pendente.php");

    $utmifyData = [
        'orderId' => $payment_id,
        'platform' => 'MinhaPlataforma',
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
            'ip' => $client_ip
        ],
        'products' => [
            [
                'id' => uniqid('PROD_'),
                'name' => $produtoTitulo,
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

    // Envia para utmify-pendente.php
    error_log("[Sistema] 📡 Enviando requisição POST para ../utmify-pendente.php");
    
   $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $currentDir = dirname($_SERVER['REQUEST_URI']);
        $utmifyUrl = $serverUrl . $currentDir . "/utmify-pendente.php";
        $logs[] = "🌐 URL Utmify pendente construída dinamicamente: " . $utmifyUrl;
    
    $ch = curl_init($utmifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($utmifyData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $utmifyResponse = curl_exec($ch);
    $utmifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $utmifyError = curl_error($ch);
    $utmifyErrno = curl_errno($ch);
    
    error_log("[Sistema] 🔍 Detalhes da requisição Utmify: " . print_r([
        'url' => $utmifyUrl,
        'status' => $utmifyHttpCode,
        'resposta' => $utmifyResponse,
        'erro' => $utmifyError,
        'errno' => $utmifyErrno
    ], true));
    
    curl_close($ch);

    error_log("[Sistema] ✉️ Resposta do utmify-pendente.php: " . $utmifyResponse);
    error_log("[Sistema] 📊 Status code do utmify-pendente.php: " . $utmifyHttpCode);

    if ($utmifyHttpCode !== 200) {
        error_log("[Sistema] ❌ Erro ao enviar dados para utmify-pendente.php: " . $utmifyResponse);
    } else {
        error_log("[Sistema] ✅ Dados enviados com sucesso para utmify-pendente.php");
    }

    // ── xTracky — waiting_payment (server-side) ───────────────────────────
    $xtrackyToken   = '41f6c466-fcdf-4b03-bf1f-99e935c6db4d';
    $xtrackyUrl     = 'https://api.xtracky.com/api/integrations/api';
    $xtrackyLogDir  = __DIR__ . '/../logs';
    if (!is_dir($xtrackyLogDir)) @mkdir($xtrackyLogDir, 0755, true);
    $xtrackyLogFile = $xtrackyLogDir . '/xtracky-' . date('Y-m-d') . '.log';

    $xtrackyPayload = [
        'orderId'    => (string)$payment_id,
        'amount'     => (int)$valor_centavos,
        'status'     => 'waiting_payment',
        'utm_source' => $utmParams['utm_source'] ?? '',
        'token'      => $xtrackyToken,
    ];

    $xtLogLine = '[' . date('Y-m-d H:i:s') . '] [PAYLOAD_ENVIADO] ' . json_encode($xtrackyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($xtrackyLogFile, $xtLogLine . "\n", FILE_APPEND | LOCK_EX);
    error_log("[xTracky] 📤 Enviando waiting_payment: " . json_encode($xtrackyPayload));

    $chXt = curl_init($xtrackyUrl);
    curl_setopt_array($chXt, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($xtrackyPayload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $xtResponse = curl_exec($chXt);
    $xtHttpCode = curl_getinfo($chXt, CURLINFO_HTTP_CODE);
    $xtCurlErr  = curl_error($chXt);
    curl_close($chXt);

    $xtLogResp = '[' . date('Y-m-d H:i:s') . '] [RESPOSTA] ' . json_encode([
        'http_code' => $xtHttpCode,
        'response'  => $xtResponse,
        'curl_err'  => $xtCurlErr ?: null,
        'orderId'   => (string)$payment_id,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($xtrackyLogFile, $xtLogResp . "\n", FILE_APPEND | LOCK_EX);

    if ($xtHttpCode >= 200 && $xtHttpCode < 300) {
        error_log("[xTracky] ✅ waiting_payment enviado com sucesso (HTTP $xtHttpCode): $xtResponse");
    } else {
        error_log("[xTracky] ❌ Erro ao enviar waiting_payment (HTTP $xtHttpCode): $xtResponse | curl_err: $xtCurlErr");
    }
    // ── fim xTracky ────────────────────────────────────────────────────────

    
    $qrCodeUrl = $pixCode ? 
        'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($pixCode) : 
        null;
    
    if (empty($qrCodeUrl) && !empty($pixCode)) {
        error_log("[MangoFy] 🔄 QR Code gerado via API externa: " . $qrCodeUrl);
    }

    
    $responseData = [
        'success' => true,
        'token' => $payment_id,
        'pixCode' => $pixCode,
        'pixCopiaECola' => $pixCode, 
        'qrCodeUrl' => $qrCodeUrl, 
        'valor' => $valor,
        'expires_at' => isset($result['pix']['expires_at']) ? $result['pix']['expires_at'] : null,
        'logs' => [
            'utmParams' => $utmParams,
            'transacao' => [
                'valor' => $valor,
                'cliente' => $nome_cliente,
                'email' => $email,
                'cpf' => $cpf,
                'reference' => 'REF-' . $payment_id
            ],
            'otimizeyResponse' => [
                'status' => $otimizeyHttpCode,
                'resposta' => $otimizeyResponse
            ],
            'utmifyResponse' => [
                'status' => $utmifyHttpCode,
                'resposta' => $utmifyResponse
            ]
        ]
    ];

    error_log("[MangoFy] 📤 Enviando resposta ao frontend: " . json_encode($responseData));
    echo json_encode($responseData);

} catch (Exception $e) {
    error_log("[MangoFy] ❌ Erro: " . $e->getMessage());
    error_log("[MangoFy] 🔍 Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar o PIX: ' . $e->getMessage()
    ]);
}
?>