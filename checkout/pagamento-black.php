<?php
// Headers já definidos pelo roteador pagamento.php
// NÃO remova este comentário - evita redefinição de headers

// Configurações de erro (sem headers)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Define timezone para horário de Brasília
date_default_timezone_set('America/Sao_Paulo');

// ===== CAPTURA IP REAL DO CLIENTE (considerando proxy/CDN) =====
function getClientIP() {
    // Lista de possíveis headers que contêm o IP real do cliente
    $headers = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_REAL_IP',            // Nginx proxy
        'HTTP_X_FORWARDED_FOR',      // Proxy padrão
        'HTTP_CLIENT_IP',            // Proxy
        'REMOTE_ADDR'                // Fallback (IP direto ou do proxy)
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Se for X-Forwarded-For, pode ter múltiplos IPs separados por vírgula
            // Pega o PRIMEIRO IP (o cliente original)
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            
            // Valida se é um IP válido
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                error_log("[IP] ✅ IP real capturado via $header: $ip");
                return $ip;
            }
            
            // Se não passou na validação acima, ainda aceita IPs privados (para testes locais)
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

// ===== CAPTURA IP E SALVA LOG IMEDIATAMENTE =====

// Define caminho do log
$logDir = __DIR__ . '/logs';
$logFilePath = $logDir . '/pix-requests.log';

// Cria diretório se não existir
if (!file_exists($logDir)) {
    $created = @mkdir($logDir, 0777, true);
    if (!$created) {
        error_log("[ERRO CRÍTICO] ❌ Não foi possível criar diretório: $logDir");
    }
}

// Testa se pode escrever no diretório
if (!is_writable($logDir)) {
    error_log("[ERRO CRÍTICO] ❌ Diretório não tem permissão de escrita: $logDir");
    @chmod($logDir, 0777);
}

// Salva log INICIAL com IP (nome será adicionado depois)
$logLineInicial = date('Y-m-d H:i:s') . ' | IP: ' . $client_ip . ' | Status: REQUISIÇÃO INICIADA' . PHP_EOL;
$bytes = @file_put_contents($logFilePath, $logLineInicial, FILE_APPEND | LOCK_EX);

if ($bytes === false) {
    error_log("[ERRO CRÍTICO] ❌ Falha ao escrever no arquivo de log: $logFilePath");
    error_log("[ERRO CRÍTICO] 📁 Diretório existe? " . (file_exists($logDir) ? 'SIM' : 'NÃO'));
    error_log("[ERRO CRÍTICO] 🔓 Diretório gravável? " . (is_writable($logDir) ? 'SIM' : 'NÃO'));
} else {
    error_log("[LOG TXT] ✅ Log inicial salvo com sucesso: $bytes bytes em $logFilePath");
}
// ===== FIM DO LOG INICIAL =====

// Bloqueio de IPs específicos
$blocked_ips = [
    '2804:14d:8e85:8025:5184:a4d6:5ad1:4270',
    '149.102.234.142',
    // '192.168.1.100'
];

// Verifica se o IP do cliente está na lista de IPs bloqueados
if (in_array($client_ip, $blocked_ips)) {
    error_log("[BLOQUEIO] Acesso negado para IP bloqueado: " . $client_ip);
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado'
    ]);
    exit;
}

// Configurações
$secretKey = "sk_AsnixDs3kjyy3FvkbXrYRIXyTBKnRQTgaUzUld1fd4AXhtal";
$apiUrl = "https://api.gateway-magicpay.com/v1/transactions";

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

/**
 * Função para gerar um email fictício baseado no nome
 */
function gerarEmail($nome) {
    $nome = strtolower(trim($nome));
    $nome = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nome));
    $dominios = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'uol.com.br'];
    $dominio = $dominios[array_rand($dominios)];
    
    return $nome . rand(1, 999) . '@' . $dominio;
}

