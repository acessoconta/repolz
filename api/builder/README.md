# 🛠️ Checkout Builder

Sistema de criação de checkouts dinâmicos com preview em tempo real usando EXATAMENTE a estrutura HTML original.

## 📋 Funcionalidades

- ✅ Editor visual com preview em tempo real
- ✅ Usa o HTML original completo (sem modificações na estrutura)
- ✅ Configuração de produto (nome, descrição, preço, imagem)
- ✅ Personalização da marca (logo, nome da loja)
- ✅ Gerenciamento de depoimentos
- ✅ Exportação em JSON
- ✅ Geração de checkout completo com PHP
- ✅ Sem necessidade de banco de dados

## 🚀 Como Usar

### Opção 1: Builder Visual (Recomendado)

1. Abra o arquivo `builder/index.html` no navegador
2. Edite os campos no painel esquerdo
3. Veja as mudanças em tempo real no preview
4. Clique em "Gerar Checkout" para baixar o JSON

### Opção 2: Gerar via PHP

```bash
php generate-checkout.php config.json
```

Isso irá criar uma pasta `checkout_[id]/` com:
- `index.html` - Checkout completo
- `config.json` - Configurações
- Todos os arquivos CSS necessários
- Imagens (se locais)

## 📁 Estrutura de Arquivos

```
checkout/
├── index.html              # Template original (NÃO MODIFICAR)
├── a.css até j.css         # Arquivos CSS originais
├── config.json             # Configuração de exemplo
├── generate-checkout.php   # Gerador de checkout
└── builder/
    ├── index.html          # Interface do builder
    ├── builder.js          # Lógica do builder
    └── README.md           # Documentação

checkout_[id]/              # Pasta gerada
├── index.html              # Checkout personalizado
├── config.json             # Configurações usadas
└── *.css                   # Arquivos CSS copiados
```

## 📝 Formato do JSON

```json
{
  "id": "checkout_1234567890",
  "createdAt": "2024-01-01T00:00:00.000Z",
  "product": {
    "name": "Nome do Produto",
    "description": "Descrição",
    "price": 24.56,
    "image": "url_da_imagem"
  },
  "store": {
    "name": "Nome da Loja",
    "logo": "url_da_logo",
    "cnpj": "00.000.000/0000-00",
    "email": "contato@loja.com"
  },
  "testimonials": [
    {
      "name": "Nome",
      "image": "url_da_foto",
      "stars": 5,
      "text": "Depoimento..."
    }
  ]
}
```

## 🎨 Personalização

Todos os campos são editáveis:
- Nome e descrição do produto
- Preço (formato: 00.00)
- URLs de imagens
- Informações da empresa
- Depoimentos (adicionar/remover/editar)

## 💾 Exportação

### Via Builder:
1. Clique em "Gerar Checkout"
2. Um arquivo JSON é baixado
3. Use o JSON com o script PHP para gerar o checkout completo

### Via PHP:
```bash
php generate-checkout.php seu_config.json
```

## 🔄 Workflow Completo

1. **Editar** - Use o builder visual para configurar
2. **Exportar** - Baixe o JSON com as configurações
3. **Gerar** - Execute o PHP para criar a pasta do checkout
4. **Deploy** - Suba a pasta gerada para seu servidor

## ⚙️ Requisitos

- Navegador moderno (Chrome, Firefox, Edge, Safari)
- PHP 7.0+ (para geração via script)
- Os arquivos CSS (a.css até j.css) devem estar na pasta raiz
- O arquivo index.html original deve estar intacto

## 📌 Notas Importantes

- **NÃO MODIFIQUE** o arquivo `index.html` original
- O builder carrega o HTML original e faz substituições em tempo real
- O script PHP cria uma cópia modificada na pasta de saída
- Todas as mudanças são feitas via substituição de texto
- A estrutura HTML original é preservada 100%

## 🐛 Troubleshooting

**Preview não carrega:**
- Verifique se o arquivo `index.html` está na pasta raiz
- Abra o console do navegador para ver erros

**Estilos não aparecem:**
- Certifique-se que os arquivos CSS estão na pasta raiz
- Verifique os caminhos relativos

**PHP não gera checkout:**
- Verifique se o arquivo config.json existe
- Confirme que o PHP tem permissão de escrita
- Execute: `php -v` para verificar a instalação

## 📞 Suporte

Para problemas ou dúvidas, verifique:
1. Se todos os arquivos CSS estão presentes
2. Se o index.html original está intacto
3. Se o JSON está no formato correto

