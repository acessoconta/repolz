# 💰 Sistema de Controle de Preço Dinâmico

## 📝 Descrição

Sistema completo para gerenciar o preço do produto no checkout através de um painel administrativo. O preço pode ser alterado globalmente sem precisar editar código.

## 🎯 Funcionalidades

- ✅ Painel administrativo com aba "Configurações"
- ✅ Alteração de preço em tempo real
- ✅ Alteração do nome do produto
- ✅ Preço sincronizado em toda a página de checkout
- ✅ API pública para obter o preço atual
- ✅ Sistema de fallback caso o arquivo não exista

## 📂 Arquivos Criados/Modificados

### Novos Arquivos:
1. **`config-controller.php`** - Controlador backend para gerenciar configurações
2. **`get-price.php`** - Endpoint público que retorna o preço atual
3. **`checkout-config.json`** - Arquivo JSON que armazena o preço e nome do produto

### Arquivos Modificados:
1. **`admin-gateway.php`** - Adicionada aba "Configurações" 
2. **`index.html`** - Script que carrega o preço dinamicamente ao carregar a página

## 🔧 Como Usar

### Acessar o Painel Admin

1. Acesse: `https://seudominio.com/checkout/admin-gateway.php`
2. Faça login com a senha configurada
3. Clique na aba "⚙️ Configurações"

### Alterar o Preço

1. No campo "Preço (R$)", digite o novo valor (ex: `29.90` ou `29,90`)
2. (Opcional) Altere o nome do produto
3. Clique em "💾 Salvar Configurações"
4. ✅ As alterações são aplicadas IMEDIATAMENTE em todo o checkout

### Como Funciona

```
┌─────────────────────────────────────────────────────┐
│  1. Admin altera preço no painel                    │
│     └─> Salvo em checkout-config.json              │
│                                                     │
│  2. Cliente carrega página de checkout (index.html)│
│     └─> JavaScript chama get-price.php              │
│         └─> Retorna preço do checkout-config.json  │
│                                                     │
│  3. Preço é atualizado automaticamente em:          │
│     ✓ Input hidden (checkout-total-amount)         │
│     ✓ Carrinho lateral                             │
│     ✓ Resumo do pedido                             │
│     ✓ Cálculos de quantidade                        │
│     ✓ localStorage                                  │
│                                                     │
│  4. Cliente preenche formulário e clica em pagar    │
│     └─> Envia valor para pagamento.php              │
│         └─> Processa pagamento no gateway           │
└─────────────────────────────────────────────────────┘
```

## 📌 Formato do checkout-config.json

```json
{
    "product_price": "21.15",
    "product_name": "Taxa de Cadastro - Essa taxa será reembolsada 100% após o saque",
    "last_updated": "2026-03-10 15:30:45"
}
```

## 🔐 Segurança

- ✅ Alterações requerem login administrativo
- ✅ Validação de formato de preço (deve ser numérico e maior que 0)
- ✅ Endpoint público (`get-price.php`) apenas lê dados, não escreve
- ✅ Mesma senha do painel de gateways

## 🌐 API Pública

### GET `/checkout/get-price.php`

**Resposta:**
```json
{
    "success": true,
    "price": "21.15",
    "name": "Taxa de Cadastro...",
    "formatted_price": "R$ 21,15"
}
```

## 🔄 Sistema de Fallback

Se o arquivo `checkout-config.json` não existir ou estiver corrompido:
- Preço padrão: **R$ 21,15**
- Nome padrão: "Taxa de Cadastro - Essa taxa será reembolsada 100% após o saque"

## ⚠️ Notas Importantes

1. **Permissões**: Certifique-se de que a pasta `/checkout/` tem permissão de escrita para o PHP criar/editar o `checkout-config.json`

2. **Backup**: Faça backup do `checkout-config.json` regularmente

3. **Formato de Preço**: Use ponto (`.`) ou vírgula (`,`) como separador decimal
   - ✅ Aceito: `21.15`, `21,15`, `29.90`, `100`
   - ❌ Inválido: `21.15.90`, `abc`, `-10`

4. **Sincronização**: As alterações são INSTANTÂNEAS. Não é necessário recarregar a página do admin.

## 🐛 Troubleshooting

### Preço não atualiza no checkout
1. Verifique se `checkout-config.json` foi criado
2. Teste o endpoint: acesse `https://seudominio.com/checkout/get-price.php`
3. Verifique console do navegador (F12) para erros JavaScript

### Erro ao salvar configurações
1. Verifique permissões da pasta `/checkout/`
2. Certifique-se de estar logado no painel admin
3. Verifique logs de erro do PHP

### Preço retorna para 21.15
- O arquivo `checkout-config.json` pode ter sido deletado ou corrompido
- Faça login no admin e configure o preço novamente

## 📞 Suporte

Caso tenha problemas:
1. Verifique os logs do PHP (`error_log`)
2. Teste o endpoint `get-price.php` diretamente no navegador
3. Verifique permissões de arquivos/pastas

---

**Desenvolvido em:** Março 2026  
**Versão:** 1.0
