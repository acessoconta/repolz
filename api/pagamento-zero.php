<?php
// Headers já definidos pelo roteador pagamento.php
// NÃO remova este comentário - evita redefinição de headers

// Habilita o log de erros
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

// Configurações da API Disrupty
$apiToken = "";
$apiUrl = "https://api.ironpayapp.com.br/api/public/v1/transactions";
$offerHash = ""; // Hash da oferta
$productHash = ""; // Hash do produto

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
        '/(\bOR\b|\bAND\b)\s*[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+/i', // OR 1=1, AND 1=1
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

// Função para gerar endereço aleatório
function gerarEndereco() {
    $ruas = ['Avenida Brasil', 'Rua das Flores', 'Rua São Paulo', 'Avenida Paulista', 'Rua Rio de Janeiro'];
    $bairros = ['Centro', 'Jardim América', 'Vila Nova', 'Bela Vista', 'Santa Cruz'];
    
    return [
        'street_name' => $ruas[array_rand($ruas)],
        'number' => (string)rand(1, 999),
        'neighborhood' => $bairros[array_rand($bairros)],
        'city' => 'São Paulo',
        'state' => 'SP',
        'zip_code' => sprintf('%08d', rand(1000000, 99999999))
    ];
}

// Array para armazenar logs
$logs = [];
$logs[] = "Iniciando processamento de pagamento PIX - " . date('Y-m-d H:i:s');
$logs[] = "API URL: $apiUrl";
$logs[] = "Offer Hash: $offerHash";
$logs[] = "Product Hash: $productHash";

