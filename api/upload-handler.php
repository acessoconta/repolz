<?php
/**
 * MANIPULADOR DE UPLOAD DE IMAGENS
 */
session_start();

// Verificar se está logado
if (!isset($_SESSION['gateway_admin_logged'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Configurações de upload
$uploadDir = __DIR__ . '/../uploads/';
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Criar diretório se não existir
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Processar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    
    // Verificar erros de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro no upload: ' . $file['error']
        ]);
        exit;
    }
    
    // Verificar tamanho do arquivo
    if ($file['size'] > $maxFileSize) {
        echo json_encode([
            'success' => false,
            'message' => 'Arquivo muito grande. Máximo: 5MB'
        ]);
        exit;
    }
    
    // Verificar tipo do arquivo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tipo de arquivo não permitido. Use: JPG, PNG, GIF ou WEBP'
        ]);
        exit;
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'img_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . strtolower($extension);
    $filePath = $uploadDir . $fileName;
    
    // Mover arquivo para o diretório de uploads
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Gerar URL da imagem
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = dirname($_SERVER['REQUEST_URI']);
        $imageUrl = $protocol . '://' . $host . $basePath . '/../uploads/' . $fileName;
        
        echo json_encode([
            'success' => true,
            'message' => 'Imagem enviada com sucesso!',
            'url' => $imageUrl,
            'filename' => $fileName
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao salvar o arquivo'
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum arquivo enviado'
    ]);
}
?>