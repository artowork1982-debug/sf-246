<?php
/**
 * SafetyFlash - Xibo Display Playlist API
 * 
 * Julkinen API joka palauttaa aktiiviset flashit Xibo-infonäytöille.
 * Ei vaadi kirjautumista. CORS-tuettu.
 * 
 * @package SafetyFlash
 * @subpackage API
 * @created 2026-02-19
 * 
 * USAGE EXAMPLES:
 * 
 * 1. JSON format (default):
 *    GET /app/api/display_playlist.php?site=SITE_ID&lang=fi
 *    Returns: JSON array of active flashes
 * 
 * 2. HTML slideshow:
 *    GET /app/api/display_playlist.php?site=SITE_ID&format=html&duration=10
 *    Returns: Full HTML page with auto-rotating slideshow
 * 
 * 3. Slideshow content only (for iframe):
 *    GET /app/api/display_playlist.php?site=SITE_ID&format=slideshow&duration=10
 *    Returns: HTML content without full page wrapper
 * 
 * XIBO INTEGRATION:
 * 
 * In Xibo CMS, create a new "Webpage" widget with URL:
 * https://your-domain.com/app/api/display_playlist.php?site=YOUR_SITE&format=html&duration=10
 * 
 * Or use Embedded content with JavaScript to fetch JSON and display images:
 * <script>
 *   fetch('/app/api/display_playlist.php?site=YOUR_SITE&lang=fi')
 *     .then(r => r.json())
 *     .then(data => {
 *       // Display images from data array
 *     });
 * </script>
 * 
 * QUERY PARAMETERS:
 * - site (required): Worksite identifier
 * - lang (optional): Language code (fi, sv, en, it, el), default: fi
 * - format (optional): json|html|slideshow, default: json
 * - duration (optional): Seconds per image in slideshow, default: 10
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
    
    // Query parameters
    $site = $_GET['site'] ?? null;
    $lang = $_GET['lang'] ?? 'fi';
    $format = $_GET['format'] ?? 'json';
    $duration = max(3, min(60, (int)($_GET['duration'] ?? 10))); // 3-60 seconds
    
    if (!$site) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing required parameter: site']);
        exit;
    }
    
    // Validate format
    if (!in_array($format, ['json', 'html', 'slideshow'], true)) {
        $format = 'json';
    }
    
    // Validate language
    if (!in_array($lang, ['fi', 'sv', 'en', 'it', 'el'], true)) {
        $lang = 'fi';
    }
    
    // Connect to database
    $pdo = Database::getInstance();
    
    // Fetch active flashes for the site
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.title,
            f.preview_filename,
            f.type,
            f.is_pinned,
            f.sort_order,
            f.published_at,
            f.created_at
        FROM sf_flashes f
        WHERE f.state = 'published'
            AND f.lang = :lang
            AND (f.display_expires_at IS NULL OR f.display_expires_at > NOW())
            AND f.display_removed_at IS NULL
        ORDER BY f.is_pinned DESC, f.sort_order ASC, f.published_at DESC
        LIMIT 100
    ");
    
    $stmt->execute([':lang' => $lang]);
    $flashes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build image URLs
    $baseUrl = rtrim($config['base_url'] ?? '', '/');
    
    $items = array_map(function($flash) use ($baseUrl, $duration) {
        $imageUrl = $flash['preview_filename'] 
            ? $baseUrl . '/uploads/previews/' . $flash['preview_filename']
            : $baseUrl . '/assets/images/placeholder.jpg';
        
        return [
            'id' => (int)$flash['id'],
            'title' => $flash['title'] ?? '',
            'image_url' => $imageUrl,
            'duration_seconds' => $duration,
            'type' => $flash['type'] ?? 'yellow',
            'published_at' => $flash['published_at'] ?? $flash['created_at'],
            'sort_order' => (int)($flash['sort_order'] ?? 0),
        ];
    }, $flashes);
    
    // Return based on format
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'site' => $site,
            'lang' => $lang,
            'count' => count($items),
            'items' => $items,
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // HTML/Slideshow format
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
    echo ".sf-slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 1s ease-in-out; display: flex; align-items: center; justify-content: center; }\n";
    echo ".sf-slide.active { opacity: 1; z-index: 1; }\n";
    echo ".sf-slide img { max-width: 100%; max-height: 100%; object-fit: contain; }\n";
    echo ".sf-no-content { color: #fff; text-align: center; padding: 2rem; font-size: 1.5rem; }\n";
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
    
    if (!empty($items)) {
        echo "<script>\n";
        echo "(function() {\n";
        echo "  const slides = document.querySelectorAll('.sf-slide');\n";
        echo "  let currentIndex = 0;\n";
        echo "  \n";
        echo "  function showNextSlide() {\n";
        echo "    slides[currentIndex].classList.remove('active');\n";
        echo "    currentIndex = (currentIndex + 1) % slides.length;\n";
        echo "    slides[currentIndex].classList.add('active');\n";
        echo "    \n";
        echo "    const duration = parseInt(slides[currentIndex].getAttribute('data-duration') || '10', 10) * 1000;\n";
        echo "    setTimeout(showNextSlide, duration);\n";
        echo "  }\n";
        echo "  \n";
        echo "  if (slides.length > 1) {\n";
        echo "    const firstDuration = parseInt(slides[0].getAttribute('data-duration') || '10', 10) * 1000;\n";
        echo "    setTimeout(showNextSlide, firstDuration);\n";
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