try {
    // Conecta ao SQLite (arquivo de banco de dados)
    $dbPath = __DIR__ . '/database.sqlite'; // Caminho para o arquivo SQLite
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $logs[] = "Conexão com o banco de dados estabelecida com sucesso";
    $logs[] = "Caminho do banco: $dbPath";

    // Verifica se a tabela 'pedidos' existe e cria se necessário
    $db->exec("CREATE TABLE IF NOT EXISTS pedidos (
        transaction_id TEXT PRIMARY KEY,
        status TEXT NOT NULL,
        valor INTEGER NOT NULL,
        nome TEXT,
        email TEXT,
        cpf TEXT,
        utm_params TEXT,
        created_at TEXT,
        updated_at TEXT
    )");

    // Recebe dados JSON do body da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Recebe o valor da requisição (index.html envia JÁ EM CENTAVOS)
    $valor_centavos = $input['valor'] ?? $_POST['valor'] ?? $_GET['valor'] ?? null;
    
    // Se não receber valor, usa o padrão
    if (!$valor_centavos || $valor_centavos <= 0) {
        $valor_centavos = 6190; // Valor padrão em centavos
        error_log("[Pagamento Zero] ⚠️ Valor não recebido, usando padrão: " . $valor_centavos . " centavos");
    }
    
    $valor = $valor_centavos; // Mantém compatibilidade com código existente
    error_log("[Pagamento Zero] 💰 Valor recebido: " . $valor_centavos . " centavos (R$ " . number_format($valor_centavos/100, 2, ',', '.') . ")");

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

    error_log("[Pagamento Zero] 👤 Dados reais recebidos: " . json_encode($dadosReais));
    error_log("[Pagamento Zero] 📊 Parâmetros UTM recebidos: " . json_encode($utmParams));
    $logs[] = "Parâmetros UTM recebidos: " . json_encode($utmParams);

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
            error_log("[Pagamento Zero] 📧 Usando email REAL validado: " . $email);
        } else {
            $email = strtolower(str_replace([' ', '+'], ['.', '.'], $nome_cliente)) . '@email.com';
            error_log("[Pagamento Zero] 📧 Email gerado baseado no nome: " . $email);
        }
        
        error_log("[Pagamento Zero] ✅ Usando dados REAIS VALIDADOS do cliente: Nome: $nome_cliente, CPF: " . substr($cpf, 0, 3) . ".***.***-" . substr($cpf, -2));
        $logs[] = "Usando dados REAIS VALIDADOS do cliente";
    } else {
        // Gerar dados falsos como fallback
        $genero = rand(0, 1);
        $nome = $genero ? 
            $nomes_masculinos[array_rand($nomes_masculinos)] : 
            $nomes_femininos[array_rand($nomes_femininos)];
        
        $sobrenome1 = $sobrenomes[array_rand($sobrenomes)];
        $sobrenome2 = $sobrenomes[array_rand($sobrenomes)];
        
        $nome_cliente = "$nome $sobrenome1 $sobrenome2";
        $email = strtolower(str_replace(' ', '.', $nome_cliente)) . '@email.com';
        $cpf = gerarCPF();
        $telefone = '11999999999';
        
        error_log("[Pagamento Zero] ⚠️ Usando dados FALSOS como fallback: Nome: $nome_cliente, CPF: $cpf, Telefone: $telefone");
        $logs[] = "Usando dados FALSOS como fallback";
    }
    
    $endereco = gerarEndereco();

    $logs[] = "Dados finais do cliente: " . json_encode([
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf,
        'telefone' => $telefone
    ]);

    // Preparar dados para a API Disrupty
    $data = [
        'amount' => $valor_centavos,
        'offer_hash' => $offerHash,
        'payment_method' => 'pix',
        'installments' => 1,
        'utm_source' => $utmParams['utm_source'] ?? null,
        'utm_campaign' => $utmParams['utm_campaign'] ?? null,
        'utm_content' => $utmParams['utm_content'] ?? null,
        'utm_term' => $utmParams['utm_term'] ?? null,
        'utm_medium' => $utmParams['utm_medium'] ?? null,
        'customer' => [
            'name' => $nome_cliente,
            'email' => $email,
            'phone_number' => $telefone,
            'document' => $cpf,
            'street_name' => $endereco['street_name'],
            'number' => $endereco['number'],
            'neighborhood' => $endereco['neighborhood'],
            'city' => $endereco['city'],
            'state' => $endereco['state'],
            'zip_code' => $endereco['zip_code']
        ],
        'cart' => [
            [
                'product_hash' => $productHash,
                'title' => "TAXA DE INSCRIÇÃO",
                'price' => $valor_centavos,
                'quantity' => 1,
                'operation_type' => 1,
                'tangible' => false
            ]
        ],
        'expire_in_days' => 1
    ];

    $logs[] = "Payload para API: " . json_encode($data);
    
    // Fazer a requisição para a API Disrupty
    $ch = curl_init($apiUrl . "?api_token=" . $apiToken);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $logs[] = "Iniciando requisição cURL para API Disrupty";
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $logs[] = "Resposta da API - HTTP Code: $httpCode";
    if (!empty($curlError)) {
        $logs[] = "ERRO cURL: $curlError";
        throw new Exception("Erro na requisição: " . $curlError);
    }
    
    $logs[] = "Resposta bruta: " . $response;
    $responseData = json_decode($response, true);
    $logs[] = "Resposta decodificada: " . json_encode($responseData);
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        $errorMessage = 'Erro ao processar pagamento';
        if (isset($responseData['message'])) {
            $errorMessage = $responseData['message'];
        }
        throw new Exception("Erro na API: HTTP " . $httpCode . " - " . $errorMessage);
    }
    
    // ID da transação da Disrupty 
    $transactionId = $responseData['hash'] ?? '';
    $logs[] = "ID da transação (hash): " . $transactionId;
    
    if (empty($transactionId)) {
        throw new Exception("Hash da transação não encontrado na resposta da API");
    }
    
    // Status do pagamento da Disrupty
    $status = $responseData['payment_status'] ?? 'pending';
    
    // Código QR PIX
    $pixQrCode = $responseData['pix']['pix_qr_code'] ?? '';
    
    if (empty($pixQrCode)) {
        throw new Exception("Código QR PIX não encontrado na resposta da API");
    }
    
    // Salvar dados no banco SQLite
    $stmt = $db->prepare("INSERT INTO pedidos (
        transaction_id, status, valor, nome, email, cpf, 
        utm_params, created_at, updated_at
    ) VALUES (
        :transaction_id, :status, :valor, :nome, :email, :cpf,
        :utm_params, :created_at, :updated_at
    )");

    $stmt->execute([
        'transaction_id' => $transactionId,
        'status' => $status,
        'valor' => $valor_centavos,
        'nome' => $nome_cliente,
        'email' => $email,
        'cpf' => $cpf,
        'utm_params' => json_encode($utmParams),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    $logs[] = "Dados salvos no banco com sucesso";

    // Iniciar comunicação com utmify-pendente.php
    $logs[] = "Iniciando comunicação com utmify-pendente.php";

    $utmifyData = [
        'orderId' => $transactionId,
        'platform' => 'Disrupty',
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
                'id' => uniqid('PROD_'),
                'name' => 'TAXA DE INSCRIÇÃO',
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

    $logs[] = "Dados preparados para utmify-pendente.php: " . json_encode($utmifyData);

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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $utmifyResponse = curl_exec($ch);
    $utmifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $utmifyError = curl_error($ch);
    $utmifyErrno = curl_errno($ch);
    
    $logs[] = "Detalhes da requisição Utmify: " . json_encode([
        'url' => $utmifyUrl,
        'status' => $utmifyHttpCode,
        'resposta' => $utmifyResponse,
        'erro' => $utmifyError,
        'errno' => $utmifyErrno
    ]);
    
    curl_close($ch);

    // Gerar QR Code usando o código PIX
    $qrCodeImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($pixQrCode);
    
    // Formatar o valor corretamente
    $valorFormatado = 'R$ ' . number_format($valor_centavos/100, 2, ',', '.');
    
    // Retorna resposta para o frontend
    $responseToFrontend = [
        'success' => true,
        'token' => $transactionId,
        'qrCodeUrl' => $qrCodeImageUrl,
        'pixCode' => $pixQrCode,
        'valor' => $valor_centavos,
        'valorFormatado' => $valorFormatado,
        'logs' => [
            'utmParams' => $utmParams,
            'transacao' => [
                'valor' => $valor_centavos,
                'cliente' => $nome_cliente,
                'email' => $email,
                'cpf' => $cpf
            ],
            'utmifyResponse' => [
                'status' => $utmifyHttpCode,
                'resposta' => $utmifyResponse
            ]
        ]
    ];

    $logs[] = "Enviando resposta ao frontend: " . json_encode($responseToFrontend);
    echo json_encode($responseToFrontend);

} catch (Exception $e) {
    $logs[] = "ERRO: " . $e->getMessage();
    $logs[] = "Stack trace: " . $e->getTraceAsString();
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar o PIX: ' . $e->getMessage(),
        'logs' => $logs
    ]);
}

// Registra todos os logs
$logsText = implode("\n", $logs);
error_log("=== LOGS DO PROCESSAMENTO DE PAGAMENTO ===\n" . $logsText . "\n=== FIM DOS LOGS ===");
?>