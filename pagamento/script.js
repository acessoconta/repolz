let currentTransactionId = null;
let paymentCheckInterval = null;
let countdownInterval = null;
const valorPix = 67.89;

function collectUTMParameters() {
  const p = new URLSearchParams(window.location.search);
  return {
    utm_source: p.get('utm_source') || '',
    utm_campaign: p.get('utm_campaign') || '',
    utm_medium: p.get('utm_medium') || '',
    utm_content: p.get('utm_content') || '',
    utm_term: p.get('utm_term') || '',
    fbclid: p.get('fbclid') || ''
  };
}

function getUserInfo() {
  try {
    const u = JSON.parse(localStorage.getItem('userInfo') || '{}');
    const nome = (u.nome || '').toString().trim();
    const cpf = (u.cpf || '').toString().replace(/\D/g, '');
    const email = (u.email || '').toString().trim();
    const telefone = (u.telefone || u.phone || '').toString().replace(/\D/g, '');
    return { nome, cpf, email, telefone };
  } catch {
    return { nome: '', cpf: '', email: '', telefone: '' };
  }
}

function setText(sel, value) {
  const el = document.querySelector(sel);
  if (el) el.textContent = value;
}

function showLoading(show) {
  const loading = document.getElementById('pix-loading');
  const content = document.getElementById('pix-content');
  if (loading) loading.style.display = show ? 'block' : 'none';
  if (content) content.style.display = show ? 'none' : 'block';
}

function setQrAndCode(qrCode, pixCode) {
  const img = document.querySelector('.qrcode-img');
  if (img) {
    img.src = qrCode
      ? qrCode
      : `https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=${encodeURIComponent(pixCode)}`;
  }
  setText('.pixcode', pixCode || '');
}

function startCountdown(seconds) {
  if (countdownInterval) clearInterval(countdownInterval);

  const tick = () => {
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    setText('.timer', `Expira em ${m}:${s}`);
    seconds -= 1;
    if (seconds < 0) clearInterval(countdownInterval);
  };

  tick();
  countdownInterval = setInterval(tick, 1000);
}

function attachCopy() {
  const btn = document.querySelector('.copy-btn');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const code = (document.querySelector('.pixcode')?.textContent || '').trim();
    if (!code) return;

    try {
      await navigator.clipboard.writeText(code);
      btn.textContent = 'Copiado';
      setTimeout(() => (btn.textContent = 'Copiar'), 1200);
    } catch {
      const ta = document.createElement('textarea');
      ta.value = code;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
      btn.textContent = 'Copiado';
      setTimeout(() => (btn.textContent = 'Copiar'), 1200);
    }
  });
}

async function gerarPix() {
  showLoading(true);

  try {
    const user = getUserInfo();
    const utmParams = collectUTMParameters();
    
    // Monta o payload conforme o backend espera
    const payload = {
      valor: Math.round(valorPix * 100), // Valor em centavos
      nome: user.nome || '',
      cpf: user.cpf || '',
      email: user.email || '',
      telefone: user.telefone || '',
      // Parâmetros UTM individuais
      utm_source: utmParams.utm_source || '',
      utm_campaign: utmParams.utm_campaign || '',
      utm_medium: utmParams.utm_medium || '',
      utm_content: utmParams.utm_content || '',
      utm_term: utmParams.utm_term || '',
      fbclid: utmParams.fbclid || ''
    };

    const apiUrl = getApiUrl('pagamento');
    console.log('[PIX] Enviando requisição para:', apiUrl);
    console.log('[PIX] Payload:', payload);

    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload)
    });

    console.log('[PIX] Response status:', response.status);
    console.log('[PIX] Response headers:', [...response.headers.entries()]);

    if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);

    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Erro ao gerar PIX');

    // Extrai dados conforme o backend retorna
    const pixCode = result.pixCopiaECola || result.pixCode || null;
    const txId = result.token || null;
    const qrCode = result.qrCodeUrl || null;

    if (!pixCode || !txId) throw new Error('Resposta inválida da API');

    currentTransactionId = txId;
    localStorage.setItem('currentTransactionId', currentTransactionId);

    setQrAndCode(qrCode, pixCode);
    setText('.valor-pix', `R$ ${valorPix.toFixed(2).replace('.', ',')}`);

    showLoading(false);

    startCountdown(15 * 60);
    iniciarVerificacaoAutomatica();
  } catch (e) {
    showLoading(false);
    alert('Erro ao gerar PIX: ' + (e?.message || e));
  }
}

async function verificarPagamento() {
  if (!currentTransactionId) currentTransactionId = localStorage.getItem('currentTransactionId');
  if (!currentTransactionId) return false;

  try {
    const response = await fetch(`${getApiUrl('verificar')}?id=${encodeURIComponent(currentTransactionId)}`, {
      method: 'GET',
      headers: { Accept: 'application/json' }
    });

    if (!response.ok) return false;

    const result = await response.json();
    const status = String(result?.status ?? '').toLowerCase();
    const isPaid = ['paid', 'approved', 'completed'].includes(status);

    if (isPaid) {
      clearInterval(paymentCheckInterval);
      paymentCheckInterval = null;
      setTimeout(() => {
        window.location.href = '../upsell1/' + window.location.search;
      }, 800);
      return true;
    }

    return false;
  } catch (e) {
    console.error('Erro ao verificar pagamento:', e);
    return false;
  }
}

function iniciarVerificacaoAutomatica() {
  if (paymentCheckInterval) clearInterval(paymentCheckInterval);
  paymentCheckInterval = setInterval(async () => {
    const ok = await verificarPagamento();
    if (ok && paymentCheckInterval) clearInterval(paymentCheckInterval);
  }, 5000);
}

document.addEventListener('DOMContentLoaded', () => {
  attachCopy();
  showLoading(true);
  const saved = localStorage.getItem('currentTransactionId');
  if (saved) currentTransactionId = saved;
  gerarPix();
});
