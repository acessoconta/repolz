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

/**
 * Função auxiliar para processar valor dinâmico
 * @param mixed $input_valor Valor recebido (GET/POST)
 * @param int $default_valor Valor padrão em centavos
 * @return int Valor em centavos
 */
function processarValor($input_valor, $default_valor = 5940) {
    if (empty($input_valor)) {
        return $default_valor;
    }

    $valor = floatval($input_valor);

    // Se o valor tem parte decimal (ex: 251.77) OU é menor que 100, está em reais
    if ($valor != floor($valor) || $valor < 100) {
        // Converte reais para centavos
        $valor = round($valor * 100);
    }
    // Caso contrário, já está em centavos

    return intval($valor);
}

/**
 * Valida CPF
 * @param string $cpf CPF para validar (apenas números)
 * @return bool True se válido, False se inválido
 */
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Valida primeiro dígito verificador
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}
$apiKey = "0a78db64-e6ba-4978-8261-2ce93fb8cb96";
$apiUrl = "https://api-gateway.umbrellapag.com/api/user/transactions";

// Array para armazenar logs
$logs = [];
$logs[] = "Iniciando processamento de pagamento PIX";

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


function gerarEmail($nome) {
    $nome = strtolower(trim($nome));
    $nome = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nome));
    $dominios = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'uol.com.br'];
    $dominio = $dominios[array_rand($dominios)];
    
    return $nome . rand(1, 999) . '@' . $dominio;
}

