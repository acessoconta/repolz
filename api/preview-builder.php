<?php
/**
 * PREVIEW BUILDER - CHECKOUT CUSTOMIZER
 * Interface para personalização visual do checkout em tempo real
 */

// Verificar se está logado
session_start();
if (!isset($_SESSION['gateway_admin_logged'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acesso negado');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Builder - Checkout Customizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-surface-base: hsl(228 24% 7%);
            --color-surface-card: hsl(228 20% 11%);
            --color-surface-elevated: hsl(228 16% 16%);
            --color-accent: hsl(239 84% 67%);
            --color-accent-hover: hsl(239 84% 72%);
            --color-text-primary: hsl(220 14% 92%);
            --color-text-secondary: hsl(220 14% 85%);
            --color-text-muted: hsl(220 9% 50%);
            --color-border: hsl(228 16% 16%);
            --color-success: hsl(142 52% 44%);
            --color-danger: hsl(0 65% 51%);
            --bg-dark: hsl(228 24% 7%);
            --bg-card: hsl(228 20% 11%);
            --text-primary: hsl(220 14% 92%);
            --text-secondary: hsl(220 14% 85%);
            --border: hsl(228 16% 16%);
            --primary: hsl(239 84% 67%);
            --primary-dark: hsl(239 84% 60%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--color-surface-base);
            color: var(--color-text-primary);
            overflow: hidden;
        }

        .preview-container {
            display: grid;
            grid-template-columns: 240px 1fr 1fr;
            gap: 20px;
            height: 100vh;
            padding: 20px;
        }

        /* Sidebar de Widgets */
        .preview-sidebar {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .preview-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }

        .preview-sidebar-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .preview-sidebar-header p {
            margin: 8px 0 0 0;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .preview-widgets {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .widget-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }

        .widget-item:hover {
            background: var(--bg-card);
            color: var(--text-primary);
        }

        .widget-item.active {
            background: var(--primary);
            color: white;
        }

        .widget-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: var(--bg-card);
            transition: all 0.2s;
        }

        .widget-item.active .widget-icon {
            background: rgba(255, 255, 255, 0.2);
        }

        .widget-icon i {
            font-size: 16px;
        }

        .preview-sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
        }

        /* Área de Configuração */
        .preview-controls {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            overflow-y: auto;
        }

        .preview-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .preview-title {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .widget-config {
            display: none;
        }

        .widget-config.active {
            display: block;
        }

        .preview-form-group {
            margin-bottom: 20px;
        }

        .preview-form-group label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .preview-form-group input,
        .preview-form-group textarea {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-primary);
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .preview-form-group input:focus,
        .preview-form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.1);
        }

        /* Preview Iframe */
        .preview-iframe-container {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .preview-iframe-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-card);
        }

        .preview-iframe-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .preview-refresh {
            background: var(--primary);
            border: none;
            border-radius: 6px;
            color: white;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-refresh:hover {
            background: var(--primary-dark);
        }

        .preview-iframe {
            width: 100%;
            height: calc(100vh - 100px);
            border: none;
            background: white;
        }

        .preview-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .btn {
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* Upload de Imagem */
        .upload-container {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 12px;
        }

        .upload-container:hover {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.05);
        }

        .upload-input {
            display: none;
        }

        .upload-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 6px;
            margin: 10px auto;
            display: block;
        }

        .upload-text {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .image-option-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }

        .image-tab {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .image-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .image-tab:hover:not(.active) {
            border-color: var(--primary);
        }

        .alert {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: none;
            font-weight: 500;
            font-size: 14px;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #4a4a4a;
            transition: .3s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        .toggle-switch input:checked + .toggle-slider {
            background-color: var(--primary);
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }

        @media (max-width: 1200px) {
            .preview-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .preview-sidebar {
                display: none;
            }
            
            .preview-iframe-container {
                height: 600px;
            }
        }
    </style>
</head>
<body>

    <div id="alertBox" class="alert"></div>

    <div class="preview-container">
        <!-- Sidebar de Widgets -->
        <div class="preview-sidebar">
            <div class="preview-sidebar-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-cube" style="color: var(--primary);"></i>
                    <h3>Widgets</h3>
                </div>
                <p>Configure os elementos visuais do checkout</p>
            </div>

            <div class="preview-widgets">
                <div class="widget-item active" data-widget="geral" onclick="switchWidget('geral')">
                    <div class="widget-icon">
                        <i class="fa-solid fa-gear"></i>
                    </div>
                    <span>Geral</span>
                </div>

                <div class="widget-item" data-widget="contador" onclick="switchWidget('contador')">
                    <div class="widget-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <span>Contador</span>
                </div>

                <div class="widget-item" data-widget="banner" onclick="switchWidget('banner')">
                    <div class="widget-icon">
                        <i class="fa-solid fa-rectangle-ad"></i>
                    </div>
                    <span>Banner</span>
                </div>

                <div class="widget-item" data-widget="depoimentos" onclick="switchWidget('depoimentos')">
                    <div class="widget-icon">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <span>Depoimentos</span>
                </div>

                <div class="widget-item" data-widget="orderbumps" onclick="switchWidget('orderbumps')">
                    <div class="widget-icon">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <span>Orderbumps</span>
                </div>

                <div class="widget-item" data-widget="frete" onclick="switchWidget('frete')">
                    <div class="widget-icon">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                    <span>Frete</span>
                </div>

                <div class="widget-item" data-widget="cores" onclick="switchWidget('cores')">
                    <div class="widget-icon">
                        <i class="fa-solid fa-palette"></i>
                    </div>
                    <span>Cores</span>
                </div>
            </div>

            <div class="preview-sidebar-footer">
                <button class="btn btn-primary" onclick="savePreviewChanges()">
                    <i class="fa-solid fa-save"></i> Salvar Alterações
                </button>
            </div>
        </div>

        <!-- Área de Configuração -->
        <div class="preview-controls">
            <div class="preview-header">
                <h3 class="preview-title" id="widgetTitle">⚙️ Configurações Gerais</h3>
            </div>

            <!-- Widget Geral -->
            <div class="widget-config active" id="widget-geral">
                <!-- Produto Digital - Toggle estilizado -->
                <div style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%); border: 2px solid rgba(139, 92, 246, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 24px; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, transparent 70%); pointer-events: none;"></div>
                    
                    <div style="position: relative; z-index: 1;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <span style="color: var(--text-primary); font-weight: 600; font-size: 16px;">Produto Digital</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="preview_is_digital" onchange="updatePreview()">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <p style="color: var(--text-secondary); font-size: 13px; margin: 0; line-height: 1.5;">
                            Ative esta opção se o produto não requer entrega física (cursos, e-books, softwares, etc.)
                        </p>
                    </div>
                </div>

                <div class="preview-form-group">
                    <label for="preview_product_price">💰 Preço (R$)</label>
                    <input type="text" id="preview_product_price" placeholder="39.90" oninput="updatePreview()" onpaste="setTimeout(updatePreview, 10)">
                </div>

                <div class="preview-form-group">
                    <label for="preview_product_name">📦 Nome do Produto</label>
                    <input type="text" id="preview_product_name" placeholder="JBL PartyBox Stage 320BR" oninput="updatePreview()" onpaste="setTimeout(updatePreview, 10)">
                </div>

                <div class="preview-form-group">
                    <label for="preview_product_description">📝 Descrição</label>
                    <input type="text" id="preview_product_description" placeholder="A Rainha das Festas Chegou" oninput="updatePreview()" onpaste="setTimeout(updatePreview, 10)">
                </div>

                <div class="preview-form-group">
                    <label>🖼️ Imagem do Produto</label>
                    <div class="image-option">
                        <div class="image-option-tabs">
                            <div class="image-tab active" onclick="switchPreviewImageTab('product', 'upload')">📤 Upload</div>
                            <div class="image-tab" onclick="switchPreviewImageTab('product', 'url')">🔗 URL</div>
                        </div>
                        
                        <div id="preview_product_image_upload" class="upload-container" onclick="document.getElementById('preview_product_image_file').click()">
                            <div class="upload-text">
                                <i class="fa-solid fa-cloud-upload" style="font-size: 20px; margin-bottom: 6px; display: block;"></i>
                                Clique para selecionar<br>
                                <small>Mudança em tempo real!</small>
                            </div>
                            <input type="file" id="preview_product_image_file" class="upload-input" accept="image/*" onchange="handlePreviewImageUpload(this, 'product')">
                            <img id="preview_product_image_preview" class="upload-preview" style="display: none;">
                        </div>
                        
                        <div id="preview_product_image_url_container" style="display: none;">
                            <input type="url" id="preview_product_image" placeholder="https://exemplo.com/imagem.jpg" oninput="updatePreview()" onpaste="setTimeout(updatePreview, 10)">
                        </div>
                    </div>
                </div>

                <div class="preview-form-group">
                    <label for="preview_company_name">🏢 Nome da Empresa</label>
                    <input type="text" id="preview_company_name" placeholder="Compra Segura" oninput="updatePreview()" onpaste="setTimeout(updatePreview, 10)">
                </div>
            </div>

            <!-- Widget Orderbumps -->
            <div class="widget-config" id="widget-orderbumps">
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                    Configure as ofertas adicionais (orderbumps) que aparecem no checkout
                </p>
                
                <!-- Toggle estilizado -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: rgba(139, 92, 246, 0.1); border-radius: 8px; margin-bottom: 24px; border: 1px solid rgba(139, 92, 246, 0.2);">
                    <span style="font-size: 14px; font-weight: 600; color: var(--text-primary);">🎁 Ativar Orderbumps</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="preview_offers_visible" onchange="toggleOffersInputs(); updatePreview();">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <!-- Inputs de configuração (ocultos por padrão) -->
                <div id="preview_offers_container" style="display: none;">
                    <div class="preview-form-group">
                        <label for="preview_offers_count">📊 Quantidade de Ofertas (1-5)</label>
                        <input type="number" id="preview_offers_count" min="1" max="5" value="3" oninput="updateOffersCount()" style="width: 100px;">
                    </div>

                    <div id="preview_offers_list"></div>
                </div>
            </div>

            <!-- Widget Contador -->
            <div class="widget-config" id="widget-contador">
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                    Configure o contador regressivo que aparece no checkout
                </p>
                
                <!-- Toggle estilizado -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: rgba(139, 92, 246, 0.1); border-radius: 8px; margin-bottom: 24px; border: 1px solid rgba(139, 92, 246, 0.2);">
                    <span style="font-size: 14px; font-weight: 600; color: var(--text-primary);">⏰ Exibir contador</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="contador_enabled" onchange="toggleContadorInputs(); updatePreview();">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <!-- Inputs de configuração (ocultos por padrão) -->
                <div id="contador_inputs_container" style="display: none;">
                    <div class="preview-form-group">
                        <label for="contador_text">📝 Texto do contador</label>
                        <input type="text" id="contador_text" placeholder="Esta oferta expira em..." oninput="updatePreview()">
                    </div>

                    <div class="preview-form-group">
                        <label for="contador_text_expired">⚠️ Texto quando zerar</label>
                        <input type="text" id="contador_text_expired" placeholder="Oferta expirada!" oninput="updatePreview()">
                    </div>

                    <div class="preview-form-group">
                        <label for="contador_minutes">⏱️ Tempo (em minutos)</label>
                        <input type="number" id="contador_minutes" min="1" max="60" value="10" oninput="updatePreview()">
                        <p style="color: var(--text-muted); font-size: 12px; margin: 4px 0 0 0;">Recomendado: entre 5 e 30 minutos</p>
                    </div>

                    <div style="margin: 24px 0; padding: 16px 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
                        <h4 style="color: var(--text-primary); font-size: 14px; font-weight: 600; margin-bottom: 16px;">🎨 Personalização de cores</h4>
                        
                        <div class="preview-form-group">
                            <label for="contador_bg_color">Cor do fundo</label>
                            <input type="color" id="contador_bg_color" value="#FF1A1A" oninput="updatePreview()" style="width: 100%; height: 50px; cursor: pointer; border-radius: 8px;">
                        </div>

                        <div class="preview-form-group">
                            <label for="contador_text_color">Cor do texto</label>
                            <input type="color" id="contador_text_color" value="#000000" oninput="updatePreview()" style="width: 100%; height: 50px; cursor: pointer; border-radius: 8px;">
                        </div>
                    </div>

                    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 16px;">
                        <h4 style="color: var(--text-primary); font-size: 14px; font-weight: 600; margin-bottom: 12px;">👁️ Preview</h4>
                        <div id="contador_preview" style="background: #FF1A1A; color: #000000; padding: 12px 16px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; font-size: 14px; font-weight: 600;">
                            <span id="contador_preview_text">Texto do contador</span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-clock"></i>
                                <span>10:00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget Banner -->
            <div class="widget-config" id="widget-banner">
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                    Configure elementos visuais do header do checkout
                </p>
                
                <!-- Toggle estilizado para mostrar logo -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: rgba(139, 92, 246, 0.1); border-radius: 8px; margin-bottom: 24px; border: 1px solid rgba(139, 92, 246, 0.2);">
                    <span style="font-size: 14px; font-weight: 600; color: var(--text-primary);">🏪 Mostrar Logo da Empresa</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="preview_show_company_logo" onchange="toggleCompanyLogoInputs(); updatePreview();" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div id="company_logo_container" class="preview-form-group">
                    <label>🏪 Logo da Empresa (header)</label>
                    <div class="image-option">
                        <div class="image-option-tabs">
                            <div class="image-tab active" onclick="switchPreviewImageTab('logo', 'upload')">📤 Upload</div>
                            <div class="image-tab" onclick="switchPreviewImageTab('logo', 'url')">🔗 URL</div>
                        </div>
                        
                        <div id="preview_company_logo_upload" class="upload-container" onclick="document.getElementById('preview_company_logo_file').click()">
                            <div class="upload-text">
                                <i class="fa-solid fa-cloud-upload" style="font-size: 20px; margin-bottom: 6px; display: block;"></i>
                                Clique para selecionar<br>
                                <small>Mudança em tempo real!</small>
                            </div>
                            <input type="file" id="preview_company_logo_file" class="upload-input" accept="image/*" onchange="handlePreviewImageUpload(this, 'logo')">
                            <img id="preview_company_logo_preview" class="upload-preview" style="display: none;">
                        </div>
                        
                        <div id="preview_company_logo_url_container" style="display: none;">
                            <input type="url" id="preview_company_logo" placeholder="https://exemplo.com/logo.png" oninput="updatePreview()" onpaste="setTimeout(updatePreview, 10)">
                        </div>
                    </div>
                </div>

                <!-- Toggle estilizado para badge seguro -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: rgba(139, 92, 246, 0.1); border-radius: 8px; margin-bottom: 24px; border: 1px solid rgba(139, 92, 246, 0.2);">
                    <span style="font-size: 14px; font-weight: 600; color: var(--text-primary);">🛡️ Mostrar badge "Pagamento 100% Seguro"</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="preview_show_safe_badge" onchange="toggleSafeBadgeInputs(); updatePreview();" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div id="safe_badge_container" class="preview-form-group">
                    <label>🛡️ Imagem do Badge Seguro</label>
                    <div class="image-option">
                        <div class="image-option-tabs">
                            <div class="image-tab active" onclick="switchPreviewImageTab('safe_badge', 'upload')">📤 Upload</div>
                            <div class="image-tab" onclick="switchPreviewImageTab('safe_badge', 'url')">🔗 URL</div>
                        </div>
                        
                        <div id="preview_safe_badge_upload" class="upload-container" onclick="document.getElementById('preview_safe_badge_file').click()">
                            <div class="upload-text">
                                <i class="fa-solid fa-cloud-upload" style="font-size: 20px; margin-bottom: 6px; display: block;"></i>
                                Clique para selecionar
                            </div>
                            <input type="file" id="preview_safe_badge_file" class="upload-input" accept="image/*" onchange="handlePreviewImageUpload(this, 'safe_badge')">
                            <img id="preview_safe_badge_preview" class="upload-preview" style="display: none;">
                        </div>
                        
                        <div id="preview_safe_badge_url_container" style="display: none;">
                            <input type="url" id="preview_safe_badge" placeholder="https://exemplo.com/badge.svg" oninput="updatePreview()" onpaste="setTimeout(updatePreview, 10)">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget Depoimentos -->
            <div class="widget-config" id="widget-depoimentos">
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                    Mostre depoimentos de clientes satisfeitos
                </p>
                
                <!-- Toggle estilizado -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: rgba(139, 92, 246, 0.1); border-radius: 8px; margin-bottom: 24px; border: 1px solid rgba(139, 92, 246, 0.2);">
                    <span style="font-size: 14px; font-weight: 600; color: var(--text-primary);">⭐ Ativar Depoimentos</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="depoimentos_enabled" onchange="toggleDepoimentosInputs(); updatePreview();" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <!-- Inputs de configuração (ocultos por padrão) -->
                <div id="depoimentos_container" style="display: block;">
                    <div class="preview-form-group">
                        <label for="depoimentos_count">📊 Quantidade de Depoimentos (1-5)</label>
                        <input type="number" id="depoimentos_count" min="1" max="5" value="3" oninput="updateDepoimentosCount()" style="width: 100px;">
                    </div>

                    <div id="depoimentos_list"></div>
                </div>
            </div>

            <!-- Widget Frete -->
            <div class="widget-config" id="widget-frete">
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                    Configure as opções de frete do checkout
                </p>
                
                <div class="preview-form-group">
                    <label for="frete_opcoes_count">📊 Quantidade de Opções de Frete (1-5)</label>
                    <input type="number" id="frete_opcoes_count" min="1" max="5" value="3" oninput="updateFreteCount()" style="width: 100px;">
                </div>

                <div id="frete_opcoes_list"></div>
            </div>

            <!-- Widget Cores -->
            <div class="widget-config" id="widget-cores">
                <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                    Personalize as cores do checkout para combinar com sua marca
                </p>
                
                <div class="preview-form-group">
                    <label for="cor_principal">🎨 Cor Principal</label>
                    <p style="color: var(--text-muted); font-size: 12px; margin: 4px 0 8px 0;">Usada em botões, tabs ativas e elementos destacados</p>
                    <input type="color" id="cor_principal" value="#262626" oninput="updatePreview()" style="width: 100%; height: 50px; cursor: pointer; border-radius: 8px;">
                </div>

                <div class="preview-form-group">
                    <label for="cor_hover">🎨 Cor de Hover</label>
                    <p style="color: var(--text-muted); font-size: 12px; margin: 4px 0 8px 0;">Cor quando o mouse passa sobre botões e links</p>
                    <input type="color" id="cor_hover" value="#222222" oninput="updatePreview()" style="width: 100%; height: 50px; cursor: pointer; border-radius: 8px;">
                </div>

                <div class="preview-form-group">
                    <label for="cor_secundaria">🎨 Cor Secundária</label>
                    <p style="color: var(--text-muted); font-size: 12px; margin: 4px 0 8px 0;">Usada nos steps concluídos (com checkmark verde)</p>
                    <input type="color" id="cor_secundaria" value="#393939" oninput="updatePreview()" style="width: 100%; height: 50px; cursor: pointer; border-radius: 8px;">
                </div>

                <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-top: 20px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <i class="fa-solid fa-lightbulb" style="color: var(--primary);"></i>
                        <span style="color: var(--text-primary); font-weight: 600;">Dica</span>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">
                        As cores são aplicadas em tempo real no preview. Experimente diferentes combinações para encontrar a que melhor representa sua marca!
                    </p>
                </div>
            </div>
        </div>

        <!-- Preview Iframe -->
        <div class="preview-iframe-container">
            <div class="preview-iframe-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <h3>
                        <i class="fa-solid fa-eye"></i> Preview do Checkout
                    </h3>
                    <div style="background: rgba(16, 185, 129, 0.15); color: #34d399; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;">
                        🟢 LIVE PREVIEW
                    </div>
                </div>
                <button class="preview-refresh" onclick="refreshPreview()">
                    <i class="fa-solid fa-refresh"></i>
                    Recarregar
                </button>
            </div>
            <div style="position: absolute; top: 60px; right: 16px; z-index: 10;">
                <div id="previewStatus" style="background: rgba(16, 185, 129, 0.9); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: none;">
                    ⚡ ATUALIZANDO...
                </div>
            </div>
            <div class="preview-loading" id="previewLoading">
                <i class="fa-solid fa-spinner fa-spin"></i>
                Carregando preview...
            </div>
            <iframe id="previewIframe" class="preview-iframe" style="display: none;"></iframe>
        </div>
    </div>


    <script>
        let previewData = {};
        let previewUpdateTimeout = null;

        // Alternar entre widgets
        function switchWidget(widgetName) {
            // Atualizar sidebar
            document.querySelectorAll('.widget-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-widget="${widgetName}"]`).classList.add('active');
            
            // Atualizar área de configuração
            document.querySelectorAll('.widget-config').forEach(config => {
                config.classList.remove('active');
            });
            const targetConfig = document.getElementById(`widget-${widgetName}`);
            if (targetConfig) {
                targetConfig.classList.add('active');
            }
            
            // Atualizar título
            const titles = {
                'geral': '⚙️ Configurações Gerais',
                'contador': '⏰ Configurar Contador',
                'banner': '🎯 Configurar Banner',
                'depoimentos': '⭐ Configurar Depoimentos',
                'orderbumps': '🎁 Configurar Orderbumps',
                'frete': '🚚 Configurar Frete',
                'cores': '🎨 Personalizar Cores'
            };
            document.getElementById('widgetTitle').textContent = titles[widgetName] || 'Configurações';
        }

        // Inicializar preview
        function initializePreview() {
            loadPreviewData();
            setupPreviewIframe();
        }

        // Carregar dados atuais
        async function loadPreviewData() {
            try {
                const response = await fetch('config-controller.php?action=get_config');
                const data = await response.json();

                if (data.success && data.config) {
                    previewData = data.config;
                    
                    document.getElementById('preview_product_price').value = data.config.product_price || '';
                    document.getElementById('preview_product_name').value = data.config.product_name || '';
                    document.getElementById('preview_product_description').value = data.config.product_description || '';
                    document.getElementById('preview_product_image').value = data.config.product_image || '';
                    document.getElementById('preview_company_name').value = data.config.company_name || '';
                    document.getElementById('preview_company_logo').value = data.config.company_logo || '';
                    
                    // Carregar configuração do badge seguro
                    const showSafeBadgeCheckbox = document.getElementById('preview_show_safe_badge');
                    if (showSafeBadgeCheckbox) {
                        if (data.config.show_safe_badge !== undefined) {
                            showSafeBadgeCheckbox.checked = data.config.show_safe_badge === true;
                        } else {
                            showSafeBadgeCheckbox.checked = true; // Padrão: mostrar
                        }
                        // Atualizar visibilidade do container
                        setTimeout(() => {
                            toggleSafeBadgeInputs();
                        }, 100);
                        console.log('🛡️ Badge checkbox carregado:', showSafeBadgeCheckbox.checked);
                    }
                    
                    // Carregar configuração da logo da empresa
                    const showCompanyLogoCheckbox = document.getElementById('preview_show_company_logo');
                    if (showCompanyLogoCheckbox) {
                        if (data.config.show_company_logo !== undefined) {
                            showCompanyLogoCheckbox.checked = data.config.show_company_logo === true;
                        } else {
                            showCompanyLogoCheckbox.checked = true; // Padrão: mostrar
                        }
                        // Atualizar visibilidade do container
                        setTimeout(() => {
                            toggleCompanyLogoInputs();
                        }, 100);
                        console.log('🏪 Logo checkbox carregado:', showCompanyLogoCheckbox.checked);
                    }
                    
                    if (data.config.safe_badge_image) {
                        document.getElementById('preview_safe_badge').value = data.config.safe_badge_image;
                    }
                    
                    // Carregar configuração de produto digital
                    if (data.config.is_digital !== undefined) {
                        document.getElementById('preview_is_digital').checked = data.config.is_digital;
                    }
                    
                    // Carregar configuração de depoimentos
                    if (data.config.depoimentos_enabled !== undefined) {
                        document.getElementById('depoimentos_enabled').checked = data.config.depoimentos_enabled;
                    } else {
                        // Padrão: ativado
                        document.getElementById('depoimentos_enabled').checked = true;
                    }
                    
                    // Atualizar visibilidade do container de depoimentos
                    setTimeout(() => {
                        toggleDepoimentosInputs();
                    }, 100);
                    
                    // Carregar cores
                    if (data.config.colors) {
                        if (data.config.colors.principal) {
                            document.getElementById('cor_principal').value = data.config.colors.principal;
                        }
                        if (data.config.colors.hover) {
                            document.getElementById('cor_hover').value = data.config.colors.hover;
                        }
                        if (data.config.colors.secundaria) {
                            document.getElementById('cor_secundaria').value = data.config.colors.secundaria;
                        }
                    }
                    
                    // Carregar configuração de orderbumps
                    if (data.config.offers) {
                        const offersVisible = data.config.offers.visible || false;
                        const offersItems = data.config.offers.items || [];
                        
                        // Marcar checkbox de ativar orderbumps
                        const offersVisibleCheckbox = document.getElementById('preview_offers_visible');
                        if (offersVisibleCheckbox) {
                            offersVisibleCheckbox.checked = offersVisible;
                        }
                        
                        // Atualizar visibilidade do container de orderbumps
                        setTimeout(() => {
                            toggleOffersInputs();
                        }, 100);
                        
                        // Definir quantidade de ofertas
                        if (offersItems.length > 0) {
                            const offersCountInput = document.getElementById('preview_offers_count');
                            if (offersCountInput) {
                                offersCountInput.value = offersItems.length;
                            }
                            
                            // Aguardar criação dos campos
                            setTimeout(() => {
                                offersItems.forEach((offer, index) => {
                                    const i = index + 1;
                                    const nameInput = document.getElementById(`preview_offer_${i}_name`);
                                    const descInput = document.getElementById(`preview_offer_${i}_description`);
                                    const oldPriceInput = document.getElementById(`preview_offer_${i}_old_price`);
                                    const priceInput = document.getElementById(`preview_offer_${i}_price`);
                                    const imageInput = document.getElementById(`preview_offer_${i}_image`);
                                    const imagePreview = document.getElementById(`preview_offer_${i}_image_preview`);
                                    
                                    if (nameInput) nameInput.value = offer.name || '';
                                    if (descInput) descInput.value = offer.description || '';
                                    if (oldPriceInput) oldPriceInput.value = offer.oldPrice || '';
                                    if (priceInput) priceInput.value = offer.price || '';
                                    
                                    // Carregar imagem se existir
                                    if (offer.image) {
                                        if (imageInput) imageInput.value = offer.image;
                                        if (imagePreview) {
                                            imagePreview.src = offer.image;
                                            imagePreview.style.display = 'block';
                                        }
                                    }
                                });
                                
                                // Atualizar preview após carregar
                                updatePreview();
                            }, 600);
                        }
                    }
                    
                    // Carregar dados de frete se existirem
                    if (data.config.frete && data.config.frete.opcoes) {
                        const freteOpcoes = data.config.frete.opcoes;
                        document.getElementById('frete_opcoes_count').value = freteOpcoes.length;
                        
                        // Aguardar um pouco para garantir que os campos foram criados
                        setTimeout(() => {
                            freteOpcoes.forEach((frete, index) => {
                                const i = index + 1;
                                const nameInput = document.getElementById(`frete_${i}_name`);
                                const descInput = document.getElementById(`frete_${i}_description`);
                                const priceInput = document.getElementById(`frete_${i}_price`);
                                const destaqueCheck = document.getElementById(`frete_${i}_destaque`);
                                const selectedCheck = document.getElementById(`frete_${i}_selected`);
                                
                                if (nameInput) nameInput.value = frete.name || '';
                                if (descInput) descInput.value = frete.description || '';
                                if (priceInput) priceInput.value = frete.price || '';
                                if (destaqueCheck) destaqueCheck.checked = frete.destaque || false;
                                if (selectedCheck) selectedCheck.checked = frete.selected || false;
                            });
                        }, 600);
                    }
                    
                    // Carregar dados de depoimentos se existirem
                    if (data.config.depoimentos && data.config.depoimentos.length > 0) {
                        const depoimentos = data.config.depoimentos;
                        document.getElementById('depoimentos_count').value = depoimentos.length;
                        
                        // Aguardar um pouco para garantir que os campos foram criados
                        setTimeout(() => {
                            depoimentos.forEach((depoimento, index) => {
                                const i = index + 1;
                                const nameInput = document.getElementById(`depoimento_${i}_name`);
                                const descInput = document.getElementById(`depoimento_${i}_description`);
                                const imageInput = document.getElementById(`depoimento_${i}_image`);
                                const imagePreview = document.getElementById(`depoimento_${i}_image_preview`);
                                
                                if (nameInput) nameInput.value = depoimento.name || '';
                                if (descInput) descInput.value = depoimento.description || '';
                                
                                // Se tem imagem, carregar ela
                                if (depoimento.image && imageInput) {
                                    imageInput.value = depoimento.image;
                                    if (imagePreview) {
                                        imagePreview.src = depoimento.image;
                                        imagePreview.style.display = 'block';
                                    }
                                }
                            });
                            
                            // Atualizar preview após carregar
                            updatePreview();
                        }, 700);
                    }
                    
                    // Carregar configurações do contador
                    if (data.config.contador) {
                        const contador = data.config.contador;
                        
                        // Carregar checkbox de ativação
                        const contadorEnabled = document.getElementById('contador_enabled');
                        if (contadorEnabled) {
                            contadorEnabled.checked = contador.enabled || false;
                        }
                        
                        // Carregar texto do contador
                        const contadorText = document.getElementById('contador_text');
                        if (contadorText) {
                            contadorText.value = contador.text || 'Esta oferta expira em';
                        }
                        
                        // Carregar texto quando zerar
                        const contadorTextExpired = document.getElementById('contador_text_expired');
                        if (contadorTextExpired) {
                            contadorTextExpired.value = contador.text_expired || 'Oferta expirada!';
                        }
                        
                        // Carregar tempo em minutos
                        const contadorMinutes = document.getElementById('contador_minutes');
                        if (contadorMinutes) {
                            contadorMinutes.value = contador.minutes || 10;
                        }
                        
                        // Carregar cor de fundo
                        const contadorBgColor = document.getElementById('contador_bg_color');
                        if (contadorBgColor) {
                            contadorBgColor.value = contador.bg_color || '#FF1A1A';
                        }
                        
                        // Carregar cor do texto
                        const contadorTextColor = document.getElementById('contador_text_color');
                        if (contadorTextColor) {
                            contadorTextColor.value = contador.text_color || '#000000';
                        }
                        
                        // Mostrar/ocultar inputs baseado no estado do toggle
                        setTimeout(() => {
                            toggleContadorInputs();
                            updateContadorPreview();
                        }, 100);
                        
                        console.log('⏰ Configurações do contador carregadas:', contador);
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
            }
        }

        // Configurar iframe
        function setupPreviewIframe() {
            const iframe = document.getElementById('previewIframe');
            const loading = document.getElementById('previewLoading');
            
            // Adicionar timestamp para evitar cache, mas manter consistente durante a sessão
            const checkoutUrl = '../check/index.html?preview=1&t=' + Date.now();
            
            iframe.onload = function() {
                loading.style.display = 'none';
                iframe.style.display = 'block';
                
                // Aguardar um pouco para garantir que o JS do checkout carregou
                setTimeout(() => {
                    updatePreviewIframe();
                }, 500);
            };
            
            iframe.src = checkoutUrl;
        }

        // Atualizar preview em tempo real
        function updatePreview() {
            const statusIndicator = document.getElementById('previewStatus');
            const iframe = document.getElementById('previewIframe');
            
            if (statusIndicator) {
                statusIndicator.style.display = 'block';
            }
            
            if (iframe) {
                iframe.style.opacity = '0.9';
                iframe.style.transition = 'opacity 0.2s ease';
            }
            
            if (previewUpdateTimeout) {
                clearTimeout(previewUpdateTimeout);
            }
            
            previewUpdateTimeout = setTimeout(() => {
                updatePreviewIframe();
                
                setTimeout(() => {
                    if (statusIndicator) {
                        statusIndicator.style.display = 'none';
                    }
                    if (iframe) {
                        iframe.style.opacity = '1';
                    }
                }, 300);
            }, 100);
        }
        
        // Helper para obter a imagem do badge seguro (upload ou URL)
        function getSafeBadgeImage() {
            const uploadContainer = document.getElementById('preview_safe_badge_upload');
            const urlContainer = document.getElementById('preview_safe_badge_url_container');
            
            // Verificar se está no modo upload
            if (uploadContainer && uploadContainer.style.display !== 'none') {
                const previewImg = document.getElementById('preview_safe_badge_preview');
                if (previewImg && previewImg.style.display !== 'none' && previewImg.src) {
                    return previewImg.src;
                }
            }
            
            // Verificar se está no modo URL
            if (urlContainer && urlContainer.style.display !== 'none') {
                const urlInput = document.getElementById('preview_safe_badge');
                if (urlInput && urlInput.value) {
                    return urlInput.value;
                }
            }
            
            // Fallback: tentar pegar de qualquer um dos campos
            const urlInput = document.getElementById('preview_safe_badge');
            if (urlInput && urlInput.value) {
                return urlInput.value;
            }
            
            const previewImg = document.getElementById('preview_safe_badge_preview');
            if (previewImg && previewImg.src && previewImg.src !== window.location.href) {
                return previewImg.src;
            }
            
            return '';
        }

        // Atualizar iframe
        function updatePreviewIframe() {
            const iframe = document.getElementById('previewIframe');
            
            console.log('🔄 Atualizando iframe preview...');
            console.log('📦 Iframe:', iframe);
            console.log('📦 ContentWindow:', iframe?.contentWindow);
            console.log('📦 Iframe src:', iframe?.src);
            
            if (!iframe || !iframe.contentWindow) {
                console.error('❌ Iframe ou contentWindow não disponível');
                return;
            }
            
            // Verificar se o iframe está realmente carregado
            try {
                // Tentar acessar o document do iframe para verificar se está carregado
                const iframeDoc = iframe.contentWindow.document;
                if (!iframeDoc || iframeDoc.readyState !== 'complete') {
                    console.warn('⚠️ Iframe ainda não carregou completamente, aguardando...');
                    setTimeout(updatePreviewIframe, 500);
                    return;
                }
            } catch (e) {
                console.error('❌ Erro ao acessar iframe document:', e);
            }
            
            try {
                const newData = {
                    product_price: document.getElementById('preview_product_price').value || previewData.product_price,
                    product_name: document.getElementById('preview_product_name').value || previewData.product_name,
                    product_description: document.getElementById('preview_product_description').value || previewData.product_description,
                    product_image: document.getElementById('preview_product_image').value || previewData.product_image,
                    company_name: document.getElementById('preview_company_name').value || previewData.company_name,
                    company_logo: document.getElementById('preview_company_logo').value || previewData.company_logo,
                    is_digital: document.getElementById('preview_is_digital')?.checked || false,
                    depoimentos_enabled: document.getElementById('depoimentos_enabled')?.checked !== false,
                    show_safe_badge: document.getElementById('preview_show_safe_badge')?.checked === true,
                    safe_badge_image: getSafeBadgeImage(),
                    show_company_logo: document.getElementById('preview_show_company_logo')?.checked === true,
                    colors: {
                        principal: document.getElementById('cor_principal')?.value || '#262626',
                        hover: document.getElementById('cor_hover')?.value || '#222222',
                        secundaria: document.getElementById('cor_secundaria')?.value || '#393939'
                    }
                };
                
                console.log('📤 DEBUG - Enviando dados para iframe:', {
                    show_safe_badge: newData.show_safe_badge,
                    show_safe_badge_type: typeof newData.show_safe_badge,
                    safe_badge_image: newData.safe_badge_image,
                    checkbox_checked: document.getElementById('preview_show_safe_badge')?.checked
                });
                console.log('📤 Dados completos:', newData);
                
                // Coletar dados das ofertas
                const offersVisible = document.getElementById('preview_offers_visible')?.checked || false;
                const offersCount = parseInt(document.getElementById('preview_offers_count')?.value) || 0;
                const offers = [];
                
                if (offersVisible && offersCount > 0) {
                    for (let i = 1; i <= offersCount; i++) {
                        const name = document.getElementById(`preview_offer_${i}_name`)?.value || '';
                        const description = document.getElementById(`preview_offer_${i}_description`)?.value || '';
                        const oldPrice = document.getElementById(`preview_offer_${i}_old_price`)?.value || '';
                        const price = document.getElementById(`preview_offer_${i}_price`)?.value || '';
                        
                        const uploadContainer = document.getElementById(`preview_offer_${i}_image_upload`);
                        const urlContainer = document.getElementById(`preview_offer_${i}_image_url_container`);
                        let image = '';
                        
                        if (uploadContainer && uploadContainer.style.display !== 'none') {
                            const previewImg = document.getElementById(`preview_offer_${i}_image_preview`);
                            if (previewImg && previewImg.style.display !== 'none') {
                                image = previewImg.src;
                            }
                        } else if (urlContainer && urlContainer.style.display !== 'none') {
                            image = document.getElementById(`preview_offer_${i}_image`)?.value || '';
                        }
                        
                        if (name || description || price) {
                            offers.push({
                                name: name,
                                description: description,
                                oldPrice: oldPrice,
                                price: price,
                                image: image
                            });
                        }
                    }
                }
                
                newData.offers = {
                    visible: offersVisible,
                    items: offers
                };
                
                // Coletar dados das opções de frete
                const freteCount = parseInt(document.getElementById('frete_opcoes_count')?.value) || 0;
                const freteOpcoes = [];
                
                if (freteCount > 0) {
                    for (let i = 1; i <= freteCount; i++) {
                        const name = document.getElementById(`frete_${i}_name`)?.value || '';
                        const description = document.getElementById(`frete_${i}_description`)?.value || '';
                        const price = document.getElementById(`frete_${i}_price`)?.value || '';
                        const destaque = document.getElementById(`frete_${i}_destaque`)?.checked || false;
                        const selected = document.getElementById(`frete_${i}_selected`)?.checked || false;
                        
                        if (name || description || price) {
                            freteOpcoes.push({
                                name: name,
                                description: description,
                                price: price,
                                destaque: destaque,
                                selected: selected
                            });
                        }
                    }
                }
                
                newData.frete = {
                    opcoes: freteOpcoes
                };
                
                // Coletar dados dos depoimentos
                const depoimentosCount = parseInt(document.getElementById('depoimentos_count')?.value) || 0;
                const depoimentos = [];
                
                if (depoimentosCount > 0) {
                    for (let i = 1; i <= depoimentosCount; i++) {
                        const name = document.getElementById(`depoimento_${i}_name`)?.value || '';
                        const description = document.getElementById(`depoimento_${i}_description`)?.value || '';
                        
                        const uploadContainer = document.getElementById(`depoimento_${i}_image_upload`);
                        const urlContainer = document.getElementById(`depoimento_${i}_image_url_container`);
                        let image = '';
                        
                        if (uploadContainer && uploadContainer.style.display !== 'none') {
                            const previewImg = document.getElementById(`depoimento_${i}_image_preview`);
                            if (previewImg && previewImg.style.display !== 'none') {
                                image = previewImg.src;
                            }
                        } else if (urlContainer && urlContainer.style.display !== 'none') {
                            image = document.getElementById(`depoimento_${i}_image`)?.value || '';
                        }
                        
                        if (name || description) {
                            depoimentos.push({
                                name: name,
                                description: description,
                                image: image
                            });
                        }
                    }
                }
                
                newData.depoimentos = depoimentos;
                
                // Coletar dados do contador
                newData.contador = {
                    enabled: document.getElementById('contador_enabled')?.checked || false,
                    text: document.getElementById('contador_text')?.value || 'Esta oferta expira em',
                    text_expired: document.getElementById('contador_text_expired')?.value || 'Oferta expirada!',
                    minutes: parseInt(document.getElementById('contador_minutes')?.value) || 10,
                    bg_color: document.getElementById('contador_bg_color')?.value || '#FF1A1A',
                    text_color: document.getElementById('contador_text_color')?.value || '#000000'
                };
                
                console.log('📤 Enviando postMessage para iframe...');
                console.log('📤 Tipo:', 'updatePreview');
                console.log('📤 Dados:', newData);
                
                iframe.contentWindow.postMessage({
                    type: 'updatePreview',
                    data: newData
                }, '*');
                
                console.log('✅ PostMessage enviado com sucesso!');
                
            } catch (error) {
                console.error('❌ Erro ao atualizar preview:', error);
                console.error('❌ Stack:', error.stack);
            }
        }

        // Refresh do preview
        function refreshPreview() {
            const iframe = document.getElementById('previewIframe');
            const loading = document.getElementById('previewLoading');
            
            loading.style.display = 'flex';
            iframe.style.display = 'none';
            
            iframe.src = iframe.src;
        }

        // Salvar alterações
        async function savePreviewChanges() {
            const saveButton = event.target;
            const originalText = saveButton.innerHTML;
            
            saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';
            saveButton.disabled = true;
            
            const price = document.getElementById('preview_product_price').value;
            const name = document.getElementById('preview_product_name').value;
            const description = document.getElementById('preview_product_description').value;
            const image = document.getElementById('preview_product_image').value;
            const companyName = document.getElementById('preview_company_name').value;
            const companyLogo = document.getElementById('preview_company_logo').value;
            const isDigital = document.getElementById('preview_is_digital')?.checked || false;
            const depoimentosEnabled = document.getElementById('depoimentos_enabled')?.checked !== false;
            const showSafeBadge = document.getElementById('preview_show_safe_badge')?.checked === true;
            const safeBadgeImage = getSafeBadgeImage();
            const showCompanyLogo = document.getElementById('preview_show_company_logo')?.checked === true;
            
            console.log('💾 Salvando badge config:', { showSafeBadge, safeBadgeImage });
            console.log('💾 Salvando logo config:', { showCompanyLogo });
            console.log('💾 Tipo do showCompanyLogo:', typeof showCompanyLogo);
            
            // Coletar cores
            const colors = {
                principal: document.getElementById('cor_principal')?.value || '#262626',
                hover: document.getElementById('cor_hover')?.value || '#222222',
                secundaria: document.getElementById('cor_secundaria')?.value || '#393939'
            };
            
            // Coletar dados das ofertas
            const offersVisible = document.getElementById('preview_offers_visible')?.checked || false;
            const offersCount = parseInt(document.getElementById('preview_offers_count')?.value) || 0;
            const offers = [];
            
            if (offersVisible && offersCount > 0) {
                for (let i = 1; i <= offersCount; i++) {
                    const name = document.getElementById(`preview_offer_${i}_name`)?.value || '';
                    const description = document.getElementById(`preview_offer_${i}_description`)?.value || '';
                    const oldPrice = document.getElementById(`preview_offer_${i}_old_price`)?.value || '';
                    const price = document.getElementById(`preview_offer_${i}_price`)?.value || '';
                    
                    const uploadContainer = document.getElementById(`preview_offer_${i}_image_upload`);
                    const urlContainer = document.getElementById(`preview_offer_${i}_image_url_container`);
                    let image = '';
                    
                    if (uploadContainer && uploadContainer.style.display !== 'none') {
                        const previewImg = document.getElementById(`preview_offer_${i}_image_preview`);
                        if (previewImg && previewImg.style.display !== 'none') {
                            image = previewImg.src;
                        }
                    } else if (urlContainer && urlContainer.style.display !== 'none') {
                        image = document.getElementById(`preview_offer_${i}_image`)?.value || '';
                    }
                    
                    if (name || description || price) {
                        offers.push({
                            name: name,
                            description: description,
                            oldPrice: oldPrice,
                            price: price,
                            image: image
                        });
                    }
                }
            }
            
            const offersData = {
                visible: offersVisible,
                items: offers
            };
            
            // Coletar dados das opções de frete
            const freteCount = parseInt(document.getElementById('frete_opcoes_count')?.value) || 0;
            const freteOpcoes = [];
            
            if (freteCount > 0) {
                for (let i = 1; i <= freteCount; i++) {
                    const name = document.getElementById(`frete_${i}_name`)?.value || '';
                    const description = document.getElementById(`frete_${i}_description`)?.value || '';
                    const price = document.getElementById(`frete_${i}_price`)?.value || '';
                    const destaque = document.getElementById(`frete_${i}_destaque`)?.checked || false;
                    const selected = document.getElementById(`frete_${i}_selected`)?.checked || false;
                    
                    if (name || description || price) {
                        freteOpcoes.push({
                            name: name,
                            description: description,
                            price: price,
                            destaque: destaque,
                            selected: selected
                        });
                    }
                }
            }
            
            const freteData = {
                opcoes: freteOpcoes
            };
            
            // Coletar dados dos depoimentos
            const depoimentosCount = parseInt(document.getElementById('depoimentos_count')?.value) || 0;
            const depoimentos = [];
            
            if (depoimentosCount > 0) {
                for (let i = 1; i <= depoimentosCount; i++) {
                    const name = document.getElementById(`depoimento_${i}_name`)?.value || '';
                    const description = document.getElementById(`depoimento_${i}_description`)?.value || '';
                    
                    const uploadContainer = document.getElementById(`depoimento_${i}_image_upload`);
                    const urlContainer = document.getElementById(`depoimento_${i}_image_url_container`);
                    let image = '';
                    
                    if (uploadContainer && uploadContainer.style.display !== 'none') {
                        const previewImg = document.getElementById(`depoimento_${i}_image_preview`);
                        if (previewImg && previewImg.style.display !== 'none') {
                            image = previewImg.src;
                        }
                    } else if (urlContainer && urlContainer.style.display !== 'none') {
                        image = document.getElementById(`depoimento_${i}_image`)?.value || '';
                    }
                    
                    if (name || description) {
                        depoimentos.push({
                            name: name,
                            description: description,
                            image: image
                        });
                    }
                }
            }

            try {
                const response = await fetch('config-controller.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=update_config&price=${encodeURIComponent(price)}&name=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}&image=${encodeURIComponent(image)}&company_name=${encodeURIComponent(companyName)}&company_logo=${encodeURIComponent(companyLogo)}&is_digital=${encodeURIComponent(isDigital)}&depoimentos_enabled=${encodeURIComponent(depoimentosEnabled)}&show_safe_badge=${encodeURIComponent(showSafeBadge)}&safe_badge_image=${encodeURIComponent(safeBadgeImage)}&show_company_logo=${encodeURIComponent(showCompanyLogo)}&offers=${encodeURIComponent(JSON.stringify(offersData))}&frete=${encodeURIComponent(JSON.stringify(freteData))}&depoimentos=${encodeURIComponent(JSON.stringify(depoimentos))}&colors=${encodeURIComponent(JSON.stringify(colors))}&contador=${encodeURIComponent(JSON.stringify({
                        enabled: document.getElementById('contador_enabled')?.checked || false,
                        text: document.getElementById('contador_text')?.value || 'Esta oferta expira em',
                        text_expired: document.getElementById('contador_text_expired')?.value || 'Oferta expirada!',
                        minutes: parseInt(document.getElementById('contador_minutes')?.value) || 10,
                        bg_color: document.getElementById('contador_bg_color')?.value || '#FF1A1A',
                        text_color: document.getElementById('contador_text_color')?.value || '#000000'
                    }))}`
                });

                const data = await response.json();

                if (data.success) {
                    saveButton.innerHTML = '<i class="fa-solid fa-check"></i> ✅ Salvo!';
                    saveButton.style.background = 'var(--color-success)';
                    
                    showAlert('🎉 Configurações salvas com sucesso!', 'success');
                    
                    previewData = data.config;
                    
                    setTimeout(() => {
                        saveButton.innerHTML = originalText;
                        saveButton.style.background = 'var(--primary)';
                        saveButton.disabled = false;
                    }, 3000);
                    
                } else {
                    saveButton.innerHTML = originalText;
                    saveButton.disabled = false;
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
                showAlert('Erro ao salvar configurações', 'error');
            }
        }

        // Alternar entre upload e URL
        function switchPreviewImageTab(type, mode) {
            let uploadContainerId, urlContainerId;
            
            if (type === 'product') {
                uploadContainerId = 'preview_product_image_upload';
                urlContainerId = 'preview_product_image_url_container';
            } else if (type === 'logo') {
                uploadContainerId = 'preview_company_logo_upload';
                urlContainerId = 'preview_company_logo_url_container';
            } else if (type.startsWith('offer_')) {
                uploadContainerId = `preview_${type}_image_upload`;
                urlContainerId = `preview_${type}_image_url_container`;
            } else if (type === 'safe_badge') {
                uploadContainerId = 'preview_safe_badge_upload';
                urlContainerId = 'preview_safe_badge_url_container';
            } else if (type.startsWith('depoimento_')) {
                uploadContainerId = `${type}_image_upload`;
                urlContainerId = `${type}_image_url_container`;
            }
            
            const uploadContainer = document.getElementById(uploadContainerId);
            const urlContainer = document.getElementById(urlContainerId);
            const tabs = event.target.parentElement.querySelectorAll('.image-tab');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            if (mode === 'upload') {
                if (uploadContainer) uploadContainer.style.display = 'block';
                if (urlContainer) urlContainer.style.display = 'none';
            } else {
                if (uploadContainer) uploadContainer.style.display = 'none';
                if (urlContainer) urlContainer.style.display = 'block';
            }
        }

        // Upload de imagem
        async function handlePreviewImageUpload(input, type) {
            const file = input.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                showAlert('Por favor, selecione apenas arquivos de imagem', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showAlert('Arquivo muito grande. Máximo: 5MB', 'error');
                return;
            }

            let previewId, urlInputId;
            
            if (type === 'product') {
                previewId = 'preview_product_image_preview';
                urlInputId = 'preview_product_image';
            } else if (type === 'logo') {
                previewId = 'preview_company_logo_preview';
                urlInputId = 'preview_company_logo';
            } else if (type.startsWith('offer_')) {
                previewId = `preview_${type}_image_preview`;
                urlInputId = `preview_${type}_image`;
            } else if (type === 'safe_badge') {
                previewId = 'preview_safe_badge_preview';
                urlInputId = 'preview_safe_badge';
            } else if (type.startsWith('depoimento_')) {
                previewId = `${type}_image_preview`;
                urlInputId = `${type}_image`;
            }

            const preview = document.getElementById(previewId);
            const reader = new FileReader();
            reader.onload = function(e) {
                if (preview) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                updatePreview();
            };
            reader.readAsDataURL(file);

            const formData = new FormData();
            formData.append('image', file);

            try {
                const response = await fetch('upload-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const urlInput = document.getElementById(urlInputId);
                    if (urlInput) {
                        urlInput.value = data.url;
                    }
                    
                    setTimeout(() => {
                        updatePreview();
                    }, 500);
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                console.error('Erro no upload:', error);
                showAlert('Erro ao fazer upload da imagem', 'error');
            }
        }

        // Atualizar quantidade de opções de frete
        function updateFreteCount() {
            const count = parseInt(document.getElementById('frete_opcoes_count').value) || 3;
            const freteList = document.getElementById('frete_opcoes_list');
            
            if (!freteList) return;
            
            // Salvar dados existentes antes de recriar
            const savedData = [];
            for (let i = 1; i <= 10; i++) { // Verificar até 10 possíveis opções
                const nameInput = document.getElementById(`frete_${i}_name`);
                const descInput = document.getElementById(`frete_${i}_description`);
                const priceInput = document.getElementById(`frete_${i}_price`);
                const destaqueCheck = document.getElementById(`frete_${i}_destaque`);
                const selectedCheck = document.getElementById(`frete_${i}_selected`);
                
                if (nameInput || descInput || priceInput) {
                    savedData[i] = {
                        name: nameInput?.value || '',
                        description: descInput?.value || '',
                        price: priceInput?.value || '',
                        destaque: destaqueCheck?.checked || false,
                        selected: selectedCheck?.checked || false
                    };
                }
            }
            
            freteList.innerHTML = '';
            
            for (let i = 1; i <= count; i++) {
                const freteHTML = `
                    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                            <span style="color: var(--primary); font-weight: 600;">🚚 Opção de Frete ${i}</span>
                        </div>
                        
                        <div class="preview-form-group">
                            <label for="frete_${i}_name">📝 Nome do Frete</label>
                            <input type="text" id="frete_${i}_name" placeholder="Ex: PAC Correios" oninput="updatePreview()">
                        </div>
                        
                        <div class="preview-form-group">
                            <label for="frete_${i}_description">💬 Descrição</label>
                            <input type="text" id="frete_${i}_description" placeholder="Ex: 4 a 12 dias" oninput="updatePreview()">
                        </div>
                        
                        <div class="preview-form-group">
                            <label for="frete_${i}_price">💰 Valor (R$)</label>
                            <input type="text" id="frete_${i}_price" placeholder="14,64" oninput="updatePreview()">
                        </div>
                        
                        <div class="preview-form-group">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="frete_${i}_destaque" onchange="updatePreview()" style="width: 18px; height: 18px; cursor: pointer;">
                                <span>⭐ Destacar esta opção</span>
                            </label>
                        </div>
                        
                        <div class="preview-form-group">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="frete_${i}_selected" onchange="handleFreteSelectedChange(${i})" style="width: 18px; height: 18px; cursor: pointer;">
                                <span>✅ Selecionar por padrão</span>
                            </label>
                        </div>
                    </div>
                `;
                
                freteList.innerHTML += freteHTML;
            }
            
            // Restaurar dados salvos
            for (let i = 1; i <= count; i++) {
                if (savedData[i]) {
                    const nameInput = document.getElementById(`frete_${i}_name`);
                    const descInput = document.getElementById(`frete_${i}_description`);
                    const priceInput = document.getElementById(`frete_${i}_price`);
                    const destaqueCheck = document.getElementById(`frete_${i}_destaque`);
                    const selectedCheck = document.getElementById(`frete_${i}_selected`);
                    
                    if (nameInput) nameInput.value = savedData[i].name;
                    if (descInput) descInput.value = savedData[i].description;
                    if (priceInput) priceInput.value = savedData[i].price;
                    if (destaqueCheck) destaqueCheck.checked = savedData[i].destaque;
                    if (selectedCheck) selectedCheck.checked = savedData[i].selected;
                }
            }
            
            updatePreview();
        }
        
        // Função para garantir que apenas uma opção de frete seja selecionada por padrão
        function handleFreteSelectedChange(selectedIndex) {
            const count = parseInt(document.getElementById('frete_opcoes_count').value) || 3;
            const selectedCheckbox = document.getElementById(`frete_${selectedIndex}_selected`);
            
            // Se o checkbox foi marcado, desmarcar todos os outros
            if (selectedCheckbox && selectedCheckbox.checked) {
                for (let i = 1; i <= count; i++) {
                    if (i !== selectedIndex) {
                        const otherCheckbox = document.getElementById(`frete_${i}_selected`);
                        if (otherCheckbox) {
                            otherCheckbox.checked = false;
                        }
                    }
                }
            }
            
            updatePreview();
        }
        
        // Atualizar quantidade de ofertas
        function updateOffersCount() {
            const count = parseInt(document.getElementById('preview_offers_count').value) || 3;
            const offersList = document.getElementById('preview_offers_list');
            
            if (!offersList) return;
            
            // Salvar dados existentes antes de recriar
            const savedData = [];
            for (let i = 1; i <= 10; i++) { // Verificar até 10 possíveis ofertas
                const nameInput = document.getElementById(`preview_offer_${i}_name`);
                const descInput = document.getElementById(`preview_offer_${i}_description`);
                const oldPriceInput = document.getElementById(`preview_offer_${i}_old_price`);
                const priceInput = document.getElementById(`preview_offer_${i}_price`);
                const imageInput = document.getElementById(`preview_offer_${i}_image`);
                const imagePreview = document.getElementById(`preview_offer_${i}_image_preview`);
                
                if (nameInput || descInput || priceInput) {
                    savedData[i] = {
                        name: nameInput?.value || '',
                        description: descInput?.value || '',
                        oldPrice: oldPriceInput?.value || '',
                        price: priceInput?.value || '',
                        image: imageInput?.value || '',
                        imageSrc: (imagePreview && imagePreview.style.display !== 'none') ? imagePreview.src : ''
                    };
                }
            }
            
            offersList.innerHTML = '';
            
            for (let i = 1; i <= count; i++) {
                const offerHTML = `
                    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                            <span style="color: var(--primary); font-weight: 600;">🎁 Oferta ${i}</span>
                        </div>
                        
                        <div class="preview-form-group">
                            <label for="preview_offer_${i}_name">📝 Nome da Oferta</label>
                            <input type="text" id="preview_offer_${i}_name" placeholder="Ex: JBL Battery 400" oninput="updatePreview()">
                        </div>
                        
                        <div class="preview-form-group">
                            <label for="preview_offer_${i}_description">💬 Descrição</label>
                            <input type="text" id="preview_offer_${i}_description" placeholder="Ex: Aproveite esta oferta especial" oninput="updatePreview()">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="preview-form-group">
                                <label for="preview_offer_${i}_old_price">💵 Preço Antigo (R$)</label>
                                <input type="text" id="preview_offer_${i}_old_price" placeholder="35,80" oninput="updatePreview()">
                            </div>
                            
                            <div class="preview-form-group">
                                <label for="preview_offer_${i}_price">💰 Preço Novo (R$)</label>
                                <input type="text" id="preview_offer_${i}_price" placeholder="17,90" oninput="updatePreview()">
                            </div>
                        </div>
                        
                        <div class="preview-form-group">
                            <label>🖼️ Imagem da Oferta</label>
                            <div class="image-option">
                                <div class="image-option-tabs">
                                    <div class="image-tab active" onclick="switchPreviewImageTab('offer_${i}', 'upload')">📤 Upload</div>
                                    <div class="image-tab" onclick="switchPreviewImageTab('offer_${i}', 'url')">🔗 URL</div>
                                </div>
                                
                                <div id="preview_offer_${i}_image_upload" class="upload-container" onclick="document.getElementById('preview_offer_${i}_image_file').click()">
                                    <div class="upload-text">
                                        <i class="fa-solid fa-cloud-upload" style="font-size: 20px; margin-bottom: 6px; display: block;"></i>
                                        Clique para selecionar
                                    </div>
                                    <input type="file" id="preview_offer_${i}_image_file" class="upload-input" accept="image/*" onchange="handlePreviewImageUpload(this, 'offer_${i}')">
                                    <img id="preview_offer_${i}_image_preview" class="upload-preview" style="display: none;">
                                </div>
                                
                                <div id="preview_offer_${i}_image_url_container" style="display: none;">
                                    <input type="url" id="preview_offer_${i}_image" placeholder="https://exemplo.com/imagem.jpg" oninput="updatePreview()">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                offersList.innerHTML += offerHTML;
            }
            
            // Restaurar dados salvos
            for (let i = 1; i <= count; i++) {
                if (savedData[i]) {
                    const nameInput = document.getElementById(`preview_offer_${i}_name`);
                    const descInput = document.getElementById(`preview_offer_${i}_description`);
                    const oldPriceInput = document.getElementById(`preview_offer_${i}_old_price`);
                    const priceInput = document.getElementById(`preview_offer_${i}_price`);
                    const imageInput = document.getElementById(`preview_offer_${i}_image`);
                    const imagePreview = document.getElementById(`preview_offer_${i}_image_preview`);
                    
                    if (nameInput) nameInput.value = savedData[i].name;
                    if (descInput) descInput.value = savedData[i].description;
                    if (oldPriceInput) oldPriceInput.value = savedData[i].oldPrice;
                    if (priceInput) priceInput.value = savedData[i].price;
                    if (imageInput) imageInput.value = savedData[i].image;
                    
                    if (imagePreview && savedData[i].imageSrc) {
                        imagePreview.src = savedData[i].imageSrc;
                        imagePreview.style.display = 'block';
                    }
                }
            }
            
            updatePreview();
        }

        // Atualizar quantidade de depoimentos
        function updateDepoimentosCount() {
            const count = parseInt(document.getElementById('depoimentos_count').value) || 3;
            const depoimentosList = document.getElementById('depoimentos_list');
            
            if (!depoimentosList) return;
            
            // Salvar dados existentes antes de recriar
            const savedData = [];
            for (let i = 1; i <= 10; i++) { // Verificar até 10 possíveis depoimentos
                const nameInput = document.getElementById(`depoimento_${i}_name`);
                const descInput = document.getElementById(`depoimento_${i}_description`);
                const imageInput = document.getElementById(`depoimento_${i}_image`);
                const imagePreview = document.getElementById(`depoimento_${i}_image_preview`);
                
                if (nameInput || descInput) {
                    savedData[i] = {
                        name: nameInput?.value || '',
                        description: descInput?.value || '',
                        image: imageInput?.value || '',
                        imageSrc: (imagePreview && imagePreview.style.display !== 'none') ? imagePreview.src : ''
                    };
                }
            }
            
            depoimentosList.innerHTML = '';
            
            for (let i = 1; i <= count; i++) {
                const depoimentoHTML = `
                    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">
                            <span style="color: var(--primary); font-weight: 600;">⭐ Depoimento ${i}</span>
                        </div>
                        
                        <div class="preview-form-group">
                            <label for="depoimento_${i}_name">👤 Nome/Título</label>
                            <input type="text" id="depoimento_${i}_name" placeholder="Ex: Correios" oninput="updatePreview()">
                        </div>
                        
                        <div class="preview-form-group">
                            <label for="depoimento_${i}_description">💬 Descrição do Depoimento</label>
                            <textarea id="depoimento_${i}_description" rows="3" placeholder="Ex: Você receberá o código de rastreio via e-mail..." oninput="updatePreview()"></textarea>
                        </div>
                        
                        <div class="preview-form-group">
                            <label>🖼️ Foto do Depoimento</label>
                            <div class="image-option">
                                <div class="image-option-tabs">
                                    <div class="image-tab active" onclick="switchPreviewImageTab('depoimento_${i}', 'upload')">📤 Upload</div>
                                    <div class="image-tab" onclick="switchPreviewImageTab('depoimento_${i}', 'url')">🔗 URL</div>
                                </div>
                                
                                <div id="depoimento_${i}_image_upload" class="upload-container" onclick="document.getElementById('depoimento_${i}_image_file').click()">
                                    <div class="upload-text">
                                        <i class="fa-solid fa-cloud-upload" style="font-size: 20px; margin-bottom: 6px; display: block;"></i>
                                        Clique para selecionar
                                    </div>
                                    <input type="file" id="depoimento_${i}_image_file" class="upload-input" accept="image/*" onchange="handlePreviewImageUpload(this, 'depoimento_${i}')">
                                    <img id="depoimento_${i}_image_preview" class="upload-preview" style="display: none;">
                                </div>
                                
                                <div id="depoimento_${i}_image_url_container" style="display: none;">
                                    <input type="url" id="depoimento_${i}_image" placeholder="https://exemplo.com/foto.jpg" oninput="updatePreview()">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                depoimentosList.innerHTML += depoimentoHTML;
            }
            
            // Restaurar dados salvos
            for (let i = 1; i <= count; i++) {
                if (savedData[i]) {
                    const nameInput = document.getElementById(`depoimento_${i}_name`);
                    const descInput = document.getElementById(`depoimento_${i}_description`);
                    const imageInput = document.getElementById(`depoimento_${i}_image`);
                    const imagePreview = document.getElementById(`depoimento_${i}_image_preview`);
                    
                    if (nameInput) nameInput.value = savedData[i].name;
                    if (descInput) descInput.value = savedData[i].description;
                    if (imageInput) imageInput.value = savedData[i].image;
                    
                    if (imagePreview && savedData[i].imageSrc) {
                        imagePreview.src = savedData[i].imageSrc;
                        imagePreview.style.display = 'block';
                    }
                }
            }
            
            updatePreview();
        }

        // Mostrar alerta
        function showAlert(message, type = 'success') {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;
            setTimeout(() => {
                alertBox.classList.remove('show');
            }, 5000);
        }

        // Inicializar ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            initializePreview();
            
            // Inicializar controle de ofertas
            const offersVisibleCheckbox = document.getElementById('preview_offers_visible');
            if (offersVisibleCheckbox) {
                // Aplicar estado inicial
                toggleOffersInputs();
            }
            
            // Inicializar controle do badge seguro
            const safeBadgeCheckbox = document.getElementById('preview_show_safe_badge');
            if (safeBadgeCheckbox) {
                // Aplicar estado inicial
                toggleSafeBadgeInputs();
            }
            
            // Inicializar controle da logo da empresa
            const companyLogoCheckbox = document.getElementById('preview_show_company_logo');
            if (companyLogoCheckbox) {
                // Aplicar estado inicial
                toggleCompanyLogoInputs();
            }
            
            // Inicializar controle dos depoimentos
            const depoimentosCheckbox = document.getElementById('depoimentos_enabled');
            if (depoimentosCheckbox) {
                // Aplicar estado inicial
                toggleDepoimentosInputs();
            }
            
            // Inicializar preview do contador
            updateContadorPreview();
            
            // Adicionar listeners para atualizar preview do contador
            const contadorInputs = [
                'contador_text',
                'contador_text_expired',
                'contador_minutes',
                'contador_bg_color',
                'contador_text_color'
            ];
            
            contadorInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.addEventListener('input', updateContadorPreview);
                }
            });
            
            setTimeout(() => {
                updateOffersCount();
                updateFreteCount();
                updateDepoimentosCount();
            }, 500);
        });
        
        // Função para mostrar/ocultar inputs do contador
        function toggleContadorInputs() {
            const contadorEnabled = document.getElementById('contador_enabled');
            const contadorInputsContainer = document.getElementById('contador_inputs_container');
            
            if (contadorEnabled && contadorInputsContainer) {
                if (contadorEnabled.checked) {
                    contadorInputsContainer.style.display = 'block';
                } else {
                    contadorInputsContainer.style.display = 'none';
                }
            }
        }
        
        // Função para mostrar/ocultar inputs do badge seguro
        function toggleSafeBadgeInputs() {
            const safeBadgeEnabled = document.getElementById('preview_show_safe_badge');
            const safeBadgeContainer = document.getElementById('safe_badge_container');
            
            if (safeBadgeEnabled && safeBadgeContainer) {
                if (safeBadgeEnabled.checked) {
                    safeBadgeContainer.style.display = 'block';
                } else {
                    safeBadgeContainer.style.display = 'none';
                }
            }
        }
        
        // Função para mostrar/ocultar inputs dos depoimentos
        function toggleDepoimentosInputs() {
            const depoimentosEnabled = document.getElementById('depoimentos_enabled');
            const depoimentosContainer = document.getElementById('depoimentos_container');
            
            if (depoimentosEnabled && depoimentosContainer) {
                if (depoimentosEnabled.checked) {
                    depoimentosContainer.style.display = 'block';
                } else {
                    depoimentosContainer.style.display = 'none';
                }
            }
        }
        
        // Função para mostrar/ocultar inputs da logo da empresa
        function toggleCompanyLogoInputs() {
            const companyLogoEnabled = document.getElementById('preview_show_company_logo');
            const companyLogoContainer = document.getElementById('company_logo_container');
            
            if (companyLogoEnabled && companyLogoContainer) {
                if (companyLogoEnabled.checked) {
                    companyLogoContainer.style.display = 'block';
                } else {
                    companyLogoContainer.style.display = 'none';
                }
            }
        }
        
        // Função para mostrar/ocultar inputs dos orderbumps
        function toggleOffersInputs() {
            const offersEnabled = document.getElementById('preview_offers_visible');
            const offersContainer = document.getElementById('preview_offers_container');
            
            if (offersEnabled && offersContainer) {
                if (offersEnabled.checked) {
                    offersContainer.style.display = 'block';
                } else {
                    offersContainer.style.display = 'none';
                }
            }
        }
        
        // Função para atualizar preview do contador
        function updateContadorPreview() {
            const previewBox = document.getElementById('contador_preview');
            const previewText = document.getElementById('contador_preview_text');
            
            if (!previewBox || !previewText) return;
            
            const text = document.getElementById('contador_text')?.value || 'Texto do contador';
            const bgColor = document.getElementById('contador_bg_color')?.value || '#FF1A1A';
            const textColor = document.getElementById('contador_text_color')?.value || '#000000';
            const minutes = document.getElementById('contador_minutes')?.value || '10';
            
            previewBox.style.background = bgColor;
            previewBox.style.color = textColor;
            previewText.textContent = text;
            const timeDisplay = previewBox.querySelector('span:last-child');
            if (timeDisplay) {
                timeDisplay.textContent = `${minutes}:00`;
            }
        }
    </script>
</body>
</html>
