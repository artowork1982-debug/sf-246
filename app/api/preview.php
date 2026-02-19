<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../services/PreviewRenderer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $renderer = new PreviewRenderer();
    
    $cardNumber = $_POST['card_number'] ?? '1';
    
    $data = [
        'type' => $_POST['type'] ?? 'yellow',
        'lang' => $_POST['lang'] ?? 'fi',
        'short_text' => $_POST['short_text'] ?? '',
        'description' => $_POST['description'] ?? '',
        'site' => $_POST['site'] ?? '',
        'site_detail' => $_POST['site_detail'] ?? '',
        'occurred_at' => $_POST['occurred_at'] ?? '',
        'root_causes' => $_POST['root_causes'] ?? '',
        'actions' => $_POST['actions'] ?? '',
        'grid_bitmap' => $_POST['grid_bitmap'] ?? '',
        'card_number' => $cardNumber,
        'font_size_override' => $_POST['font_size_override'] ?? null,
    ];
    
    $resolution = $_POST['resolution'] ?? 'preview';
    
    // DEBUG: Log data
    error_log('Preview API data: ' . json_encode($data));
    
    $imageBase64 = $renderer->render($data, $resolution);
    
    if ($imageBase64 === null) {
        // Check PHP error log for details
        $lastError = error_get_last();
        throw new RuntimeException('Failed to generate preview image. Last error: ' . json_encode($lastError));
    }
    
    echo json_encode([
        'ok' => true,
        'image' => 'data:image/jpeg;base64,' . $imageBase64
    ]);
    
} catch (Throwable $e) {
    error_log('Preview API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}