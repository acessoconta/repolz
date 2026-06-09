// Configuração de URLs da API
const API_CONFIG = {
    baseUrl: 'https://traxsomer.site',
    apiPath: '/api',
    endpoints: {
        api: '/api.php',
        pagamento: '/pagamento.php',
        verificar: '/verificar.php'
    }
};

// Função helper para obter URL completa
function getApiUrl(endpoint) {
    return API_CONFIG.baseUrl + API_CONFIG.apiPath + API_CONFIG.endpoints[endpoint];
}

// Exportar para uso global
window.API_CONFIG = API_CONFIG;
window.getApiUrl = getApiUrl;