// Função para obter o título baseado no valor do produto
function getUpsellTitle($valor) {
    // Mapeamento de valores para nomes de upsell
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
    // Conecta ao SQLite (arquivo de banco de dados na pasta checkout)
    $dbPath = __DIR__ . '/database.sqlite';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cria a tabela pedidos se ela não existir
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
    
    // Criar índices para melhor performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_status ON pedidos(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_created_at ON pedidos(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_valor ON pedidos(valor)");
    
    error_log("[Pagamento] 🔌 Conectado ao banco de dados SQLite em: " . $dbPath);
    error_log("[Pagamento] 📋 Tabela 'pedidos' verificada/criada com sucesso");

    // Recebe dados JSON do body da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Recebe o valor da requisição (index.html envia JÁ EM CENTAVOS)
    $valor_centavos = $input['valor'] ?? $_POST['valor'] ?? $_GET['valor'] ?? null;
    
    // Se não receber valor, usa o padrão
    if (!$valor_centavos || $valor_centavos <= 0) {
        $valor_centavos = 5940; // Valor padrão em centavos
        error_log("[Pagamento] ⚠️ Valor não recebido, usando padrão: " . $valor_centavos . " centavos");
    }
    
    $valor = $valor_centavos; // Mantém compatibilidade com código existente
    error_log("[Pagamento] 💰 Valor recebido: " . $valor_centavos . " centavos (R$ " . number_format($valor_centavos/100, 2, ',', '.') . ")");

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

    // Capturar dados reais do cliente - primeiro tenta do JSON, depois do POST/GET
    $dadosReais = [
        'nome' => $input['nome'] ?? $_POST['nome'] ?? $_GET['nome'] ?? null,
        'email' => $input['email'] ?? $_POST['email'] ?? $_GET['email'] ?? null,
        'cpf' => $input['cpf'] ?? $_POST['cpf'] ?? $_GET['cpf'] ?? null,
        'telefone' => $input['telefone'] ?? $_POST['telefone'] ?? $_GET['telefone'] ?? null
    ];
    
    // Parâmetros UTM - primeiro tenta do JSON, depois do POST/GET
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

    // Remove parâmetros vazios
    $utmParams = array_filter($utmParams, function($value) {
        return $value !== null && $value !== '';
    });

    error_log("[Pagamento] 👤 Dados reais recebidos: " . json_encode($dadosReais));
    error_log("[Pagamento] 📊 Parâmetros UTM recebidos: " . json_encode($utmParams));

    $utmQuery = http_build_query($utmParams);

    // ===== VALIDAÇÃO E SANITIZAÇÃO DE SEGURANÇA =====
    function sanitizeInput($input, $maxLength = 255) {
        if (empty($input)) return null;
        
        // Remove espaços em branco extras
        $input = trim($input);
        
        // Remove caracteres de controle e null bytes
        $input = preg_replace('/[\x00-\x1F\x7F]/u', '', $input);
        
        // Limita o tamanho
        $input = mb_substr($input, 0, $maxLength, 'UTF-8');
        
        return $input;
    }

    function validateNome($nome) {
        // Sanitiza primeiro
        $nome = sanitizeInput($nome, 100);
        if (empty($nome)) return null;
        
        // Rejeita SQL injection patterns
        $sqlPatterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bEXEC\b|\bEXECUTE\b)/i',
            '/(\-\-|\/\*|\*\/|;)/i',  // Comentários SQL
            '/(\bOR\b|\bAND\b)\s*[\'\"]?\d+[\'\"]?\s*=\s*[\'\"]?\d+/i', // OR 1=1, AND 1=1
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $nome)) {
                error_log("[SECURITY ALERT] 🚨 SQL Injection attempt detected in nome: " . $nome);
                return null; // Rejeita entrada maliciosa
            }
        }
        
        // Permite apenas letras, espaços, apóstrofos e hífens (nomes válidos)
        if (!preg_match('/^[\p{L}\s\'\-]+$/u', $nome)) {
            error_log("[SECURITY ALERT] ⚠️ Invalid character in nome: " . $nome);
            return null;
        }
        
        return $nome;
    }

    function validateEmail($email) {
        $email = sanitizeInput($email, 255);
        if (empty($email)) return null;
        
        // Validação básica de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("[SECURITY ALERT] ⚠️ Invalid email format: " . $email);
            return null;
        }
        
        return $email;
    }

    function validateCPF($cpf) {
        $cpf = sanitizeInput($cpf, 14);
        if (empty($cpf)) return null;
        
        // Remove tudo exceto números
        $cpf = preg_replace('/\D/', '', $cpf);
        
        // Valida se tem 11 dígitos
        if (strlen($cpf) !== 11) {
            return null;
        }
        
        return $cpf;
    }
    // ===== FIM DA VALIDAÇÃO DE SEGURANÇA =====

    // Validar dados reais ANTES de usar
    $dadosValidados = [
        'nome' => validateNome($dadosReais['nome']),
        'email' => validateEmail($dadosReais['email']),
        'cpf' => validateCPF($dadosReais['cpf']),
        'telefone' => !empty($dadosReais['telefone']) ? preg_replace('/\D/', '', $dadosReais['telefone']) : null
    ];

    // Log de segurança
    if ($dadosReais['nome'] && !$dadosValidados['nome']) {
        error_log("[SECURITY ALERT] 🚨 Nome rejeitado por validação: " . $dadosReais['nome']);
    }
    if ($dadosReais['email'] && !$dadosValidados['email']) {
        error_log("[SECURITY ALERT] 🚨 Email rejeitado por validação: " . $dadosReais['email']);
    }
    if ($dadosReais['cpf'] && !$dadosValidados['cpf']) {
        error_log("[SECURITY ALERT] 🚨 CPF rejeitado por validação: " . $dadosReais['cpf']);
    }

    // Usar dados reais se disponíveis E VÁLIDOS, senão gerar dados falsos como fallback
    if (!empty($dadosValidados['nome']) && !empty($dadosValidados['cpf'])) {
        // Usar dados reais VALIDADOS
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
        // Gerar dados falsos como fallback
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

    // Obtém o título do produto baseado no valor
    $produtoTitulo = getUpsellTitle($valor_centavos);
    error_log("[Pagamento] 🏷️ Título do produto: " . $produtoTitulo);

    // ===== ATUALIZA LOG TXT COM NOME DO CLIENTE =====
    $logLineCompleto = date('Y-m-d H:i:s') . ' | IP: ' . $client_ip . ' | Nome: ' . $nome_cliente . ' | Valor: R$ ' . number_format($valor_centavos/100, 2, ',', '.') . PHP_EOL;
    $bytes = @file_put_contents($logFilePath, $logLineCompleto, FILE_APPEND | LOCK_EX);
    
    if ($bytes === false) {
        error_log("[LOG TXT] ❌ ERRO ao salvar log completo");
    } else {
        error_log("[LOG TXT] ✅ Log completo salvo: IP=$client_ip | Nome=$nome_cliente | Bytes: $bytes");
        error_log("[LOG TXT] 📂 Arquivo: $logFilePath");
    }
    // ===== FIM DO LOG COMPLETO =====

    // Gera uma referência única para a transação
    $reference = 'REF-' . time() . '-' . rand(1000, 9999);
    $placa = chr(rand(65, 90)) . chr(rand(65, 90)) . chr(rand(65, 90)) . rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9);

    error_log("[BlackCat] 📝 Preparando dados para envio: " . json_encode([
        'valor' => $valor,
        'valor_centavos' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf,
        'telefone' => $telefone,
        'reference' => $reference
    ]));

    // Preparar dados para a API
    $data = [
        'amount' => $valor_centavos,
        'paymentMethod' => 'pix',
        'pix' => [
            'expiresInDays' => 1
        ],
        'customer' => [
            'name' => $nome_cliente,
            'email' => $email,
            'phone' => '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7),
            'document' => [
                'type' => 'cpf',
                'number' => $cpf
            ],
            'externalRef' => 'md-' . $placa . '-' . time()
        ],
        'items' => [
            [
                'title' => $produtoTitulo,
                'unitPrice' => $valor_centavos,
                'quantity' => 1,
                'tangible' => false,
                'externalRef' => 'PROD-' . $placa
            ]
        ],
        'metadata' => json_encode($utmParams),
        'ip' => $client_ip
    ];
    
    error_log("[BlackCat] 🌐 URL da requisição: " . $apiUrl);
    error_log("[BlackCat] 📦 Dados enviados: " . json_encode($data));
    
    // Fazer requisição para a API
    $authorization = 'Basic ' . base64_encode($secretKey . ':x');
    error_log("[BlackCat] 🔑 Authorization: Basic ***********");
    
    // Fazer a requisição real para a API
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authorization,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // Adiciona opções para debug
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    
    // Log detalhado do cURL
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    error_log("[BlackCat] 🔍 Detalhes da requisição cURL:\n" . $verboseLog);
    
    error_log("[BlackCat] 📊 HTTP Status Code: " . $httpCode);
    if (!empty($curlError)) {
        error_log("[BlackCat] ❌ Erro cURL: " . $curlError . " (errno: " . $curlErrno . ")");
        throw new Exception("Erro cURL: " . $curlError);
    }
    
    curl_close($ch);
    
    if ($response) {
        error_log("[BlackCat] 📄 Resposta bruta: " . $response);
    } else {
        error_log("[BlackCat] ❌ Sem resposta da API");
        throw new Exception("Sem resposta da API");
    }
    
    if ($httpCode === 200 || $httpCode === 201) {
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erro ao decodificar resposta: " . json_last_error_msg() . " - Resposta: " . $response);
        }
        
        error_log("[BlackCat] 📄 Resposta decodificada: " . json_encode($responseData));
        
        if (!isset($responseData['id'])) {
            throw new Exception("ID não encontrado na resposta da API");
        }
        
        // Usa transaction_id da BlackCat
        $transactionId = $responseData['id'];
        
        // Extrair os dados do PIX da resposta
        $pixCopiaECola = '';
        if (isset($responseData['pix']['qrcode'])) {
            $pixCopiaECola = $responseData['pix']['qrcode'];
            error_log("[BlackCat] 💳 Código PIX encontrado em responseData['pix']['qrcode']");
        } elseif (isset($responseData['pix']['qrCode'])) {
            $pixCopiaECola = $responseData['pix']['qrCode'];
            error_log("[BlackCat] 💳 Código PIX encontrado em responseData['pix']['qrCode']");
        } elseif (isset($responseData['pix']['code'])) {
            $pixCopiaECola = $responseData['pix']['code'];
            error_log("[BlackCat] 💳 Código PIX encontrado em responseData['pix']['code']");
        } elseif (isset($responseData['pix']['text'])) {
            $pixCopiaECola = $responseData['pix']['text'];
            error_log("[BlackCat] 💳 Código PIX encontrado em responseData['pix']['text']");
        } elseif (isset($responseData['qrcode'])) {
            $pixCopiaECola = $responseData['qrcode'];
            error_log("[BlackCat] 💳 Código PIX encontrado em responseData['qrcode']");
        }
        
        // Fazer o mesmo para a URL do QR Code
        $qrCodeUrl = '';
        if (isset($responseData['pix']['receiptUrl'])) {
            $qrCodeUrl = $responseData['pix']['receiptUrl'];
            error_log("[BlackCat] 🖼️ URL do QR Code encontrado em responseData['pix']['receiptUrl']");
        } elseif (isset($responseData['pix']['qrcodeUrl'])) {
            $qrCodeUrl = $responseData['pix']['qrcodeUrl'];
            error_log("[BlackCat] 🖼️ URL do QR Code encontrado em responseData['pix']['qrcodeUrl']");
        } elseif (isset($responseData['pix']['imageUrl'])) {
            $qrCodeUrl = $responseData['pix']['imageUrl'];
            error_log("[BlackCat] 🖼️ URL do QR Code encontrado em responseData['pix']['imageUrl']");
        } elseif (isset($responseData['qrcodeUrl'])) {
            $qrCodeUrl = $responseData['qrcodeUrl'];
            error_log("[BlackCat] 🖼️ URL do QR Code encontrado em responseData['qrcodeUrl']");
        }
        
        // Se não tiver QR Code e tiver o código PIX, gera via API pública
        if (empty($qrCodeUrl) && !empty($pixCopiaECola)) {
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($pixCopiaECola);
            error_log("[BlackCat] 🔄 QR Code gerado via API externa: " . $qrCodeUrl);
        }
        
        $txid = isset($responseData['pix']['end2EndId']) ? $responseData['pix']['end2EndId'] : '';
        if (empty($txid) && isset($responseData['pix']['txid'])) {
            $txid = $responseData['pix']['txid'];
        }
        
        error_log("[BlackCat] 📋 Dados PIX extraídos - qrCode: " . (empty($pixCopiaECola) ? 'vazio' : 'preenchido'));
        error_log("[BlackCat] 📋 Dados PIX extraídos - qrCodeUrl: " . (empty($qrCodeUrl) ? 'vazio' : 'preenchido'));
        error_log("[BlackCat] 📋 Dados PIX extraídos - txid: " . (empty($txid) ? 'vazio' : $txid));
        
        // Salva os dados no SQLite usando INSERT OR REPLACE para permitir atualização de IDs duplicados
        $stmt = $db->prepare("INSERT OR REPLACE INTO pedidos (transaction_id, status, valor, nome, email, cpf, utm_params, created_at, updated_at) 
            VALUES (:transaction_id, 'pending', :valor, :nome, :email, :cpf, :utm_params, :created_at, :updated_at)");
        $stmt->execute([
            'transaction_id' => $transactionId,
            'valor' => $valor_centavos,
            'nome' => $nome_cliente,
            'email' => $email,
            'cpf' => $cpf,
            'utm_params' => json_encode($utmParams),
            'created_at' => date('c'),
            'updated_at' => date('c')
        ]);
        
        error_log("[Pagamento] 💾 Dados salvos/atualizados no banco SQLite com transaction_id: " . $transactionId);
        
        // Garantir que a sessão está ativa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['payment_id'] = $transactionId;
        error_log("[BlackCat] 💳 Transação criada com sucesso: " . $transactionId);
        error_log("[BlackCat] 🔑 Token gerado: " . $transactionId);
        
        error_log("[Sistema] 📡 Iniciando comunicação com utmify-pendente.php");
        
        // Enviar para utmify-pendente.php
        $utmifyData = [
            'orderId' => $transactionId,
            'platform' => 'BlackCat',
            'paymentMethod' => 'pix',
            'status' => 'waiting_payment',
            'createdAt' => date('Y-m-d H:i:s'),
            'approvedDate' => null,
            'refundedAt' => null,
            'customer' => [
                'name' => $nome_cliente,
                'email' => $email,
                'phone' => null,
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
                'gatewayFeeInCents' => isset($responseData['fee']['amount']) ? $responseData['fee']['amount'] : 0,
                'userCommissionInCents' => $valor_centavos
            ],
            'isTest' => false
        ];
        
        error_log("[Utmify] 📦 Preparando dados para envio ao utmify-pendente.php: " . json_encode($utmifyData));
        
        // Usando URL relativa ao servidor web
        $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $utmifyUrl = $serverUrl . "/amazon/utmify-pendente.php";
        error_log("[Sistema] 🔍 URL do utmify-pendente.php: " . $utmifyUrl);
        
        $ch = curl_init($utmifyUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($utmifyData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
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
        $xtrackyToken   = '';
        $xtrackyUrl     = 'https://api.xtracky.com/api/integrations/api';
        $xtrackyLogDir  = __DIR__ . '/../logs';
        if (!is_dir($xtrackyLogDir)) @mkdir($xtrackyLogDir, 0755, true);
        $xtrackyLogFile = $xtrackyLogDir . '/xtracky-' . date('Y-m-d') . '.log';

        $xtrackyPayload = [
            'orderId'    => (string)$transactionId,
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
            'orderId'   => (string)$transactionId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($xtrackyLogFile, $xtLogResp . "\n", FILE_APPEND | LOCK_EX);

        if ($xtHttpCode >= 200 && $xtHttpCode < 300) {
            error_log("[xTracky] ✅ waiting_payment enviado com sucesso (HTTP $xtHttpCode): $xtResponse");
        } else {
            error_log("[xTracky] ❌ Erro ao enviar waiting_payment (HTTP $xtHttpCode): $xtResponse | curl_err: $xtCurlErr");
        }
        // ── fim xTracky ────────────────────────────────────────────────────────
        
        // Preparar resposta para o frontend (seguindo padrão do paradise.php)
        $responseToFrontend = [
            'success' => true,
            'token' => $transactionId,
            'pixCode' => $pixCopiaECola,
            'pixCopiaECola' => $pixCopiaECola, // Adiciona o campo que o frontend espera
            'qrCodeUrl' => $qrCodeUrl,
            'valor' => $valor,
            'expires_at' => isset($responseData['pix']['expiresAt']) ? $responseData['pix']['expiresAt'] : null,
            'logs' => [
                'utmParams' => $utmParams,
                'transacao' => [
                    'valor' => $valor,
                    'cliente' => $nome_cliente,
                    'email' => $email,
                    'cpf' => $cpf,
                    'reference' => $reference
                ],
                'utmifyResponse' => [
                    'status' => $utmifyHttpCode,
                    'resposta' => $utmifyResponse
                ]
            ]
        ];
        
        error_log("[BlackCat] 📤 Enviando resposta ao frontend: " . json_encode($responseToFrontend));
        echo json_encode($responseToFrontend);
    } else {
        // Tratar erro
        $errorMessage = 'Erro ao processar pagamento';
        $errorDetails = '';
        
        if ($response) {
            $responseData = json_decode($response, true);
            error_log("[BlackCat] 📄 Resposta de erro decodificada: " . json_encode($responseData));
            
            if (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
                error_log("[BlackCat] ❌ Mensagem de erro da API: $errorMessage");
            }
            
            // Capturar detalhes do erro
            if (isset($responseData['details'])) {
                $errorDetails = is_array($responseData['details']) ? 
                    json_encode($responseData['details']) : 
                    $responseData['details'];
                error_log("[BlackCat] 🔍 Detalhes do erro: $errorDetails");
            }
        }
        
        throw new Exception($errorMessage . ($errorDetails ? ": " . $errorDetails : ""));
    }
} catch (Exception $e) {
    error_log("[BlackCat] ❌ Erro: " . $e->getMessage());
    error_log("[BlackCat] 🔍 Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar o PIX: ' . $e->getMessage()
    ]);
}
?>