try {
    // Conecta ao SQLite (arquivo de banco de dados)
    $dbPath = __DIR__ . '/database.sqlite'; // Caminho para o arquivo SQLite
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $logs[] = "Conexão com banco de dados SQLite estabelecida: $dbPath";

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
    
    // Criar índices para melhor performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_status ON pedidos(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_created_at ON pedidos(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pedidos_valor ON pedidos(valor)");
    
    $logs[] = "Tabela 'pedidos' verificada/criada com sucesso";

    // Recebe dados JSON do body da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    $logs[] = "Dados recebidos (JSON): " . json_encode($input);

    // Valor dinâmico em centavos
    $valor_default = 5940; // Valor padrão em centavos (R$ 59,40)
    
    // Processa valor dinâmico (prioriza JSON, depois POST, depois GET)
    $input_valor = $input['valor'] ?? $_POST['valor'] ?? $_GET['valor'] ?? null;
    $valor = processarValor($input_valor, $valor_default);
    $valor_centavos = $valor;
    
    // Log do processamento
    if (!empty($input_valor)) {
        $metodo = isset($_GET['valor']) ? 'GET' : 'POST';
        $logs[] = "Valor recebido via $metodo: $input_valor";
    } else {
        $logs[] = "Usando valor padrão: $valor_default centavos";
    }
    
    $logs[] = "Valor final processado: $valor centavos (R$ " . number_format($valor/100, 2, ',', '.') . ")";

    // Validação do valor
    if (!$valor || $valor <= 0) {
        $logs[] = "ERRO: Valor inválido: $valor";
        throw new Exception('Valor inválido. O valor deve ser maior que zero.');
    }
    
    if ($valor < 100) { // Menos de R$ 1,00
        $logs[] = "AVISO: Valor muito baixo: $valor centavos";
    }
    
    if ($valor > 1000000) { // Mais de R$ 10.000,00
        $logs[] = "AVISO: Valor muito alto: $valor centavos";
    }

    // Dados falsos para fallback
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

    // Parâmetros UTM (JSON, POST ou GET) - Completo para Utmify
    $utmParams = [
        'src' => $input['src'] ?? $_POST['src'] ?? $_GET['src'] ?? null,
        'sck' => $input['sck'] ?? $_POST['sck'] ?? $_GET['sck'] ?? null,
        'utm_source' => $input['utm_source'] ?? $_POST['utm_source'] ?? $_GET['utm_source'] ?? null,
        'utm_medium' => $input['utm_medium'] ?? $_POST['utm_medium'] ?? $_GET['utm_medium'] ?? null,
        'utm_campaign' => $input['utm_campaign'] ?? $_POST['utm_campaign'] ?? $_GET['utm_campaign'] ?? null,
        'utm_content' => $input['utm_content'] ?? $_POST['utm_content'] ?? $_GET['utm_content'] ?? null,
        'utm_term' => $input['utm_term'] ?? $_POST['utm_term'] ?? $_GET['utm_term'] ?? null,
        'utm_id' => $input['utm_id'] ?? $_POST['utm_id'] ?? $_GET['utm_id'] ?? null,
        'xcod' => $input['xcod'] ?? $_POST['xcod'] ?? $_GET['xcod'] ?? null,
        'fbclid' => $input['fbclid'] ?? $_POST['fbclid'] ?? $_GET['fbclid'] ?? null,
        'gclid' => $input['gclid'] ?? $_POST['gclid'] ?? $_GET['gclid'] ?? null,
        'ttclid' => $input['ttclid'] ?? $_POST['ttclid'] ?? $_GET['ttclid'] ?? null
    ];

    // Remove parâmetros vazios
    $utmParams = array_filter($utmParams, function($value) {
        return $value !== null && $value !== '';
    });

    $logs[] = "Parâmetros UTM recebidos: " . json_encode($utmParams);
    $utmQuery = http_build_query($utmParams);

    // Recebe dados JSON do body da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Recebe dados do cliente (JSON, POST ou GET) - prioriza JSON
    $nome_cliente_input = $input['nome'] ?? $_POST['nome'] ?? $_GET['nome'] ?? $input['name'] ?? $_POST['name'] ?? $_GET['name'] ?? null;
    $cpf_input = $input['cpf'] ?? $_POST['cpf'] ?? $_GET['cpf'] ?? $input['document'] ?? $_POST['document'] ?? $_GET['document'] ?? null;
    $telefone_input = $input['telefone'] ?? $_POST['telefone'] ?? $_GET['telefone'] ?? $input['telephone'] ?? $_POST['telephone'] ?? $_GET['telephone'] ?? $input['phone'] ?? $_POST['phone'] ?? $_GET['phone'] ?? null;
    $email_input = $input['email'] ?? $_POST['email'] ?? $_GET['email'] ?? null;
    
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
        'nome' => validateNome($nome_cliente_input),
        'email' => validateEmail($email_input),
        'cpf' => validateCPF($cpf_input),
        'telefone' => !empty($telefone_input) ? preg_replace('/\D/', '', $telefone_input) : null
    ];

    // Log de segurança
    if ($nome_cliente_input && !$dadosValidados['nome']) {
        error_log("[SECURITY ALERT] 🚨 Nome rejeitado por validação: " . $nome_cliente_input);
    }
    if ($email_input && !$dadosValidados['email']) {
        error_log("[SECURITY ALERT] 🚨 Email rejeitado por validação: " . $email_input);
    }
    if ($cpf_input && !$dadosValidados['cpf']) {
        error_log("[SECURITY ALERT] 🚨 CPF rejeitado por validação: " . $cpf_input);
    }
    
    // Processa e valida dados recebidos
    $usar_dados_reais = false;
    
    // Usar dados reais se disponíveis E VÁLIDOS, senão gerar dados falsos como fallback
    if (!empty($dadosValidados['nome']) && !empty($dadosValidados['cpf'])) {
        // Usar dados reais VALIDADOS
        $nome_cliente = $dadosValidados['nome'];
        $cpf = $dadosValidados['cpf'];
        $telefone = $dadosValidados['telefone'] ?: '11999999999';
        
        // Usa email validado ou gera baseado no nome
        if (!empty($dadosValidados['email'])) {
            $email = $dadosValidados['email'];
            $logs[] = "📧 Usando email REAL validado: " . $email;
        } else {
            $email = strtolower(str_replace([' ', '+'], ['.', '.'], $nome_cliente)) . '@email.com';
            $logs[] = "📧 Email gerado baseado no nome: " . $email;
        }
        
        $logs[] = "✅ Usando dados REAIS VALIDADOS do cliente: Nome: $nome_cliente, CPF: " . substr($cpf, 0, 3) . ".***.***-" . substr($cpf, -2);
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
        
        $logs[] = "⚠️ Usando dados FALSOS como fallback: Nome: $nome_cliente, CPF: $cpf, Telefone: $telefone";
    }
    // Formata telefone se necessário
    $telefone_apenas_numeros = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone_apenas_numeros) >= 10 && strlen($telefone_apenas_numeros) <= 11) {
        if (strlen($telefone_apenas_numeros) === 11) {
            $telefone_formatado = '(' . substr($telefone_apenas_numeros, 0, 2) . ') ' . substr($telefone_apenas_numeros, 2, 5) . '-' . substr($telefone_apenas_numeros, 7);
        } else {
            $telefone_formatado = '(' . substr($telefone_apenas_numeros, 0, 2) . ') ' . substr($telefone_apenas_numeros, 2, 4) . '-' . substr($telefone_apenas_numeros, 6);
        }
    } else {
        $telefone_formatado = $telefone;
        $telefone_apenas_numeros = '11999999999'; // Fallback
    }
    
    // Placa (sempre gerada)
    $placa = chr(rand(65, 90)) . chr(rand(65, 90)) . chr(rand(65, 90)) . rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9);
    
    $logs[] = "Dados finais: nome=$nome_cliente, cpf=$cpf, telefone=$telefone_formatado, email=$email, placa=$placa";
    
    // Formatar valor para exibição
    $valorFormatado = 'R$ ' . number_format($valor_centavos/100, 2, ',', '.');
    $logs[] = "Valor formatado: $valorFormatado";

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

    // Preparar dados para a API
    $data = [
        'amount' => $valor_centavos,
        'currency' => 'BRL',
        'paymentMethod' => 'pix',
        'installments' => 1,
        'pix' => [
            'expiresInDays' => 1
        ],
        'customer' => [
            'name' => $nome_cliente,
            'email' => $email,
            'phone' => $telefone_apenas_numeros, // Envia apenas números
            'document' => [
                'type' => 'CPF',
                'number' => $cpf
            ],
            'externalRef' => 'md-' . $placa . '-' . time(),
        ],
        'items' => [
            [
                'title' => 'Pedido ' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8)),
                'unitPrice' => $valor_centavos,
                'quantity' => 1,
                'tangible' => false,
                'externalRef' => 'PEDIDO-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8))
            ]
        ],
        'postbackUrl' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['REQUEST_URI']) . '/webhook-umbrella.php',
        'metadata' => json_encode($utmParams),
        'traceable' => true,
        'ip' => $client_ip // Usa o IP capturado pela função getClientIP()
    ];
    
    $logs[] = "Payload para API: " . json_encode($data);
    
    // Validações antes de enviar
    if (empty($nome_cliente) || strlen($nome_cliente) < 3) {
        $logs[] = "ERRO: Nome inválido: $nome_cliente";
        throw new Exception('Nome do cliente inválido');
    }
    
    if (empty($cpf) || strlen($cpf) != 11) {
        $logs[] = "ERRO: CPF inválido: $cpf";
        throw new Exception('CPF inválido');
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $logs[] = "ERRO: Email inválido: $email";
        throw new Exception('Email inválido');
    }
    
    if ($valor_centavos <= 0) {
        $logs[] = "ERRO: Valor inválido: $valor_centavos";
        throw new Exception('Valor inválido');
    }
    
    $logs[] = "✅ Validações passaram - enviando para API";
    
    // Fazer requisição para a API
    $logs[] = "Usando API Key: " . substr($apiKey, 0, 8) . "...";
    
    // Fazer a requisição real para a API
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . $apiKey,
        'User-Agent: AtivoB2B/1.0',
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $logs[] = "Resposta da API - HTTP Code: $httpCode";
    if (!empty($curlError)) {
        $logs[] = "Erro cURL: $curlError";
        throw new Exception("Erro cURL: $curlError");
    }
    
    if ($response) {
        $logs[] = "Resposta bruta: " . $response;
    } else {
        $logs[] = "Sem resposta da API";
        throw new Exception("Sem resposta da API");
    }
    
    if ($httpCode === 200 || $httpCode === 201) {
        $responseData = json_decode($response, true);
        $logs[] = "Resposta decodificada: " . json_encode($responseData);
        
        // A resposta da TechByNet vem em responseData['data']
        if (!isset($responseData['data']['id'])) {
            $logs[] = "ERRO: Estrutura da resposta inesperada";
            $logs[] = "Resposta completa: " . print_r($responseData, true);
            throw new Exception("ID não encontrado na resposta da API");
        }
        
        $transactionData = $responseData['data'];
        
        // Extrair os dados do PIX da resposta
        $pixCopiaECola = '';
        $qrCodeUrl = '';
        $txid = '';
        
        // Na API TechByNet, os dados do PIX vêm em data.pix
        if (isset($transactionData['pix'])) {
            $pixData = $transactionData['pix'];
            
            // Tenta obter o código PIX (qrCode, code, text, etc)
            if (isset($pixData['qrcode'])) {
                $pixCopiaECola = $pixData['qrcode'];
                $logs[] = "Código PIX encontrado em data.pix.qrcode";
            } elseif (isset($pixData['qrCode'])) {
                $pixCopiaECola = $pixData['qrCode'];
                $logs[] = "Código PIX encontrado em data.pix.qrCode";
            } elseif (isset($pixData['code'])) {
                $pixCopiaECola = $pixData['code'];
                $logs[] = "Código PIX encontrado em data.pix.code";
            } elseif (isset($pixData['text'])) {
                $pixCopiaECola = $pixData['text'];
                $logs[] = "Código PIX encontrado em data.pix.text";
            }
            
            // Tenta obter a URL do QR Code
            if (isset($pixData['url'])) {
                $qrCodeUrl = $pixData['url'];
                $logs[] = "URL do QR Code encontrado em data.pix.url";
            } elseif (isset($pixData['qrcodeUrl'])) {
                $qrCodeUrl = $pixData['qrcodeUrl'];
                $logs[] = "URL do QR Code encontrado em data.pix.qrcodeUrl";
            } elseif (isset($pixData['imageUrl'])) {
                $qrCodeUrl = $pixData['imageUrl'];
                $logs[] = "URL do QR Code encontrado em data.pix.imageUrl";
            }
            
            // Tenta obter o txid/endToEndId
            if (isset($pixData['endToEndId'])) {
                $txid = $pixData['endToEndId'];
            } elseif (isset($pixData['txid'])) {
                $txid = $pixData['txid'];
            }
        }
        
        // Também verifica nos campos de nível superior
        if (empty($pixCopiaECola) && isset($transactionData['qrCode'])) {
            $pixCopiaECola = $transactionData['qrCode'];
            $logs[] = "Código PIX encontrado em data.qrCode";
        }
        
        if (empty($txid) && isset($transactionData['endToEndId'])) {
            $txid = $transactionData['endToEndId'];
        }
        
        $logs[] = "Dados PIX extraídos - qrCode: " . (empty($pixCopiaECola) ? 'vazio' : 'preenchido');
        $logs[] = "Dados PIX extraídos - qrCodeUrl: " . (empty($qrCodeUrl) ? 'vazio' : 'preenchido');
        $logs[] = "Dados PIX extraídos - txid: " . (empty($txid) ? 'vazio' : $txid);
        
        // Gerar QR Code usando o QRServer se não tiver URL
        if (empty($qrCodeUrl) && !empty($pixCopiaECola)) {
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($pixCopiaECola);
            $logs[] = "URL do QR Code gerado via QRServer: $qrCodeUrl";
        }
        
        // Verificar se já existe um registro com este transaction_id
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE transaction_id = :transaction_id");
        $checkStmt->execute(['transaction_id' => $transactionData['id']]);
        $exists = (int)$checkStmt->fetchColumn() > 0;
        
        if ($exists) {
            $logs[] = "Pedido já existe no banco de dados. Atualizando informações.";
            $stmt = $db->prepare("UPDATE pedidos SET 
                status = :status, 
                updated_at = :updated_at 
                WHERE transaction_id = :transaction_id");
            $stmt->execute([
                'status' => 'pending',
                'transaction_id' => $transactionData['id'],
                'updated_at' => date('c')
            ]);
        } else {
            // Salva os dados no SQLite - Garantindo que dados sejam inseridos corretamente
            $logs[] = "Inserindo novo registro no banco de dados: " . $transactionData['id'];
            try {
                $stmt = $db->prepare("INSERT INTO pedidos (transaction_id, status, valor, nome, email, cpf, utm_params, created_at) 
                    VALUES (:transaction_id, :status, :valor, :nome, :email, :cpf, :utm_params, :created_at)");
                $result = $stmt->execute([
                    'transaction_id' => $transactionData['id'],
                    'status' => 'pending',
                    'valor' => $valor_centavos,
                    'nome' => $nome_cliente,
                    'email' => $email,
                    'cpf' => $cpf,
                    'utm_params' => json_encode($utmParams),
                    'created_at' => date('c')
                ]);
                
                if ($result) {
                    $logs[] = "Dados salvos com sucesso no banco de dados SQLite";
                } else {
                    $logs[] = "ERRO: Falha ao inserir dados no banco de dados";
                }
            } catch (PDOException $e) {
                $logs[] = "ERRO de banco de dados: " . $e->getMessage();
                // Não interrompe o fluxo, apenas registra o erro
            }
        }
        
        // Garantir que a sessão está ativa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['payment_id'] = $transactionData['id'];
        $logs[] = "ID do pagamento salvo na sessão: " . $transactionData['id'];

        // ========================================
        // INTEGRAÇÃO XTRACKY - CONVERSÃO WAITING_PAYMENT
        // ========================================
        $logs[] = "📤 Iniciando envio para xTracky API";

        $xTrackyData = [
            'orderId' => $transactionData['id'],
            'amount' => $valor_centavos,
            'status' => 'waiting_payment',
            'utm_source' => $utmParams['utm_source'] ?? ''
        ];

        $logs[] = "📦 Payload xTracky: " . json_encode($xTrackyData);

        $chXTracky = curl_init('https://api.xtracky.com/api/integrations/api');
        curl_setopt_array($chXTracky, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($xTrackyData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10
        ]);

        $xTrackyResponse = curl_exec($chXTracky);
        $xTrackyHttpCode = curl_getinfo($chXTracky, CURLINFO_HTTP_CODE);
        $xTrackyError = curl_error($chXTracky);
        curl_close($chXTracky);

        $logs[] = "📥 Resposta xTracky - HTTP Code: $xTrackyHttpCode";
        if (!empty($xTrackyError)) {
            $logs[] = "⚠️ Erro ao enviar para xTracky: $xTrackyError";
        } else {
            $logs[] = "✅ Resposta xTracky: " . $xTrackyResponse;
        }

        if ($xTrackyHttpCode === 200 || $xTrackyHttpCode === 201) {
            $logs[] = "✅ Conversão 'waiting_payment' enviada com sucesso para xTracky";
        } else {
            $logs[] = "⚠️ xTracky retornou código não-200: $xTrackyHttpCode - " . $xTrackyResponse;
        }
        
        // ========================================
        // INTEGRAÇÃO UTMIFY - CONVERSÃO WAITING_PAYMENT
        // ========================================
        $logs[] = "📤 Iniciando envio para Utmify (waiting_payment)";

        $utmifyPendenteData = [
            'orderId' => $transactionData['id'],
            'platform' => 'TechByNet',
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
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ],
            'products' => [
                [
                    'id' => 'PROD_' . rand(1000, 9999),
                    'name' => 'Pagamento',
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
        
        $logs[] = "📦 Payload Utmify pendente: " . json_encode($utmifyPendenteData);

        // Constrói URL dinâmica do Utmify na pasta check
        $serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $currentDir = dirname($_SERVER['REQUEST_URI']);
        $utmifyPendenteUrl = $serverUrl . $currentDir . "/utmify-pendente.php";
        $logs[] = "🌐 URL Utmify pendente construída dinamicamente: " . $utmifyPendenteUrl;

        $chUtmifyPendente = curl_init($utmifyPendenteUrl);
        curl_setopt_array($chUtmifyPendente, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($utmifyPendenteData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10
        ]);

        $utmifyPendenteResponse = curl_exec($chUtmifyPendente);
        $utmifyPendenteHttpCode = curl_getinfo($chUtmifyPendente, CURLINFO_HTTP_CODE);
        $utmifyPendenteError = curl_error($chUtmifyPendente);
        curl_close($chUtmifyPendente);

        $logs[] = "📥 Resposta Utmify pendente - HTTP Code: $utmifyPendenteHttpCode";
        if (!empty($utmifyPendenteError)) {
            $logs[] = "⚠️ Erro ao enviar para Utmify pendente: $utmifyPendenteError";
        } else {
            $logs[] = "✅ Resposta Utmify pendente: " . $utmifyPendenteResponse;
        }

        if ($utmifyPendenteHttpCode === 200 || $utmifyPendenteHttpCode === 201) {
            $logs[] = "✅ Dados enviados com sucesso para Utmify (waiting_payment)";
        } else {
            $logs[] = "⚠️ Utmify pendente retornou código não-200: $utmifyPendenteHttpCode - " . $utmifyPendenteResponse;
        }
        
        // Retornar dados para o frontend (compatível com Paradise)
        $responseToFrontend = [
            'success' => true,
            'token' => $transactionData['id'],
            'pixCode' => $pixCopiaECola,
            'pixCopiaECola' => $pixCopiaECola, // Adiciona o campo que o frontend espera
            'qrCodeUrl' => $qrCodeUrl, // QR Code gerado ou da API
            'valor' => $valor_centavos,
            'nome' => $nome_cliente,
            'cpf' => $cpf,
            'email' => $email,
            'telefone' => $telefone_formatado,
            'placa' => $placa,
            'expiraEm' => '1 dia',
            'expires_at' => date('c', strtotime('+1 day')),
            'txid' => $txid,
            'logs' => [
                'utmParams' => $utmParams,
                'transacao' => [
                    'valor' => $valor_centavos,
                    'cliente' => $nome_cliente,
                    'email' => $email,
                    'cpf' => $cpf,
                    'telefone' => $telefone_formatado
                ],
                'webhookUrl' => $data['postbackUrl'], // URL do webhook construída
                'utmifyUrl' => $utmifyPendenteUrl, // URL do Utmify construída
                'utmifyResponse' => [
                    'status' => $utmifyPendenteHttpCode,
                    'resposta' => $utmifyPendenteResponse
                ],
                'xTrackyResponse' => [
                    'status' => $xTrackyHttpCode,
                    'resposta' => $xTrackyResponse
                ],
                'debug' => [
                    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A',
                    'currentDir' => dirname($_SERVER['REQUEST_URI'] ?? ''),
                    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'N/A'
                ]
            ]
        ];
        
        $logs[] = "Enviando resposta de sucesso para o frontend";
        error_log("[Umbrella] 📤 Enviando resposta ao frontend: " . json_encode($responseToFrontend));
        echo json_encode($responseToFrontend);
    } else {
        // Tratar erro
        $errorMessage = 'Erro ao processar pagamento';
        $errorDetails = '';
        
        if ($response) {
            $responseData = json_decode($response, true);
            $logs[] = "Resposta de erro decodificada: " . json_encode($responseData);
            
            if (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
                $logs[] = "Mensagem de erro da API: $errorMessage";
            }
            
            // Capturar detalhes do erro
            if (isset($responseData['details'])) {
                $errorDetails = is_array($responseData['details']) ? 
                    json_encode($responseData['details']) : 
                    $responseData['details'];
                $logs[] = "Detalhes do erro: $errorDetails";
            }
            
            // Capturar erros de validação
            if (isset($responseData['errors'])) {
                $validationErrors = is_array($responseData['errors']) ? 
                    json_encode($responseData['errors']) : 
                    $responseData['errors'];
                $logs[] = "Erros de validação: $validationErrors";
                $errorDetails = $validationErrors;
            }
            
            // Log completo da resposta de erro
            $logs[] = "Resposta completa de erro: " . print_r($responseData, true);
        }
        
        throw new Exception($errorMessage . ($errorDetails ? ": " . $errorDetails : ""));
    }
} catch (Exception $e) {
    $logs[] = "❌ Erro: " . $e->getMessage();
    $logs[] = "🔍 Stack trace: " . $e->getTraceAsString();
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar o PIX: ' . $e->getMessage(),
        'logs' => $logs
    ]);
}
?>