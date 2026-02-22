<?php
/**
 * SafetyFlash - Xibo Display Playlist API
 * 
 * Julkinen API joka palauttaa aktiiviset flashit Xibo-infonäytöille.
 * Käyttää API-avainautentikointia. CORS-tuettu.
 * 
 * @package SafetyFlash
 * @subpackage API
 * @created 2026-02-19
 * @updated 2026-02-19 - API key authentication
 * 
 * USAGE EXAMPLES:
 * 
 * 1. JSON format (default):
 *    GET /app/api/display_playlist.php?key=sf_dk_xxx...
 *    Returns: JSON array of active flashes
 * 
 * 2. HTML slideshow:
 *    GET /app/api/display_playlist.php?key=sf_dk_xxx...&format=html
 *    Returns: Full HTML page with auto-rotating slideshow
 * 
 * 3. Slideshow content only (for iframe):
 *    GET /app/api/display_playlist.php?key=sf_dk_xxx...&format=slideshow
 *    Returns: HTML content without full page wrapper
 * 
 * XIBO INTEGRATION:
 * 
 * In Xibo CMS, create a new "Webpage" widget with URL:
 * https://your-domain.com/app/api/display_playlist.php?key=sf_dk_xxx...&format=html
 * 
 * Or use Embedded content with JavaScript to fetch JSON and display images.
 * See docs/XIBO_EMBEDDED_WIDGET.md for ready-to-use templates.
 * 
 * QUERY PARAMETERS:
 * - key (required): API key that determines site and language automatically
 * - format (optional): json|html|slideshow, default: json
 * 
 * AUTHENTICATION:
 * - API key validates against sf_display_api_keys table
 * - Checks is_active = 1 and expires_at is not passed
 * - Updates last_used_at and last_used_ip on each request
 * - Returns 401 if key missing, 403 if invalid/expired/inactive
 * 
 * RESPONSE:
 * - JSON format includes duration_seconds for each flash
 * - Slideshow uses per-image duration_seconds value
 * 
 * RATE LIMITING: Max 60 requests/minute per IP
 */

declare(strict_types=1);

// Simple rate limiting
function checkRateLimit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cacheFile = sys_get_temp_dir() . '/sf_api_rate_' . md5($ip) . '.json';
    
    $now = time();
    $window = 60; // 1 minute
    $maxRequests = 60;
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        $requests = array_filter($data['requests'] ?? [], fn($t) => $t > $now - $window);
        
        if (count($requests) >= $maxRequests) {
            return false;
        }
        
        $requests[] = $now;
        file_put_contents($cacheFile, json_encode(['requests' => $requests]));
    } else {
        file_put_contents($cacheFile, json_encode(['requests' => [$now]]));
    }
    
    return true;
}

