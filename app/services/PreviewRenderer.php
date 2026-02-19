<?php
/**
 * Unified Preview Rendering Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/log_app.php';

class PreviewRenderer
{
    const WIDTH = 1920;
    const HEIGHT = 1080;
    const PREVIEW_WIDTH = 480;
    const PREVIEW_HEIGHT = 270;
    
    // Match PreviewImageGenerator constants
    const HEADER_HEIGHT = 220;  // Match PreviewImageGenerator TITLE_Y
    const TITLE_BOX_HEIGHT = 100;
    const DESC_START_Y = 320;  // Match PreviewImageGenerator DESC_Y
    const MARGIN_LEFT = 116;
    const MARGIN_BOTTOM = 60;
    const CONTENT_GAP = 50;
    const GRID_BITMAP_SIZE = 750;
    
    const TEXT_COL_WIDTH = 920;
    const DESCRIPTION_HEIGHT = 450;
    const META_BOX_WIDTH = 450;
    const META_BOX_HEIGHT = 115;
    
    const CARD2_TITLE_WIDTH = 1730;
    const CARD2_COL_WIDTH = 845;
    const CARD2_COL_HEIGHT = 540;
    
    const COLOR_BLACK = '#1a1a1a';
    const COLOR_WHITE = '#ffffff';
    const COLOR_GRAY = '#d2d2d2';
    
    // Font size presets - matches PreviewImageGenerator and preview-tutkinta.js
    const FONT_PRESETS = [
        'XS' => 14,
        'S' => 16,
        'M' => 18,
        'L' => 20,
        'XL' => 22,
    ];
    
    // Font size ratios - matches PreviewImageGenerator
    const FONT_RATIOS = [
        'shortTitle' => 1.6,
        'description' => 1.0,
        'content' => 0.9,
    ];
    
    // Layout constraint constants - MUST MATCH PreviewImageGenerator.php
    const CARD1_DESC_MAX_HEIGHT = 420;
    const CARD1_DESC_WIDTH = 880;        // TEXT_COL_WIDTH(920) - 40 padding
    const COLUMN_MAX_HEIGHT = 400;
    const COLUMN_WIDTH = 420;            // (920-20)/2 - 30 padding
    const HEADERS_SPACING = 100;         // Header boxes + gaps
    const SINGLE_CARD_MAX_HEIGHT = 850;
    const CHAR_WIDTH_RATIO = 0.48;       // Open Sans actual average
    const FONT_SIZE_AUTO_MAX = 22;
    const FONT_SIZE_AUTO_MIN = 12;
    const FONT_SIZE_AUTO_STEP = 1;
    
    private string $basePath;
    private string $templatesDir;
    private string $uploadsDir;
    private string $fontsDir;
    private ?array $terms = null;
    
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 2);
        $this->templatesDir = $this->basePath . '/assets/img/templates';
        $this->uploadsDir = $this->basePath . '/uploads/images';
        $this->fontsDir = $this->basePath . '/assets/fonts';
    }
    
    public function render(array $data, string $resolution = 'final'): ?string
    {
        try {
            $type = $data['type'] ?? 'yellow';
            $cardNumber = $data['card_number'] ?? 1;
            
            $layoutMode = 'standard';
            
            // Convert to string for reliable comparison
            $cardNumberStr = (string)$cardNumber;
            
            if ($type === 'green') {
                if ($cardNumberStr === 'single') {
                    $layoutMode = 'green_single';
                    $cardNumber = 1;
                } elseif ($cardNumberStr === '1') {
                    $layoutMode = 'green_two_slide_1';
                    $cardNumber = 1;
                } elseif ($cardNumberStr === '2') {
                    $layoutMode = 'green_two_slide_2';
                    $cardNumber = 2;
                } else {
                    // Default to single card for green
                    $layoutMode = 'green_single';
                    $cardNumber = 1;
                }
            }
            
            $cardNumber = (int)$cardNumber;
            
            $image = $this->createBaseImage(self::WIDTH, self::HEIGHT);
            
            $bgPath = $this->getBackgroundPath($type, $data['lang'] ?? 'fi', $layoutMode, $cardNumber);
            $this->applyBackground($image, $bgPath);
            
            switch ($layoutMode) {
                case 'green_single':
                    $this->renderGreenCardSingle($image, $data);
                    break;
                case 'green_two_slide_1':
                    $this->renderGreenCard1($image, $data);
                    break;
                case 'green_two_slide_2':
                    $this->renderGreenCard2($image, $data);
                    break;
                default:
                    $this->renderYellowRedCard($image, $data);
                    break;
            }
            
            if ($resolution === 'preview') {
                $scaled = imagescale($image, self::PREVIEW_WIDTH, self::PREVIEW_HEIGHT, IMG_BICUBIC);
                if ($scaled === false) {
                    imagedestroy($image);
                    return null;
                }
                imagedestroy($image);
                $image = $scaled;
            }
            
            ob_start();
            imagejpeg($image, null, 92);
            $output = ob_get_clean();
            imagedestroy($image);
            
            return $output === false ? null : base64_encode($output);
            
        } catch (Throwable $e) {
            // Kirjaa virhe sovelluksen lokiin
            if (function_exists('sf_app_log')) {
                sf_app_log(
                    'PreviewRenderer::render FAILED: ' . $e->getMessage(),
                    LOG_LEVEL_ERROR,
                    [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'data_type' => $data['type'] ?? 'unknown',
                        'card_number' => $data['card_number'] ?? 'unknown',
                    ],
                    'sf_errors.log'
                );
            }
            
            error_log('PreviewRenderer::render failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return null;
        }
    }
    
    private function renderYellowRedCard($image, array $data): void
    {
        $y = self::HEADER_HEIGHT;
        
        // Get font sizes (supports override and auto mode) - MUST MATCH PreviewImageGenerator.php
        $fontSizes = $this->getFontSizes($data);
        
        $titleFontSize = $fontSizes['title'];
        $descFontSize = $fontSizes['description'];
        
        $shortText = $data['short_text'] ?? '';
        $titleHeight = $this->renderDynamicText($image, self::MARGIN_LEFT + 20, $y + 20, self::TEXT_COL_WIDTH - 40, $shortText, $titleFontSize, 'Bold', self::COLOR_BLACK);
        $y += $titleHeight + 40;  // 40px gap between title and description (standardized)
        
        $descText = $data['description'] ?? '';
        $this->renderText($image, self::MARGIN_LEFT + 20, $y + 20, self::TEXT_COL_WIDTH - 40, self::DESCRIPTION_HEIGHT - 40, $descText, self::COLOR_BLACK, $descFontSize);
        
        $this->renderMetaBoxes($image, $data);
        $this->renderGridBitmap($image, self::MARGIN_LEFT + self::TEXT_COL_WIDTH + self::CONTENT_GAP, self::HEADER_HEIGHT, $data);
    }
    
    private function renderGreenCardSingle($image, array $data): void
    {
        $y = self::HEADER_HEIGHT;
        $lang = $data['lang'] ?? 'fi';
        
        // Get font sizes (supports override and auto mode) - MUST MATCH PreviewImageGenerator.php
        $fontSizes = $this->getFontSizes($data);
        
        $titleFontSize = $fontSizes['title'];
        $descFontSize = $fontSizes['description'];
        $contentFontSize = $fontSizes['content'];
        
        // Short title - BLACK text, dynamic font size based on total content length
        $shortText = $data['short_text'] ?? '';
        $titleHeight = $this->renderDynamicText($image, self::MARGIN_LEFT + 20, $y + 20, self::TEXT_COL_WIDTH - 40, $shortText, $titleFontSize, 'Bold', self::COLOR_BLACK);
        $y += $titleHeight + 40;  // 40px gap between title and description
        
        // Description - starts dynamically after title
        $descText = $data['description'] ?? '';
        $descHeight = $this->renderDynamicText($image, self::MARGIN_LEFT + 20, $y, self::TEXT_COL_WIDTH - 40, $descText, $descFontSize, 'Regular', self::COLOR_BLACK);
        $y += $descHeight + 30;
        
        // Root causes & Actions - format bullet points
        $colWidth = (int)((self::TEXT_COL_WIDTH - 20) / 2);
        
        $rootText = $this->formatBulletPoints($data['root_causes'] ?? '');
        $actionsText = $this->formatBulletPoints($data['actions'] ?? '');
        
        // Use contentFontSize from total length calculation (includes title and description, not just root causes + actions)
        $rootContentHeight = $this->calculateTextHeight($rootText, $colWidth - 30, $contentFontSize);
        $actionsContentHeight = $this->calculateTextHeight($actionsText, $colWidth - 30, $contentFontSize);
        $maxContentHeight = max($rootContentHeight, $actionsContentHeight, 50);
        $raHeight = $maxContentHeight + 70;
        
        $metaY = self::HEIGHT - self::MARGIN_BOTTOM - self::META_BOX_HEIGHT;
        $maxAvailableHeight = $metaY - $y - 20;
        $raHeight = max(120, min($raHeight, $maxAvailableHeight));
        
        // Get translated labels
        $rootCausesLabel = $this->getTerm('root_causes_label', $lang);
        $actionsLabel = $this->getTerm('actions_label', $lang);
        
        $this->renderLabeledBox($image, self::MARGIN_LEFT, $y, $colWidth, $raHeight, $rootCausesLabel, $rootText, 18, $contentFontSize);
        $this->renderLabeledBox($image, self::MARGIN_LEFT + $colWidth + 20, $y, $colWidth, $raHeight, $actionsLabel, $actionsText, 18, $contentFontSize);
        
        $this->renderMetaBoxes($image, $data);
        $this->renderGridBitmap($image, self::MARGIN_LEFT + self::TEXT_COL_WIDTH + self::CONTENT_GAP, self::HEADER_HEIGHT, $data);
    }
    
    private function renderGreenCard1($image, array $data): void
    {
        $y = self::HEADER_HEIGHT;
        
        // Get font sizes (supports override and auto mode) - MUST MATCH PreviewImageGenerator.php
        $fontSizes = $this->getFontSizes($data);
        
        $titleFontSize = $fontSizes['title'];
        $descFontSize = $fontSizes['description'];
        
        $shortText = $data['short_text'] ?? '';
        $titleHeight = $this->renderDynamicText($image, self::MARGIN_LEFT + 20, $y + 20, self::TEXT_COL_WIDTH - 40, $shortText, $titleFontSize, 'Bold', self::COLOR_BLACK);
        $y += $titleHeight + 40;  // 40px gap between title and description
        
        $descText = $data['description'] ?? '';
        $this->renderText($image, self::MARGIN_LEFT + 20, $y + 20, self::TEXT_COL_WIDTH - 40, self::DESCRIPTION_HEIGHT - 40, $descText, self::COLOR_BLACK, $descFontSize);
        
        $this->renderMetaBoxes($image, $data);
        $this->renderGridBitmap($image, self::MARGIN_LEFT + self::TEXT_COL_WIDTH + self::CONTENT_GAP, self::HEADER_HEIGHT, $data);
    }
    
    private function renderGreenCard2($image, array $data): void
    {
        $y = self::HEADER_HEIGHT;
        $lang = $data['lang'] ?? 'fi';
        
        // Get font sizes (supports override and auto mode) - MUST MATCH PreviewImageGenerator.php
        $fontSizes = $this->getFontSizes($data);
        
        $titleFontSize = $fontSizes['title'];
        $contentFontSize = $fontSizes['content'];
        
        // Full-width short title - dynamic font size based on total content length
        $shortText = $data['short_text'] ?? '';
        $titleX = (int)((self::WIDTH - self::CARD2_TITLE_WIDTH) / 2);
        $titleHeight = $this->renderDynamicText($image, $titleX + 20, $y + 20, self::CARD2_TITLE_WIDTH - 40, $shortText, $titleFontSize, 'Bold', self::COLOR_BLACK);
        
        // 60px gap between title and sections (was 40px)
        $y += $titleHeight + 60;
        
        // Two columns for root causes and actions - format bullet points
        $colX1 = (int)((self::WIDTH - (2 * self::CARD2_COL_WIDTH + 30)) / 2);
        $colX2 = $colX1 + self::CARD2_COL_WIDTH + 30;
        
        $rootText = $this->formatBulletPoints($data['root_causes'] ?? '');
        $actionsText = $this->formatBulletPoints($data['actions'] ?? '');
        
        // Get translated labels
        $rootCausesLabel = $this->getTerm('root_causes_label', $lang);
        $actionsLabel = $this->getTerm('actions_label', $lang);
        
        // Header font size 18px (smaller, cleaner), content uses calculated contentFontSize
        $this->renderLabeledBox($image, $colX1, $y, self::CARD2_COL_WIDTH, self::CARD2_COL_HEIGHT, $rootCausesLabel, $rootText, 18, $contentFontSize);
        $this->renderLabeledBox($image, $colX2, $y, self::CARD2_COL_WIDTH, self::CARD2_COL_HEIGHT, $actionsLabel, $actionsText, 18, $contentFontSize);
    }
    
    private function renderMetaBoxes($image, array $data): void
    {
        $metaY = self::HEIGHT - self::MARGIN_BOTTOM - self::META_BOX_HEIGHT;
        $lang = $data['lang'] ?? 'fi';
        
        // Get translated labels
        $siteLabel = $this->getTerm('site_label', $lang);
        $whenLabel = $this->getTerm('when_label', $lang);
        
        // Site box
        $this->renderBoxWithBackground($image, self::MARGIN_LEFT, $metaY, self::META_BOX_WIDTH, self::META_BOX_HEIGHT, self::COLOR_GRAY);
        // Meta label: 16px Bold
        $this->renderText($image, self::MARGIN_LEFT + 15, $metaY + 15, self::META_BOX_WIDTH - 30, 25, strtoupper($siteLabel) . ':', self::COLOR_BLACK, 16, 'Bold');
        // Meta value: 22px Regular
        // Combine site and site_detail (matching PreviewImageGenerator.php logic)
        $site = $data['site'] ?? '';
        $siteDetail = $data['site_detail'] ?? '';
        $siteText = $site;
        if ($siteDetail) {
            $siteText .= ' – ' . $siteDetail;
        }
        if (!$siteText) {
            $siteText = '–';
        }
        $this->renderText($image, self::MARGIN_LEFT + 15, $metaY + 50, self::META_BOX_WIDTH - 30, 50, $siteText, self::COLOR_BLACK, 22);
        
        // Date box
        $dateX = self::MARGIN_LEFT + self::META_BOX_WIDTH + 20;
        $this->renderBoxWithBackground($image, $dateX, $metaY, self::META_BOX_WIDTH, self::META_BOX_HEIGHT, self::COLOR_GRAY);
        // Meta label: 16px Bold
        $this->renderText($image, $dateX + 15, $metaY + 15, self::META_BOX_WIDTH - 30, 25, strtoupper($whenLabel), self::COLOR_BLACK, 16, 'Bold');
        $formattedDate = $this->formatDate($data['occurred_at'] ?? null);
        // Meta value: 22px Regular
        $this->renderText($image, $dateX + 15, $metaY + 50, self::META_BOX_WIDTH - 30, 50, $formattedDate, self::COLOR_BLACK, 22);
    }
    
    /**
     * Render labeled box:
     * - Black header background with white text (label)
     * - No background for content, black text
     */
    private function renderLabeledBox($image, int $x, int $y, int $width, int $height, string $label, string $text, int $headerFontSize, int $contentFontSize): void
    {
        // Pienempi header-korkeus siistimpään ulkoasuun
        $headerHeight = 45;  // oli 50
        $padding = 15;       // sisäinen padding
        
        // Rajoita header-fonttikoko maksimiin 20px
        $actualHeaderFontSize = min($headerFontSize, 20);
        
        // Black header background
        $this->renderBoxWithBackground($image, $x, $y, $width, $headerHeight, self::COLOR_BLACK);
        
        // White text for header label
        $fontFile = $this->fontsDir . '/OpenSans-Bold.ttf';
        if (!file_exists($fontFile)) {
            $fontFile = $this->fontsDir . '/OpenSans-Regular.ttf';
        }
        
        if (file_exists($fontFile) && function_exists('imagettftext')) {
            $white = imagecolorallocate($image, 255, 255, 255);
            // Center vertically in header
            $textY = $y + (int)(($headerHeight + $actualHeaderFontSize) / 2) - 2;
            imagettftext($image, $actualHeaderFontSize, 0, (int)($x + $padding), (int)$textY, $white, $fontFile, $label);
        }
        
        // Content area - NO background, black text
        // Sisältöalue alkaa headerin jälkeen + padding
        $contentY = $y + $headerHeight + $padding;
        // Use renderTextWithBullets to support hanging indent
        if (!empty($text)) {
            $this->renderTextWithBullets($image, $x + $padding, $contentY, $width - ($padding * 2), $height - $headerHeight - 30, $text, self::COLOR_BLACK, $contentFontSize);
        }
    }
    
    private function renderDynamicText($image, int $x, int $y, int $maxWidth, string $text, int $fontSize, string $fontWeight, string $textColor): int
    {
        $lineHeight = (int)($fontSize * 1.3);
        $lines = $this->calculateTextLines($text, $maxWidth, $fontSize, $fontWeight);
        $height = $lines * $lineHeight;
        
        $this->renderText($image, $x, $y, $maxWidth, $height + 20, $text, $textColor, $fontSize, $fontWeight);
        
        return $height;
    }
    
    private function calculateTextHeight(string $text, int $maxWidth, int $fontSize, string $fontWeight = 'Regular'): int
    {
        $lineHeight = (int)($fontSize * 1.3);
        $lines = $this->calculateTextLines($text, $maxWidth, $fontSize, $fontWeight);
        return $lines * $lineHeight;
    }
    
    private function renderBoxWithBackground($image, int $x, int $y, int $width, int $height, string $color): void
    {
        $rgb = $this->hexToRgb($color);
        $bgColor = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($image, $x, $y, $x + $width, $y + $height, $bgColor);
    }
    
    private function getDynamicFontSize(int $length, array $thresholds, int $minSize): int
    {
        foreach ($thresholds as $threshold => $size) {
            if ($length < $threshold) {
                return $size;
            }
        }
        return $minSize;
    }
    
    /**
     * Format date string to dd.mm.yyyy HH:mm format in Europe/Helsinki timezone
     * 
     * @param string|null $dateString Date string to format (ISO format or other parseable format)
     * @return string Formatted date string in dd.mm.yyyy HH:mm format, or '–' if parsing fails
     */
    private function formatDate(?string $dateString): string
    {
        if (empty($dateString)) {
            return '–';
        }
        
        $tz = new DateTimeZone('Europe/Helsinki');
        
        // Try multiple date formats - parse as local Helsinki time
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dateString, $tz);
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $dateString, $tz);
        }
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dateString, $tz);
        }
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d H:i', $dateString, $tz);
        }
        
        if ($dt === false) {
            // Fallback: strtotime interprets as UTC, then convert to Helsinki time
            // This is needed for edge cases where the date string format doesn't match above
            $ts = strtotime($dateString);
            if ($ts !== false) {
                try {
                    $dt = new DateTime('@' . $ts);
                    $dt->setTimezone($tz);
                } catch (Exception $e) {
                    return '–';
                }
            } else {
                return '–';
            }
        }
        
        return $dt->format('d.m.Y H:i');
    }
    
    /**
     * Format bullet points - convert dashes to proper bullets
     */
    private function formatBulletPoints(string $text): string
    {
        if (empty(trim($text))) {
            return '';
        }
        
        // Normalisoi rivinvaihdot
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Jaa riveihin
        $lines = explode("\n", $text);
        $formatted = [];
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;
            
            // Tunnista rivin alussa oleva "- " tai "-"
            if (preg_match('/^-\s*(.+)$/', $trimmed, $matches)) {
                $formatted[] = '• ' . trim($matches[1]);
            } else {
                // Ei bullet-merkintää, lisää sellaisenaan
                $formatted[] = $trimmed;
            }
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Render text with bullet list support (hanging indent)
     */
    private function renderTextWithBullets($image, int $x, int $y, int $maxWidth, int $maxHeight, string $text, string $color, int $fontSize, string $fontWeight = 'Regular'): void
    {
        $rgb = $this->hexToRgb($color);
        $textColor = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        
        $fontFile = $this->fontsDir . '/OpenSans-' . $fontWeight . '.ttf';
        if (!file_exists($fontFile)) {
            $fontFile = $this->fontsDir . '/OpenSans-Regular.ttf';
        }
        $useTTF = file_exists($fontFile) && function_exists('imagettftext');
        
        $lines = explode("\n", $text);
        $lineHeight = (int)($fontSize * 1.4);
        $bulletWidth = (int)($fontSize * 1.2); // Bullet width
        $currentY = $y + $fontSize;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;
            
            // Check if this is a bullet line
            if (mb_strpos($trimmed, '• ') === 0) {
                $bulletText = mb_substr($trimmed, 2); // Remove 2 characters (bullet + space) regardless of byte length
                
                // Draw bullet
                if ($useTTF) {
                    imagettftext($image, $fontSize, 0, (int)$x, (int)$currentY, $textColor, $fontFile, '•');
                } else {
                    imagestring($image, 5, (int)$x, (int)($currentY - $fontSize), '•', $textColor);
                }
                
                // Draw text with indentation, wrapped
                $textX = $x + $bulletWidth;
                $textWidth = $maxWidth - $bulletWidth;
                $textHeight = $this->renderWrappedText($image, (int)$textX, (int)$currentY, (int)$textWidth, $bulletText, $fontSize, $fontWeight, $color, $useTTF, $fontFile, $textColor);
                
                $currentY += $textHeight + (int)($lineHeight * 0.3);
            } else {
                // Normal line without bullet
                $textHeight = $this->renderWrappedText($image, (int)$x, (int)$currentY, (int)$maxWidth, $trimmed, $fontSize, $fontWeight, $color, $useTTF, $fontFile, $textColor);
                $currentY += $textHeight + (int)($lineHeight * 0.3);
            }
            
            // Stop if we exceed maxHeight
            if ($currentY - $y > $maxHeight) {
                break;
            }
        }
    }
    
    /**
     * Render wrapped text and return height used
     */
    private function renderWrappedText($image, int $x, int $y, int $maxWidth, string $text, int $fontSize, string $fontWeight, string $color, bool $useTTF, string $fontFile, $textColor): int
    {
        $lineHeight = (int)($fontSize * 1.4);
        
        // Return early if text is empty
        $trimmedText = trim($text);
        if (empty($trimmedText)) {
            return 0;
        }
        
        $measureWidth = function (int $fs, string $s) use ($useTTF, $fontFile): float {
            if ($useTTF) {
                $bbox = imagettfbbox($fs, 0, $fontFile, $s);
                return abs($bbox[2] - $bbox[0]);
            }
            return strlen($s) * ($fs * 0.6);
        };
        
        // Wrap text to multiple lines
        $words = preg_split('/\s+/', $trimmedText);
        $lines = [];
        $currentLine = '';
        
        foreach ($words as $word) {
            if (empty($word)) continue;
            $test = ($currentLine === '') ? $word : ($currentLine . ' ' . $word);
            if ($measureWidth($fontSize, $test) > $maxWidth && $currentLine !== '') {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $test;
            }
        }
        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }
        
        // Render each line
        $currentY = $y;
        foreach ($lines as $line) {
            if ($useTTF) {
                imagettftext($image, $fontSize, 0, (int)$x, (int)$currentY, $textColor, $fontFile, $line);
            } else {
                imagestring($image, 5, (int)$x, (int)($currentY - $fontSize), $line, $textColor);
            }
            $currentY += $lineHeight;
        }
        
        return count($lines) * $lineHeight;
    }
    
    private function createBaseImage(int $width, int $height)
    {
        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            throw new RuntimeException('Failed to create image');
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        return $image;
    }
    
    private function applyBackground($image, string $bgPath): void
    {
        if (!file_exists($bgPath)) {
            error_log("Background template not found: {$bgPath}");
            return;
        }
        $bg = imagecreatefromjpeg($bgPath);
        if ($bg) {
            imagecopy($image, $bg, 0, 0, 0, 0, self::WIDTH, self::HEIGHT);
            imagedestroy($bg);
        }
    }
    
    private function getBackgroundPath(string $type, string $lang, string $layoutMode, int $cardNumber): string
    {
        if ($type === 'green') {
            switch ($layoutMode) {
                case 'green_single':
                    $filename = "SF_bg_green_{$lang}.jpg";
                    break;
                case 'green_two_slide_1':
                    $filename = "SF_bg_green_1_{$lang}.jpg";
                    break;
                case 'green_two_slide_2':
                    $filename = "SF_bg_green_2_{$lang}.jpg";
                    break;
                default:
                    $filename = "SF_bg_green_{$lang}.jpg";
            }
        } else {
            $filename = "SF_bg_{$type}_{$lang}.jpg";
        }
        
        $path = $this->templatesDir . '/' . $filename;
        if (!file_exists($path)) {
            $path = $this->templatesDir . '/' . str_replace("_{$lang}.jpg", "_fi.jpg", $filename);
        }
        return $path;
    }
    
    /**
     * Get translation term from app/config/terms
     */
    private function getTerm(string $key, string $lang): string
    {
        // Load terms once
        if ($this->terms === null) {
            $termsFile = $this->basePath . '/app/config/terms/form.php';
            if (file_exists($termsFile)) {
                $this->terms = require $termsFile;
            } else {
                $this->terms = [];
            }
        }
        
        // Look up the term
        if (isset($this->terms[$key][$lang])) {
            return $this->terms[$key][$lang];
        }
        
        // Fallback to Finnish
        if (isset($this->terms[$key]['fi'])) {
            return $this->terms[$key]['fi'];
        }
        
        // Return key as last resort
        return $key;
    }
    
    private function renderText($image, int $x, int $y, int $maxWidth, int $maxHeight, string $text, string $color, int $fontSize, string $fontWeight = 'Regular'): void
{
    $rgb = $this->hexToRgb($color);
    $textColor = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);

    $fontFile = $this->fontsDir . '/OpenSans-' . $fontWeight . '.ttf';
    if (!file_exists($fontFile)) {
        $fontFile = $this->fontsDir . '/OpenSans-Regular.ttf';
    }
    $useTTF = file_exists($fontFile) && function_exists('imagettftext');

    // Normalisoi rivinvaihdot ja rajoita tyhjien rivien määrä
    $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    $measureWidth = function (int $fs, string $s) use ($useTTF, $fontFile): float {
        if ($useTTF) {
            $bbox = imagettfbbox($fs, 0, $fontFile, $s);
            return abs($bbox[2] - $bbox[0]);
        }
        return strlen($s) * ($fs * 0.6);
    };

    $wrapAll = function (int $fs) use ($text, $maxWidth, $measureWidth): array {
        $paragraphs = preg_split("/\n/", $text);
        $wrapped = [];

        foreach ($paragraphs as $para) {
            $para = (string)$para;

            if (trim($para) === '') {
                $wrapped[] = '';
                continue;
            }

            $words = preg_split('/\s+/', trim($para));
            $line = '';

            foreach ($words as $word) {
                $test = ($line === '') ? $word : ($line . ' ' . $word);
                if ($measureWidth($fs, $test) > $maxWidth && $line !== '') {
                    $wrapped[] = $line;
                    $line = $word;
                } else {
                    $line = $test;
                }
            }
            if ($line !== '') $wrapped[] = $line;
        }
        return $wrapped;
    };

    $minFont = 12;

    for ($fs = $fontSize; $fs >= $minFont; $fs--) {
        $lineHeight = (int)round($fs * 1.3);
        $maxLines = max(1, (int)floor($maxHeight / $lineHeight));

        $lines = $wrapAll($fs);

        if (count($lines) <= $maxLines) {
            $currentY = $y + $fs;
            foreach ($lines as $line) {
                if ($useTTF) {
                    imagettftext($image, $fs, 0, (int)$x, (int)$currentY, $textColor, $fontFile, $line);
                } else {
                    imagestring($image, 5, (int)$x, (int)($currentY - $fs), $line, $textColor);
                }
                $currentY += $lineHeight;
            }
            return;
        }
    }

    // Fallback: piirrä minimifontilla niin paljon kuin mahtuu (ei pitäisi osua 950 merkillä)
    $fs = $minFont;
    $lineHeight = (int)round($fs * 1.3);
    $maxLines = max(1, (int)floor($maxHeight / $lineHeight));
    $lines = array_slice($wrapAll($fs), 0, $maxLines);

    $currentY = $y + $fs;
    foreach ($lines as $line) {
        if ($useTTF) {
            imagettftext($image, $fs, 0, (int)$x, (int)$currentY, $textColor, $fontFile, $line);
        } else {
            imagestring($image, 5, (int)$x, (int)($currentY - $fs), $line, $textColor);
        }
        $currentY += $lineHeight;
    }
}
    
    private function renderGridBitmap($image, int $x, int $y, array $data): void
    {
        $gridBitmap = $data['grid_bitmap'] ?? '';
        if (empty($gridBitmap)) return;
        
        // Check if it's a base64 data URL
        if (strpos($gridBitmap, 'data:image/') === 0) {
            $this->renderBase64Image($image, $gridBitmap, $x, $y, self::GRID_BITMAP_SIZE, self::GRID_BITMAP_SIZE);
            return;
        }
        
        // Check if it's a file path / filename
        $fullPath = null;

        // 1) If absolute/relative path exists as-is, use it
        if (is_string($gridBitmap) && $gridBitmap !== '' && file_exists($gridBitmap)) {
            $fullPath = $gridBitmap;
        } else {
            // 2) Try common upload locations (match PreviewImageGenerator behaviour)
            $candidates = [
                // preferred location for grid bitmap
                $this->uploadsDir . '/grids/' . basename($gridBitmap),

                // fallback locations (older/other)
                $this->uploadsDir . '/' . basename($gridBitmap),
                $this->uploadsDir . '/images/' . basename($gridBitmap),
            ];

            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $fullPath = $candidate;
                    break;
                }
            }
        }

        // Validate that the file is within the uploads directory
        if ($fullPath && file_exists($fullPath)) {
            $realPath = realpath($fullPath);
            $realUploadsDir = realpath($this->uploadsDir);

            if ($realPath !== false && $realUploadsDir !== false && strpos($realPath, $realUploadsDir) === 0) {
                $srcImage = $this->loadImage($realPath);
                if ($srcImage) {
                    imagecopyresampled(
                        $image,
                        $srcImage,
                        $x,
                        $y,
                        0,
                        0,
                        self::GRID_BITMAP_SIZE,
                        self::GRID_BITMAP_SIZE,
                        imagesx($srcImage),
                        imagesy($srcImage)
                    );
                    imagedestroy($srcImage);
                }
                return;
            }
        }
        
        // Legacy JSON format
        $gridData = json_decode($gridBitmap, true);
        if (!$gridData || !is_array($gridData)) return;
        
        foreach ($gridData as $item) {
            $imagePath = $item['src'] ?? '';
            $itemX = (int)($item['x'] ?? 0);
            $itemY = (int)($item['y'] ?? 0);
            $itemWidth = (int)($item['width'] ?? 0);
            $itemHeight = (int)($item['height'] ?? 0);
            
            if (empty($imagePath) || $itemWidth <= 0 || $itemHeight <= 0) continue;
            
            $fullPath = $this->uploadsDir . '/' . basename($imagePath);
            if (file_exists($fullPath)) {
                $realPath = realpath($fullPath);
                $realUploadsDir = realpath($this->uploadsDir);
                
                if ($realPath !== false && $realUploadsDir !== false && strpos($realPath, $realUploadsDir) === 0) {
                    $srcImage = $this->loadImage($realPath);
                    if ($srcImage) {
                        imagecopyresampled($image, $srcImage, $x + $itemX, $y + $itemY, 0, 0, $itemWidth, $itemHeight, imagesx($srcImage), imagesy($srcImage));
                        imagedestroy($srcImage);
                    }
                }
            }
        }
    }
    
    private function renderBase64Image($image, string $dataUrl, int $x, int $y, int $width, int $height): void
    {
        // Extract and validate base64 data
        $parts = explode(',', $dataUrl, 2);
        if (count($parts) !== 2) return;
        
        // Validate MIME type
        $header = $parts[0];
        $allowedMimeTypes = ['data:image/jpeg', 'data:image/jpg', 'data:image/png', 'data:image/gif'];
        $isValidMimeType = false;
        foreach ($allowedMimeTypes as $mimeType) {
            if (strpos($header, $mimeType) === 0) {
                $isValidMimeType = true;
                break;
            }
        }
        if (!$isValidMimeType) return;
        
        $imageData = base64_decode($parts[1]);
        if ($imageData === false) return;
        
        $srcImage = imagecreatefromstring($imageData);
        if (!$srcImage) return;
        
        // Scale to fit the target area
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);
        
        imagecopyresampled($image, $srcImage, $x, $y, 0, 0, $width, $height, $srcWidth, $srcHeight);
        imagedestroy($srcImage);
    }
    
    private function loadImage(string $path)
    {
        $info = getimagesize($path);
        if (!$info) return null;
        
        switch ($info[2]) {
            case IMAGETYPE_JPEG: return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG: return imagecreatefrompng($path);
            case IMAGETYPE_GIF: return imagecreatefromgif($path);
            default: return null;
        }
    }
    
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
    
    private function calculateTextLines(string $text, int $maxWidth, int $fontSize, string $fontWeight = 'Regular'): int
    {
        if (empty($text)) return 1;
        
        $fontFile = $this->fontsDir . '/OpenSans-' . $fontWeight . '.ttf';
        if (!file_exists($fontFile)) {
            $fontFile = $this->fontsDir . '/OpenSans-Regular.ttf';
        }
        $useTTF = file_exists($fontFile) && function_exists('imagettftext');
        
        $lines = explode("\n", $text);
        $totalLines = 0;
        
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                $totalLines++;
                continue;
            }
            
            $words = explode(' ', $line);
            $currentLine = '';
            $lineCount = 1;
            
            foreach ($words as $word) {
                $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
                
                if ($useTTF) {
                    $bbox = imagettfbbox($fontSize, 0, $fontFile, $testLine);
                    $textWidth = abs($bbox[2] - $bbox[0]);
                } else {
                    $textWidth = strlen($testLine) * ($fontSize * 0.6);
                }
                
                if ($textWidth > $maxWidth && $currentLine !== '') {
                    $lineCount++;
                    $currentLine = $word;
                } else {
                    $currentLine = $testLine;
                }
            }
            $totalLines += $lineCount;
        }
        return max(1, $totalLines);
    }

    /**
     * Calculate optimal base size to fit content on single card
     * MUST MATCH PreviewImageGenerator.php logic exactly
     */
    private function calculateOptimalBaseSize(array $data): int
    {
        return $this->calculateOptimalBaseSizeFrom($data, self::FONT_SIZE_AUTO_MAX);
    }

    /**
     * Calculate optimal base size starting from a maximum
     * Tries progressively smaller sizes until content fits
     * MUST MATCH PreviewImageGenerator.php logic exactly
     */
    private function calculateOptimalBaseSizeFrom(array $data, int $maxBase): int
    {
        $title = trim((string) ($data['short_text'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $rootCauses = trim((string) ($data['root_causes'] ?? ''));
        $actions = trim((string) ($data['actions'] ?? ''));
        
        for ($baseSize = $maxBase; $baseSize >= self::FONT_SIZE_AUTO_MIN; $baseSize -= self::FONT_SIZE_AUTO_STEP) {
            $sizes = $this->calculateAllFontSizes($baseSize);
            
            if ($this->contentFitsOnSingleCard($title, $description, $rootCauses, $actions, $sizes)) {
                return $baseSize;
            }
        }
        
        return self::FONT_SIZE_AUTO_MIN;
    }

    /**
     * Calculate all font sizes from base size using ratios
     * MUST MATCH PreviewImageGenerator.php logic exactly
     */
    private function calculateAllFontSizes(int $baseSize): array
    {
        return [
            'title' => (int) round($baseSize * self::FONT_RATIOS['shortTitle']),
            'description' => (int) round($baseSize * self::FONT_RATIOS['description']),
            'content' => (int) round($baseSize * self::FONT_RATIOS['content']),
        ];
    }

    /**
     * Check if content fits on single card with given font sizes
     * MUST MATCH PreviewImageGenerator.php logic exactly
     */
    private function contentFitsOnSingleCard(string $title, string $description, string $rootCauses, string $actions, array $sizes): bool
    {
        $descLines = $this->estimateLinesWithFontSize($description, self::CARD1_DESC_WIDTH, $sizes['description']);
        $descHeight = $descLines * ($sizes['description'] * 1.35);
        
        if ($descHeight > self::CARD1_DESC_MAX_HEIGHT) {
            return false;
        }
        
        $contentFontSize = $sizes['content'];
        $rootLines = $this->estimateLinesWithFontSize($rootCauses, self::COLUMN_WIDTH, $contentFontSize);
        $actionsLines = $this->estimateLinesWithFontSize($actions, self::COLUMN_WIDTH, $contentFontSize);
        
        $rootHeight = $rootLines * ($contentFontSize * 1.35);
        $actionsHeight = $actionsLines * ($contentFontSize * 1.35);
        $maxColumnHeight = max($rootHeight, $actionsHeight);
        
        $totalHeight = $descHeight + $maxColumnHeight + self::HEADERS_SPACING;
        
        return $totalHeight <= self::SINGLE_CARD_MAX_HEIGHT;
    }

    /**
     * Estimate lines with specific font size and width
     * MUST MATCH PreviewImageGenerator.php logic exactly
     */
    private function estimateLinesWithFontSize(string $text, int $maxWidth, int $fontSize): int
    {
        if (empty($text)) {
            return 0;
        }
        
        $charsPerLine = (int) floor($maxWidth / ($fontSize * self::CHAR_WIDTH_RATIO));
        $lines = 0;
        
        foreach (explode("\n", $text) as $paragraph) {
            $trimmed = trim($paragraph);
            if ($trimmed === '') continue;
            
            $lines += max(1, (int) ceil(mb_strlen($trimmed) / $charsPerLine));
        }
        
        return $lines;
    }

    /**
     * Get font sizes based on override or auto calculation
     * MUST MATCH PreviewImageGenerator.php logic
     */
    private function getFontSizes(array $data): array
    {
        $override = $data['font_size_override'] ?? null;
        
        if ($override && isset(self::FONT_PRESETS[$override])) {
            $maxBase = self::FONT_PRESETS[$override];
        } else {
            $maxBase = self::FONT_SIZE_AUTO_MAX;
        }
        
        // Try from selected size down until content fits
        $baseSize = $this->calculateOptimalBaseSizeFrom($data, $maxBase);
        return $this->calculateAllFontSizes($baseSize);
    }
}