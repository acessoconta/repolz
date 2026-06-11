<?php
/**
 * CASHFLY - Criar Cobrança PIX
 * API: POST https://api.gw.cashflypay.com/api/v1/transactions
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ============================================================
// CONFIGURAÇÕES
// ============================================================
define('CASHFLY_API_KEY',    'live_LFwF7NoFaJyUxsGYoMPo489HGkrFpsD1');
define('CASHFLY_PUBLIC_KEY', 'b3be1ecc-9eed-45df-8bc3-b4bc23cb3981');
define('CASHFLY_BASE_URL',   'https://api.gw.cashflypay.com');
define('CASHFLY_USER_AGENT', 'MeuCheckout/1.0 (+suporte@seusite.com.br)');
// ============================================================

// ----------------------------------------------------------
// Helpers — fora do try para evitar erro de redeclaração
// quando incluído via require pelo pagamento.php
// ----------------------------------------------------------
$_cf_input = json_decode(file_get_contents('php://input'), true) ?? [];

function cf_getField(string $field, $default = null) {
    global $_cf_input;
    if (isset($_cf_input[$field]) && $_cf_input[$field] !== '') return $_cf_input[$field];
    if (isset($_POST[$field])     && $_POST[$field]     !== '') return $_POST[$field];
    if (isset($_GET[$field])      && $_GET[$field]      !== '') return $_GET[$field];
    return $default;
}

function cf_gerarCpf(): string {
    $invalidos = [];
    for ($d = 0; $d <= 9; $d++) {
        $invalidos[] = str_repeat((string)$d, 11);
    }
    do {
        $n = [];
        for ($i = 0; $i < 9; $i++) $n[] = rand(0, 9);

        $s1 = 0;
        for ($i = 0; $i < 9; $i++) $s1 += $n[$i] * (10 - $i);
        $r1 = $s1 % 11;
        $n[] = $r1 < 2 ? 0 : 11 - $r1;

        $s2 = 0;
        for ($i = 0; $i < 10; $i++) $s2 += $n[$i] * (11 - $i);
        $r2 = $s2 % 11;
        $n[] = $r2 < 2 ? 0 : 11 - $r2;

        $cpf = implode('', $n);
    } while (in_array($cpf, $invalidos));

    return $cpf;
}

// ----------------------------------------------------------
// Processamento principal
// ----------------------------------------------------------
try {

    // 1. Captura campos
    $valorCentavos = (int) cf_getField('valor', cf_getField('amount', 0));
    $nome          = cf_getField('nome') ?? cf_getField('name');
    $email         = cf_getField('email');
    $cpf           = cf_getField('cpf')  ?? cf_getField('document');
    $telefone      = cf_getField('telefone') ?? cf_getField('telephone') ?? cf_getField('phone', '11999999999');

    // Parâmetros UTM
    $utm = array_filter([
        'utm_source'   => cf_getField('utm_source'),
        'utm_medium'   => cf_getField('utm_medium'),
        'utm_campaign' => cf_getField('utm_campaign'),
        'utm_content'  => cf_getField('utm_content'),
        'utm_term'     => cf_getField('utm_term'),
        'utm_id'       => cf_getField('utm_id'),
        'xcod'         => cf_getField('xcod'),
        'sck'          => cf_getField('sck'),
        'src'          => cf_getField('src'),
    ]);

    // 2. Fallbacks
    if ($valorCentavos <= 0) {
        $valorCentavos = 6990;
        error_log("[CashFly] ⚠️ Valor não informado, usando padrão: {$valorCentavos}¢");
    }

    if (empty($nome)) {
        $nomes = ['João Silva', 'Maria Santos', 'Carlos Oliveira', 'Ana Souza', 'Pedro Alves'];
        $nome  = $nomes[array_rand($nomes)];
        error_log("[CashFly] ⚠️ Nome não informado, usando gerado: {$nome}");
    }

    if (!empty($cpf)) {
        $cpf = preg_replace('/\D/', '', $cpf);
    } else {
        $cpf = cf_gerarCpf();
        error_log("[CashFly] ⚠️ CPF não informado, usando gerado: {$cpf}");
    }

    if (empty($email)) {
        $slug  = strtolower(preg_replace('/\s+/', '.', trim($nome)));
        $email = $slug . '@email.com';
    }

    $telefone = preg_replace('/\D/', '', $telefone ?: '11999999999') ?: '11999999999';

    error_log("[CashFly] 📦 valor={$valorCentavos}¢ | nome={$nome} | cpf={$cpf}");

    // 3. Banco SQLite
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS pedidos (
        transaction_id TEXT PRIMARY KEY,
        status         TEXT NOT NULL DEFAULT 'pending',
        valor          INTEGER NOT NULL,
        nome           TEXT,
        email          TEXT,
        cpf            TEXT,
        utm_params     TEXT,
        created_at     TEXT,
        updated_at     TEXT
    )");

    // 4. Payload CashFly
    $proto      = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir        = dirname($_SERVER['REQUEST_URI'] ?? '/');
    $postbackUrl = "{$proto}://{$host}{$dir}/webhook-cashfly.php";
    $externalRef = 'order-' . uniqid();

    $payload = [
        'amount'        => $valorCentavos,
        'paymentMethod' => 'pix',
        'externalRef'   => $externalRef,
        'ip'            => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'postbackUrl'   => $postbackUrl,
        'customer'      => [
            'name'     => $nome,
            'email'    => $email,
            'phone'    => $telefone,
            'document' => ['number' => $cpf, 'type' => 'cpf'],
        ],
        'items' => [[
            'title'     => 'Produto',
            'unitPrice' => $valorCentavos,
            'quantity'  => 1,
            'tangible'  => false,
        ]],
        'pix'      => ['expiresInDays' => 1],
        'metadata' => (object) array_merge($utm, [
            'checkout_url' => "{$proto}://{$host}" . ($_SERVER['REQUEST_URI'] ?? '/'),
        ]),
    ];

    error_log("[CashFly] 📤 Payload: " . json_encode($payload));

    // 5. Chama API
    $auth = base64_encode(CASHFLY_API_KEY . ':' . CASHFLY_PUBLIC_KEY);
    $url  = CASHFLY_BASE_URL . '/api/v1/transactions';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
            'User-Agent: ' . CASHFLY_USER_AGENT,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new RuntimeException("Erro cURL: {$curlError}");
    }

    error_log("[CashFly] 📥 HTTP {$httpCode}: {$response}");

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException("API retornou HTTP {$httpCode}: {$response}");
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Resposta inválida da API: {$response}");
    }

    $transactionId = $result['id']              ?? null;
    $qrcode        = $result['pix']['qrcode']   ?? null;
    $expiresAt     = $result['pix']['expiresAt'] ?? null;

    if (!$transactionId) throw new RuntimeException("ID não encontrado na resposta.");
    if (!$qrcode)        throw new RuntimeException("QR Code não retornado pela API.");

    // 6. Salva no SQLite
    $stmt = $db->prepare("INSERT OR IGNORE INTO pedidos
        (transaction_id, status, valor, nome, email, cpf, utm_params, created_at)
        VALUES (:tid, 'pending', :valor, :nome, :email, :cpf, :utm, :created_at)");
    $stmt->execute([
        'tid'        => $transactionId,
        'valor'      => $valorCentavos,
        'nome'       => $nome,
        'email'      => $email,
        'cpf'        => $cpf,
        'utm'        => json_encode($utm),
        'created_at' => date('c'),
    ]);

    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['payment_id'] = $transactionId;

    error_log("[CashFly] ✅ Transação: {$transactionId}");

    // 7. Resposta ao frontend
    echo json_encode([
        'success'   => true,
        'token'     => $transactionId,
        'pixCode'   => $qrcode,
        'expiresAt' => $expiresAt,
        'qrCodeUrl' => 'https://api.qrserver.com/v1/create-qr-code/?data='
                       . urlencode($qrcode)
                       . '&size=300x300&format=png&ecc=L',
        'valor'     => $valorCentavos,
    ]);

} catch (Exception $e) {
    error_log("[CashFly] ❌ " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar pagamento: ' . $e->getMessage(),
    ]);
}