try {
    // Rate limiting check
    if (!checkRateLimit()) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Rate limit exceeded. Max 60 requests per minute.']);
        exit;
    }
    
    // CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // Load dependencies
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../assets/lib/Database.php';
    
    // Connect to database
    $pdo = Database::getInstance();
    
    // Query parameters
    $apiKey = $_GET['key'] ?? null;
    $format = $_GET['format'] ?? 'json';
    
    // API key is required
    if (!$apiKey) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing required parameter: key']);
        exit;
    }
    
    // Validate API key and get site/lang from database
    $stmt = $pdo->prepare("
        SELECT id, site, lang, is_active, expires_at
        FROM sf_display_api_keys
        WHERE api_key = :api_key
        LIMIT 1
    ");
    $stmt->execute([':api_key' => $apiKey]);
    $keyData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if key exists
    if (!$keyData) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
    
    // Check if key is active
    if (!(bool)$keyData['is_active']) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API key is deactivated']);
        exit;
    }
    
    // Check if key has expired
    if ($keyData['expires_at'] && strtotime($keyData['expires_at']) < time()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API key has expired']);
        exit;
    }
    
    // Update last_used_at and last_used_ip
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $updateStmt = $pdo->prepare("
        UPDATE sf_display_api_keys 
        SET last_used_at = NOW(), last_used_ip = :ip
        WHERE id = :id
    ");
    $updateStmt->execute([':ip' => $clientIp, ':id' => (int)$keyData['id']]);
    
    // Get site and lang from API key
    $site = $keyData['site'];
    $lang = $keyData['lang'] ?? 'fi';
    
    // Validate format
    if (!in_array($format, ['json', 'html', 'slideshow'], true)) {
        $format = 'json';
    }
    
    // Validate language
    if (!in_array($lang, ['fi', 'sv', 'en', 'it', 'el'], true)) {
        $lang = 'fi';
    }
    
    // Get display key id for JOIN
    $displayKeyId = (int)$keyData['id'];

    // Fetch active flashes for this specific display (via sf_flash_display_targets)
    $stmt = $pdo->prepare("
        SELECT
            f.id,
            f.title,
            f.preview_filename,
            f.type,
            f.published_at,
            f.created_at,
            f.display_duration_seconds,
            COALESCE(t.sort_order, 0) AS sort_order
        FROM sf_flashes f
        INNER JOIN sf_flash_display_targets t ON t.flash_id = f.id
        WHERE t.display_key_id = :display_key_id
          AND t.is_active = 1
          AND f.state = 'published'
          AND f.lang = :lang
          AND (f.display_expires_at IS NULL OR f.display_expires_at > NOW())
          AND f.display_removed_at IS NULL
        ORDER BY COALESCE(t.sort_order, 0) ASC, f.published_at DESC
        LIMIT 100
    ");

    $stmt->execute([':display_key_id' => $displayKeyId, ':lang' => $lang]);
    $flashes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build image URLs
    $baseUrl = rtrim($config['base_url'] ?? '', '/');
    
    $items = array_map(function($flash) use ($baseUrl) {
        $imageUrl = $flash['preview_filename'] 
            ? $baseUrl . '/uploads/previews/' . $flash['preview_filename']
            : $baseUrl . '/assets/images/placeholder.jpg';
        
        return [
            'id' => (int)$flash['id'],
            'title' => $flash['title'] ?? '',
            'image_url' => $imageUrl,
            'duration_seconds' => (int)($flash['display_duration_seconds'] ?? 30),
            'type' => $flash['type'] ?? 'yellow',
            'published_at' => $flash['published_at'] ?? $flash['created_at'],
            'sort_order' => (int)($flash['sort_order'] ?? 0),
        ];
    }, $flashes);
    
    // Return based on format
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'site' => $site,
            'lang' => $lang,
            'count' => count($items),
            'items' => $items,
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // HTML/Slideshow format - override restrictive CSP set by config.php/bootstrap.php
    // so this page can be embedded in an iframe from the same origin (playlist preview modal)
    header('Content-Security-Policy: frame-ancestors \'self\'', true);
    header('X-Frame-Options: SAMEORIGIN', true);
    header('Cross-Origin-Resource-Policy: same-origin', true);

    $includeHtmlWrapper = ($format === 'html');
    
    if ($includeHtmlWrapper) {
        echo "<!DOCTYPE html>\n";
        echo "<html lang=\"{$lang}\">\n";
        echo "<head>\n";
        echo "<meta charset=\"UTF-8\">\n";
        echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        echo "<meta http-equiv=\"refresh\" content=\"300\">\n"; // Auto-refresh every 5 minutes
        echo "<title>SafetyFlash Display - {$site}</title>\n";
    }
    
    echo "<style>\n";
    echo "* { margin: 0; padding: 0; box-sizing: border-box; }\n";
    echo "body { background: #000; overflow: hidden; font-family: Arial, sans-serif; }\n";
    echo ".sf-slideshow-container { width: 100vw; height: 100vh; position: relative; }\n";
    echo ".sf-slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 0.8s ease-in-out; display: flex; align-items: center; justify-content: center; }\n";
    echo ".sf-slide.active { opacity: 1; z-index: 1; }\n";
    echo ".sf-slide img { max-width: 100%; max-height: 100%; object-fit: contain; }\n";
    echo ".sf-no-content { color: #fff; text-align: center; padding: 2rem; font-size: 1.5rem; }\n";
    echo ".sf-progress-bar { position: fixed; bottom: 0; left: 0; height: 4px; background: rgba(255, 255, 255, 0.5); width: 0; transition: width linear; z-index: 10; }\n";
    echo "</style>\n";
    
    if ($includeHtmlWrapper) {
        echo "</head>\n";
        echo "<body>\n";
    }
    
    echo "<div class=\"sf-slideshow-container\" id=\"slideshow\">\n";
    
    if (empty($items)) {
        echo "<div class=\"sf-no-content\">No active safety flashes to display</div>\n";
    } else {
        foreach ($items as $index => $item) {
            $activeClass = ($index === 0) ? ' active' : '';
            echo "<div class=\"sf-slide{$activeClass}\" data-duration=\"{$item['duration_seconds']}\">\n";
            echo "<img src=\"" . htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') . "\" alt=\"" . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . "\" loading=\"lazy\">\n";
            echo "</div>\n";
        }
    }
    
    echo "</div>\n";
    echo "<div class=\"sf-progress-bar\" id=\"progressBar\"></div>\n";
    
    if (!empty($items)) {
        echo "<script>\n";
        echo "(function() {\n";
        echo "  const slides = document.querySelectorAll('.sf-slide');\n";
        echo "  const progressBar = document.getElementById('progressBar');\n";
        echo "  let currentIndex = 0;\n";
        echo "  \n";
        echo "  function showNextSlide() {\n";
        echo "    slides[currentIndex].classList.remove('active');\n";
        echo "    currentIndex = (currentIndex + 1) % slides.length;\n";
        echo "    slides[currentIndex].classList.add('active');\n";
        echo "    \n";
        echo "    const duration = parseInt(slides[currentIndex].getAttribute('data-duration') || '30', 10);\n";
        echo "    \n";
        echo "    // Reset and animate progress bar\n";
        echo "    progressBar.style.transition = 'none';\n";
        echo "    progressBar.style.width = '0%';\n";
        echo "    setTimeout(function() {\n";
        echo "      progressBar.style.transition = 'width ' + duration + 's linear';\n";
        echo "      progressBar.style.width = '100%';\n";
        echo "    }, 50);\n";
        echo "    \n";
        echo "    setTimeout(showNextSlide, duration * 1000);\n";
        echo "  }\n";
        echo "  \n";
        echo "  if (slides.length > 0) {\n";
        echo "    const firstDuration = parseInt(slides[0].getAttribute('data-duration') || '30', 10);\n";
        echo "    \n";
        echo "    // Start progress bar for first slide\n";
        echo "    setTimeout(function() {\n";
        echo "      progressBar.style.transition = 'width ' + firstDuration + 's linear';\n";
        echo "      progressBar.style.width = '100%';\n";
        echo "    }, 50);\n";
        echo "    \n";
        echo "    if (slides.length > 1) {\n";
        echo "      setTimeout(showNextSlide, firstDuration * 1000);\n";
        echo "    }\n";
        echo "  }\n";
        echo "  \n";
        echo "  // Reload playlist data every 5 minutes\n";
        echo "  setTimeout(function() { window.location.reload(); }, 300000);\n";
        echo "})();\n";
        echo "</script>\n";
    }
    
    if ($includeHtmlWrapper) {
        echo "</body>\n";
        echo "</html>\n";
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
