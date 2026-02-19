<?php
/**
 * PreviewImageGenerator - Server-side preview image generation using Imagick
 * 
 * Generates 1920x1080 JPEG preview images from SafetyFlash data
 * Uses pre-designed template backgrounds and overlays text and images using Imagick
 * 
 * @package SafetyFlash
 * @subpackage Services
 */

declare(strict_types=1);

class PreviewImageGenerator
{
    private const WIDTH = 1920;
    private const HEIGHT = 1080;
    private const QUALITY = 85;
    
    // Color definitions
    private const COLORS = [
        'yellow' => '#FEE000',
        'red' => '#C81E1E',
        'green' => '#009650',
        'black' => '#000000',
        'white' => '#FFFFFF',
        'gray_light' => '#F0F0F0',
        'gray_dark' => '#3C3C3C',
        'black_box' => '#1a1a1a',  // For header boxes and backgrounds
    ];
    
    // --- YLEISET MARGINAALIT ---
    private const LEFT_MARGIN = 116; 
    private const START_Y = 220;     // Mustan yläpalkin alku
    
    // 1. LYHYT KUVAUS (Musta laatikko)
    // Kuvassa: 920 x 100 px
    private const TITLE_X = 116;
    private const TITLE_Y = 220;
    private const TITLE_WIDTH = 920;
    private const TITLE_HEIGHT = 100;
    
    // 2. PITKÄ KUVAUS (Ylempi sininen laatikko)
    // Kuvassa: 920 x 225 px
    private const DESC_X = 116;
    private const DESC_Y = 320;      // 220 + 100
    private const DESC_WIDTH = 920;
    private const DESC_HEIGHT = 225;
    
    // 3. JAETTU ALUE (Juurisyyt & Toimenpiteet)
    // Sijainti: Pitkän kuvauksen alla
    // Laskettu Y: 320 (alku) + 225 (korkeus) + 20 (väli) = 565
    private const SPLIT_Y = 565;
    private const SPLIT_WIDTH = 450; // Sama leveys kuin meta-laatikoilla
    private const SPLIT_HEIGHT = 290; // Tila ennen meta-laatikoita
    
    private const ROOT_X = 116;           // Vasen palsta
    private const ACTION_X = 586;         // Oikea palsta (116 + 450 + 20px väli)
    
    // 4. METATIEDOT (Harmaat laatikot alhaalla)
    // Kuvassa: 450 x 115 px
    // Sijainti: 90px alareunasta -> Y = 1080 - 90 - 115 = 875
    private const META_Y = 875;
    private const META_BOX_WIDTH = 450;
    private const META_BOX_HEIGHT = 115;
    private const META_BOX1_X = 116;   // Paikka/TYÖMAA
    private const META_BOX2_X = 586;  // Oikea meta-laatikko (116 + 450 + 20px väli)
    private const META_LABEL_SIZE = 18;
    private const META_VALUE_SIZE = 22;
    private const META_VALUE_OFFSET = 30;
    private const META_PADDING_LEFT = 15;   // Internal left padding
    private const META_PADDING_TOP = 20;    // Internal top padding
    private const META_TEXT_WRAP = 25;  // Max characters per line in meta box
    private const META_LINE_HEIGHT = 22;  // Line height for wrapped meta text
    private const META_MAX_LINES = 3;  // Maximum lines for meta text to prevent overflow
    // Gray background for meta boxes - hex color with alpha
    private const META_BG_COLOR = '#D2D2D2';  // rgb(210,210,210)
    private const META_BG_OPACITY = 0.85;
    
    // 5. KUVA-ALUE (Pinkki)
    private const IMAGE_X = 1086; // 116 + 920 + 50
    private const IMAGE_Y = 220;
    private const IMAGE_WIDTH = 750;
    private const IMAGE_HEIGHT = 750;
    
    // --- FONTIT ---
    private const FONT_TITLE = 42;    // Mahtuu 100px korkeuteen
    private const FONT_BODY = 28;     // Mahtuu hyvin 225px ja jaettuihin laatikoihin
    private const LINE_HEIGHT = 36;
    
    // Split view layout padding and spacing
    private const SPLIT_VIEW_PADDING = 20;  // Internal padding for content boxes
    private const SPLIT_VIEW_PADDING_SMALL = 15;  // Smaller padding for section content
    private const SPLIT_VIEW_HEADER_HEIGHT = 40;  // Height of section headers (Root Causes, Actions)
    private const SPLIT_VIEW_HEADER_OFFSET = 5;  // Vertical offset for header text positioning
    private const SPLIT_VIEW_TOP_PADDING = 10;  // Top padding for description and section content
    private const SPLIT_VIEW_LINE_HEIGHT_MULTIPLIER = 1.3;  // Line height multiplier for content sections
    private const SPLIT_VIEW_META_BUFFER = 20;  // Buffer space between content and meta boxes to prevent overlap
    private const SPLIT_VIEW_MIN_CONTENT_LINES = 5;  // Minimum lines for root causes/actions content
    private const SPLIT_VIEW_MAX_CONTENT_LINES = 10;  // Maximum lines for root causes/actions content
    
    // Legacy constants for backward compatibility with existing layouts
    private const TITLE_FONT_SIZE = 38;  // Fits in 160px height
    private const TITLE_LINE_HEIGHT = 45;
    private const DESC_FONT_SIZE = 26;
    private const DESC_LINE_HEIGHT = 30;
    private const DESC_WRAP = 75;  // Characters per line at 26px font in 920px width
    
    // Character limits for single slide green type (matching capture.js logic)
    private const CHAR_LIMIT_SINGLE_SLIDE = 900;  // Total character limit
    private const ROOT_CAUSES_SINGLE_LIMIT = 500;  // Root causes field limit
    private const ACTIONS_SINGLE_LIMIT = 500;      // Actions field limit
    private const DESC_SINGLE_LIMIT = 400;         // Description field limit
    private const ROOT_CAUSES_ACTIONS_COMBINED_LIMIT = 800;  // Combined root causes + actions limit
    
    // Line-based calculation constants for better accuracy
    private const MAX_COLUMN_LINES = 14;  // Max lines that fit in a column on single-slide layout
    private const CHARS_PER_LINE = 45;    // Average characters per line
    
    // Font size ratios (proportional scaling) - same as JavaScript
    private const FONT_RATIOS = [
        'shortTitle' => 1.6,
        'description' => 1.0,
        'rootCauses' => 0.9,
        'actions' => 0.9,
    ];

    // Preset sizes (base size for description) - same as JavaScript
    private const FONT_PRESETS = [
        'XS' => 14,
        'S' => 16,
        'M' => 18,
        'L' => 20,
        'XL' => 22,
    ];

    // Font size calculation constants - same as JavaScript
    private const FONT_SIZE_AUTO_MAX = 22;  // Maximum base size for auto mode
    private const FONT_SIZE_AUTO_MIN = 12;  // Minimum base size for auto mode
    private const FONT_SIZE_AUTO_STEP = 1;  // Step size when searching for optimal size

    // Layout constraint constants for card fitting calculations - same as JavaScript
    private const CARD1_DESC_MAX_HEIGHT = 420;   // Max height for description on card 1
    private const CARD1_DESC_WIDTH = 880;        // Width for description text (TEXT_COL_WIDTH 920 - 40 padding)
    private const COLUMN_MAX_HEIGHT = 400;       // Max height for root causes/actions columns
    private const COLUMN_WIDTH = 420;            // Width for columns ((920-20)/2 - 30 padding)
    private const HEADERS_SPACING = 100;         // Extra space for headers and spacing (header boxes + gaps)
    private const SINGLE_CARD_MAX_HEIGHT = 850;  // Total max height for single card
    private const CHAR_WIDTH_RATIO = 0.48;       // Approximate character width as ratio of font size
                                                  // (calibrated for Open Sans font - actual average ~0.48)
    
    private ?PDO $pdo;
    private string $uploadsDir;
    private string $previewsDir;
    private string $templatesDir;
    
    public function __construct(?PDO $pdo, string $uploadsDir, string $previewsDir)
    {
        $this->pdo = $pdo;
        $this->uploadsDir = rtrim($uploadsDir, '/');
        $this->previewsDir = rtrim($previewsDir, '/');
        $this->templatesDir = dirname(__DIR__, 2) . '/assets/img/templates';
        
        if (!extension_loaded('imagick')) {
            throw new RuntimeException('Imagick extension is not loaded');
        }
        
        if (!is_dir($this->previewsDir)) {
            @mkdir($this->previewsDir, 0755, true);
        }
        
        if (!is_dir($this->templatesDir)) {
            throw new RuntimeException('Templates directory not found: ' . $this->templatesDir);
        }
    }
    
    /**
     * Generate preview image for a flash
     * 
     * @param array $flashData Flash data from database
     * @return array|string|null Returns array with both filenames for two-card generation:
     *                           ['filename1' => 'card1.jpg', 'filename2' => 'card2.jpg']
     *                           Returns string for single card generation: 'card.jpg'
     *                           Returns null on failure
     */
    public function generate(array $flashData): array|string|null
    {
        try {
            $flashId = (int) ($flashData['id'] ?? 0);
            if ($flashId <= 0) {
                throw new RuntimeException('Invalid flash ID');
            }
            
            $type = $flashData['type'] ?? 'yellow';
            $lang = $flashData['lang'] ?? 'fi';
            
            // Improved logging
            error_log("PreviewImageGenerator: Starting generation for flash {$flashId}, type={$type}, lang={$lang}");
            
            // Check if green type needs two cards
            $needsSecondCard = ($type === 'green' && $this->needsSecondCard($flashData));
            error_log("PreviewImageGenerator: needsSecondCard=" . ($needsSecondCard ? 'true' : 'false'));
            
            // Generate filename(s) based on flash data
            $filename1 = $this->generateFilename($flashData, 1);
            $outputPath1 = $this->previewsDir . '/' . $filename1;
            
            // Get template path with fallback for missing two-card templates
            try {
                $templatePath1 = $this->getTemplatePath($type, $lang, $needsSecondCard ? 1 : null);
            } catch (RuntimeException $e) {
                error_log("PreviewImageGenerator: Two-card template not found, falling back to single-card: " . $e->getMessage());
                // Fallback to single-card template
                $templatePath1 = $this->getTemplatePath($type, $lang, null);
                $needsSecondCard = false;
            }
            
            error_log("PreviewImageGenerator: Using template: {$templatePath1}");
            
            // Render card 1
            $this->renderCard($flashData, $templatePath1, $outputPath1, 1);
            
            // Verify file was created
            if (!file_exists($outputPath1)) {
                throw new RuntimeException('Output file was not created: ' . $outputPath1);
            }
            
            error_log("PreviewImageGenerator: Card 1 generated successfully: {$filename1}");
            
            // If green type needs second card, generate it
            if ($needsSecondCard) {
                $filename2 = $this->generateFilename($flashData, 2);
                $outputPath2 = $this->previewsDir . '/' . $filename2;
                
                try {
                    $templatePath2 = $this->getTemplatePath($type, $lang, 2);
                } catch (RuntimeException $e) {
                    error_log("PreviewImageGenerator: Card 2 template not found: " . $e->getMessage());
                    // Return only card 1 if card 2 template is missing
                    return $filename1;
                }
                
                $this->renderCard($flashData, $templatePath2, $outputPath2, 2);
                
                if (!file_exists($outputPath2)) {
                    error_log("PreviewImageGenerator: Card 2 file was not created, returning only card 1");
                    return $filename1;
                }
                
                error_log("PreviewImageGenerator: Card 2 generated successfully: {$filename2}");
                
                // Return BOTH filenames as array
                return [
                    'filename1' => $filename1,
                    'filename2' => $filename2
                ];
            }
            
            return $filename1;
            
        } catch (Throwable $e) {
            error_log('PreviewImageGenerator::generate failed for flash ' . ($flashData['id'] ?? 'unknown') . ': ' . $e->getMessage());
            error_log('PreviewImageGenerator::generate stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Generate descriptive filename for preview
     */
    private function generateFilename(array $flashData, int $cardNumber = 1): string
    {
        $site = $flashData['site'] ?? 'Site';
        $title = $flashData['title_short'] ?? $flashData['summary'] ?? 'Flash';
        $lang = strtoupper($flashData['lang'] ?? 'FI');
        $type = strtoupper($flashData['type'] ?? 'YELLOW');
        
        $occurredAt = $flashData['occurred_at'] ?? null;
        $date = $occurredAt ? date('Y_m_d', strtotime($occurredAt)) : date('Y_m_d');
        
        // Sanitize for filename - transliterate unicode to ASCII if possible
        $siteSafe = $this->sanitizeFilename($site, 30);
        $titleSafe = $this->sanitizeFilename($title, 50);
        
        if (trim($siteSafe) === '') $siteSafe = 'Site';
        if (trim($titleSafe) === '') $titleSafe = 'Flash';
        
        $cardSuffix = $cardNumber > 1 ? "_{$cardNumber}" : '';
        
        return "SF_{$date}_{$type}_{$siteSafe}-{$titleSafe}-{$lang}{$cardSuffix}.jpg";
    }
    
    /**
     * Sanitize string for use in filename
     */
    private function sanitizeFilename(string $text, int $maxLength): string
    {
        // Try to transliterate unicode characters to ASCII
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        }
        
        // Remove any remaining non-alphanumeric characters (except dash and underscore)
        $text = preg_replace('/[^a-zA-Z0-9\-_]/', '', $text);
        
        return substr($text, 0, $maxLength);
    }
    
    /**
     * Get template path for given type, language, and card number
     */
    private function getTemplatePath(string $type, string $lang, ?int $cardNumber): string
    {
        if ($type === 'green' && $cardNumber !== null) {
            // Two-card green model
            $filename = "SF_bg_green_{$cardNumber}_{$lang}.jpg";
        } else {
            // Single card (red, yellow, or single-slide green)
            $filename = "SF_bg_{$type}_{$lang}.jpg";
        }
        
        $path = $this->templatesDir . '/' . $filename;
        
        if (!file_exists($path)) {
            throw new RuntimeException("Template not found: {$filename}");
        }
        
        return $path;
    }
    
    /**
     * Estimate the number of lines needed to display text
     * Takes into account line breaks (bullets) and text wrapping
     * @param string $text Text to estimate
     * @param int $charsPerLine Average characters per line
     * @return int Estimated number of lines
     */
    private function estimateLines(string $text, int $charsPerLine = self::CHARS_PER_LINE): int
    {
        if (empty($text)) {
            return 0;
        }
        
        $lines = 0;
        $paragraphs = explode("\n", $text);
        
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            
            // Each paragraph/bullet point is at least 1 line
            // Additional lines based on character count
            $lines += max(1, (int)ceil(mb_strlen($p) / $charsPerLine));
        }
        
        return $lines;
    }
    
    /**
     * Calculate all font sizes from base size using ratios
     */
    private function calculateFontSizes(int $baseSize): array
    {
        return [
            'shortTitle' => (int) round($baseSize * self::FONT_RATIOS['shortTitle']),
            'description' => (int) round($baseSize * self::FONT_RATIOS['description']),
            'rootCauses' => (int) round($baseSize * self::FONT_RATIOS['rootCauses']),
            'actions' => (int) round($baseSize * self::FONT_RATIOS['actions']),
        ];
    }

    /**
     * Get font sizes based on user preference or auto-calculation
     */
    private function getFontSizes(array $flashData): array
    {
        $override = $flashData['font_size_override'] ?? null;
        
        if ($override && isset(self::FONT_PRESETS[$override])) {
            $maxBase = self::FONT_PRESETS[$override];
        } else {
            $maxBase = self::FONT_SIZE_AUTO_MAX;
        }
        
        // Try from selected size down until content fits
        $baseSize = $this->calculateOptimalBaseSizeFrom($flashData, $maxBase);
        return $this->calculateFontSizes($baseSize);
    }

    /**
     * Calculate optimal base size to fit content on single card
     */
    private function calculateOptimalBaseSize(array $flashData): int
    {
        return $this->calculateOptimalBaseSizeFrom($flashData, self::FONT_SIZE_AUTO_MAX);
    }

    /**
     * Calculate optimal base size starting from a maximum
     * Tries progressively smaller sizes until content fits
     */
    private function calculateOptimalBaseSizeFrom(array $flashData, int $maxBase): int
    {
        $title = trim((string) ($flashData['title_short'] ?? ''));
        $description = trim((string) ($flashData['description'] ?? ''));
        $rootCauses = trim((string) ($flashData['root_causes'] ?? ''));
        $actions = trim((string) ($flashData['actions'] ?? ''));
        
        // Try from largest to smallest
        for ($baseSize = $maxBase; $baseSize >= self::FONT_SIZE_AUTO_MIN; $baseSize -= self::FONT_SIZE_AUTO_STEP) {
            $sizes = $this->calculateFontSizes($baseSize);
            
            if ($this->contentFitsOnSingleCard($title, $description, $rootCauses, $actions, $sizes)) {
                return $baseSize;
            }
        }
        
        return self::FONT_SIZE_AUTO_MIN; // Minimum
    }

    /**
     * Check if content fits on single card with given font sizes
     */
    private function contentFitsOnSingleCard(
        string $title,
        string $description, 
        string $rootCauses,
        string $actions,
        array $sizes
    ): bool {
        // Estimate description height
        $descLines = $this->estimateLinesWithFontSize($description, self::CARD1_DESC_WIDTH, $sizes['description']);
        $descHeight = $descLines * ($sizes['description'] * 1.35);
        
        if ($descHeight > self::CARD1_DESC_MAX_HEIGHT) {
            return false;
        }
        
        // Estimate root causes / actions height
        $rootLines = $this->estimateLinesWithFontSize($rootCauses, self::COLUMN_WIDTH, $sizes['rootCauses']);
        $actionsLines = $this->estimateLinesWithFontSize($actions, self::COLUMN_WIDTH, $sizes['actions']);
        
        $rootHeight = $rootLines * ($sizes['rootCauses'] * 1.35);
        $actionsHeight = $actionsLines * ($sizes['actions'] * 1.35);
        $maxColumnHeight = max($rootHeight, $actionsHeight);
        
        // Check total height
        $totalHeight = $descHeight + $maxColumnHeight + self::HEADERS_SPACING;
        
        return $totalHeight <= self::SINGLE_CARD_MAX_HEIGHT;
    }

    /**
     * Estimate lines with specific font size and width
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
     * Check if green type needs second card
     * Matches logic from capture.js for consistency between client and server
     * 
     * Decision is based on whether content fits on one card:
     * - Total character count across all fields
     * - Individual field limits (description, root causes, actions)
     * 
     * If ANY limit is exceeded, two cards are needed.
     */
    private function needsSecondCard(array $flashData): bool
    {
        $shortTitle = trim((string) ($flashData['title_short'] ?? ''));
        $description = trim((string) ($flashData['description'] ?? ''));
        $rootCauses = trim((string) ($flashData['root_causes'] ?? ''));
        $actions = trim((string) ($flashData['actions'] ?? ''));
        
        // Get font sizes (respecting user override if present)
        $sizes = $this->getFontSizes($flashData);
        
        // Check if content fits with calculated font sizes
        return !$this->contentFitsOnSingleCard($shortTitle, $description, $rootCauses, $actions, $sizes);
    }
    
    /**
     * Public wrapper for needsSecondCard - used by process_flash_worker.php
     * 
     * @param array $flashData Flash data with keys: title_short, description, root_causes, actions, font_size_override
     * @return bool True if two cards are needed, false otherwise
     */
    public function needsSecondCardPublic(array $flashData): bool
    {
        return $this->needsSecondCard($flashData);
    }
    
    /**
     * Render a card using template background and Imagick text overlay
     */
    private function renderCard(array $flashData, string $templatePath, string $outputPath, int $cardNumber): void
    {
        $imagick = new Imagick($templatePath);
        
        try {
            $type = $flashData['type'] ?? 'yellow';
            $lang = $flashData['lang'] ?? 'fi';
            
            // Extract data
            $title = $flashData['title_short'] ?? $flashData['summary'] ?? '';
            $description = $flashData['description'] ?? '';
            $site = $flashData['site'] ?? '';
            $siteDetail = $flashData['site_detail'] ?? '';
            $occurredAt = $flashData['occurred_at'] ?? null;
            $rootCauses = $this->formatBulletPoints(trim((string) ($flashData['root_causes'] ?? '')));
            $actions = $this->formatBulletPoints(trim((string) ($flashData['actions'] ?? '')));
            
            // Format site text
            $siteText = $site;
            if ($siteDetail) {
                $siteText .= ' – ' . $siteDetail;
            }
            if (!$siteText) $siteText = '–';
            
            // Format date - handle ISO 8601 format properly
            $dateText = '–';
            if ($occurredAt) {
                $tz = new DateTimeZone('Europe/Helsinki');
                
                // Try multiple date formats - parse as local Helsinki time
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $occurredAt, $tz)
                    ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $occurredAt, $tz)
                    ?: DateTime::createFromFormat('Y-m-d H:i:s', $occurredAt, $tz)
                    ?: DateTime::createFromFormat('Y-m-d H:i', $occurredAt, $tz);
                
                if (!$dt) {
                    // Fallback to strtotime if none of the formats match
                    $ts = strtotime($occurredAt);
                    if ($ts !== false) {
                        $dt = new DateTime('@' . $ts);
                        $dt->setTimezone($tz);
                    }
                }
                
                if ($dt) {
                    $dateText = $dt->format('d.m.Y H:i');
                }
            }
            
            // Get labels
            $labels = $this->getLabels($lang);
            $siteLabel = $labels['site'];
            $dateLabel = $labels['date'];
            
            if ($cardNumber === 2) {
                // Card 2: Root causes and actions
                $this->renderCard2($imagick, $title, $rootCauses, $actions, $labels);
            } elseif ($type === 'green' && $cardNumber === 1 && !$this->needsSecondCard($flashData)) {
                // Card 1 for green type that fits on single card - use split view layout
                $this->renderInvestigationSplitView(
                    $imagick,
                    $title,
                    $description,
                    $rootCauses,
                    $actions,
                    $siteText,
                    $dateText,
                    $siteLabel,
                    $dateLabel,
                    $labels,
                    $flashData
                );
            } else {
                // Card 1: Title, description, meta, and image (standard layout)
                $this->renderCard1($imagick, $title, $description, $siteText, $dateText, $siteLabel, $dateLabel, $flashData);
            }
            
            // Save to JPEG
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(self::QUALITY);
            $imagick->writeImage($outputPath);
            
        } finally {
            $imagick->clear();
            $imagick->destroy();
        }
    }
    
    /**
     * Render card 1 content (title, description, meta, image)
     */
    private function renderCard1(
        Imagick $imagick,
        string $title,
        string $description,
        string $siteText,
        string $dateText,
        string $siteLabel,
        string $dateLabel,
        array $flashData
    ): void {
        // Draw title (bold) with dynamic font size
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Bold'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['black']));
        
        // Calculate total content length and get font sizes
        $totalLength = $this->calculateTotalContentLength($flashData);
        $fontSizes = $this->getFontSizesByTotalLength($totalLength);
        $titleFontSize = $fontSizes['title'];
        $descFontSize = $fontSizes['description'];
        
        $draw->setFontSize($titleFontSize);
        // Calculate line width based on font size (920px width)
        $titleLines = $this->wrapText($title, (int)(self::TITLE_WIDTH / ($titleFontSize * 0.5)));
        $titleY = self::TITLE_Y;
        foreach ($titleLines as $i => $line) {
            $y = $titleY + ($i * ($titleFontSize + 7));  // Dynamic line height
            $imagick->annotateImage($draw, self::TITLE_X, $y, 0, $line);
        }
        
        // Calculate dynamic description Y position based on title height
        $titleHeight = count($titleLines) * ($titleFontSize + 7);
        $descY = $titleY + $titleHeight + 25;  // 25px gap after title
        
        // Draw description (regular) - ALWAYS FIT (no truncation, auto font downscale)
        $draw->setFont($this->getFont('Regular'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['gray_dark']));
        
        // $descFontSize is already calculated from total length above
        
        $draw->setFontSize($descFontSize);

        // Normalisoi rivinvaihdot ja rajoita tyhjien rivien määrä (ettei tila lopu “turhaan”)
        $description = str_replace(["\r\n", "\r"], "\n", (string)$description);
        $description = preg_replace("/\n{3,}/", "\n\n", $description);

        // Laske montako riviä mahtuu ennen meta-aluetta
        $bottomLimitY = self::META_Y - 10;
        $availableHeight = max(0, $bottomLimitY - $descY);
        $maxDescLines = (int) floor($availableHeight / ($descFontSize * 1.3));
        $maxDescLines = max(6, min($maxDescLines, 30));

        // Piirrä aina kokonaan (pienennä fonttia tarvittaessa)
        $this->drawWrappedText(
            $imagick,
            $draw,
            (string)$description,
            self::DESC_X,
            $descY,
            self::DESC_WIDTH,
            $descFontSize,
            (int)($descFontSize * 1.3),
            $maxDescLines
        );
        
        // Draw meta info (site and date) at fixed position
        $this->renderMetaInfo($imagick, $siteLabel, $siteText, $dateLabel, $dateText, self::META_Y);
        
        // Composite grid bitmap image
        $this->compositeImage($imagick, $flashData);
    }
    
    /**
     * Render card 2 content (root causes and actions)
     */
    private function renderCard2(
        Imagick $imagick,
        string $title,
        string $rootCauses,
        string $actions,
        array $labels
    ): void {
        // Layout measurements from design specification
        $descBoxX = 95;
        $descBoxY = 247;
        $descBoxW = 1730;
        $descBoxH = 130;
        
        $headerY = 390;
        $headerH = 45;
        $leftColX = 95;
        $rightColX = 970;
        $colW = 845;
        $gap = 30; // Gap between columns
        
        $headerContentGap = 10; // Gap between header and content
        $contentY = $headerY + $headerH + $headerContentGap;
        $contentH = 540;
        
        // 1. Draw short description box with black background
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel(self::COLORS['black_box']));
        $draw->rectangle($descBoxX, $descBoxY, $descBoxX + $descBoxW, $descBoxY + $descBoxH);
        $imagick->drawImage($draw);
        
        // Draw title_short text (white on black) - vertically centered with dynamic font size
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Bold'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['white']));
        
        // Dynamic title font size: < 50 chars → 38px, < 80 → 36px, < 120 → 34px, >= 120 → 32px
        $titleFontSize = $this->getDynamicFontSize(mb_strlen($title), [
            50 => 38,
            80 => 36,
            120 => 34,
        ], 32);
        
        $draw->setFontSize($titleFontSize);
        $titleLines = $this->wrapText($title, 90);
        // Center text vertically in box
        $titleY = $descBoxY + ($descBoxH + $titleFontSize) / 2;
        foreach (array_slice($titleLines, 0, 2) as $i => $line) {
            $y = $titleY + ($i * ($titleFontSize + 7));
            $imagick->annotateImage($draw, $descBoxX + 20, $y, 0, $line);
        }
        
        // 2. Draw black header bars for columns
        // Left header "Juurisyyt" / "Root Causes"
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel(self::COLORS['black_box']));
        $draw->rectangle($leftColX, $headerY, $leftColX + $colW, $headerY + $headerH);
        $imagick->drawImage($draw);
        
        // Right header "Toimenpiteet" / "Actions"
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel(self::COLORS['black_box']));
        $draw->rectangle($rightColX, $headerY, $rightColX + $colW, $headerY + $headerH);
        $imagick->drawImage($draw);
        
        // 3. Draw header text (white on black, 18px Bold)
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Bold'));
        $draw->setFontSize(18);  // Header font size: 18px
        $draw->setFillColor(new ImagickPixel(self::COLORS['white']));
        
        // Left header text
        $imagick->annotateImage($draw, $leftColX + 20, $headerY + 30, 0, $labels['root_causes']);
        
        // Right header text
        $imagick->annotateImage($draw, $rightColX + 20, $headerY + 30, 0, $labels['actions']);
        
        // 4. Draw content areas (black text on light background from template)
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Regular'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['black']));
        
        // Yhtenäinen fonttikoko molemmille (pienempi näistä)
        $combinedLength = mb_strlen($rootCauses) + mb_strlen($actions);
        $contentFontSize = $this->getDynamicFontSize($combinedLength, [
            200 => 22,
            400 => 20,
        ], 18);
        
        $draw->setFontSize($contentFontSize);
        
        // Root causes - content (left column)
        $rootLines = $this->wrapText($rootCauses, 45);
        $rootY = $contentY;
        foreach (array_slice($rootLines, 0, 22) as $i => $line) {
            $y = $rootY + ($i * (int)($contentFontSize * 1.3));
            $imagick->annotateImage($draw, $leftColX + 20, $y, 0, $line);
        }
        
        // Actions - content (right column)
        $actionLines = $this->wrapText($actions, 45);
        $actionY = $contentY;
        foreach (array_slice($actionLines, 0, 22) as $i => $line) {
            $y = $actionY + ($i * (int)($contentFontSize * 1.3));
            $imagick->annotateImage($draw, $rightColX + 20, $y, 0, $line);
        }
    }
    
    /**
     * Render meta information (site and date) with gray background boxes
     */
    private function renderMetaInfo(
        Imagick $imagick,
        string $siteLabel,
        string $siteText,
        string $dateLabel,
        string $dateText,
        int $metaY
    ): void {
        $draw = new ImagickDraw();
        
        // Define box dimensions from constants
        $boxWidth = self::META_BOX_WIDTH;
        $boxHeight = self::META_BOX_HEIGHT;
        $boxRadius = 8;
        $boxY = $metaY - 25; // Start above the label
        
        // Draw gray background for site box (Paikka)
        $draw->setFillColor(new ImagickPixel(self::META_BG_COLOR));
        $draw->setFillOpacity(self::META_BG_OPACITY);
        $draw->setStrokeOpacity(0);
        $draw->roundRectangle(
            self::META_BOX1_X - 10,           // x1
            $boxY,                             // y1
            self::META_BOX1_X - 10 + $boxWidth, // x2
            $boxY + $boxHeight,                // y2
            $boxRadius,                        // rx
            $boxRadius                         // ry
        );
        
        // Draw gray background for date box (Aika)
        $draw->roundRectangle(
            self::META_BOX2_X - 10,            // x1
            $boxY,                             // y1
            self::META_BOX2_X - 10 + $boxWidth, // x2
            $boxY + $boxHeight,                // y2
            $boxRadius,                        // rx
            $boxRadius                         // ry
        );
        
        // Apply the backgrounds
        $imagick->drawImage($draw);
        
        // Now draw the text (create new draw object for text)
        $draw = new ImagickDraw();
        
        // Apply padding to text positions
        $textX1 = self::META_BOX1_X + self::META_PADDING_LEFT;
        $textX2 = self::META_BOX2_X + self::META_PADDING_LEFT;
        $textYLabel = $metaY + self::META_PADDING_TOP;
        $textYValue = $textYLabel + self::META_VALUE_OFFSET;
        
        // Site box - label (bold, uppercase, 16px)
        $draw->setFont($this->getFont('Bold'));
        $draw->setFontSize(16);  // Meta label: 16px Bold
        $draw->setFillColor(new ImagickPixel(self::COLORS['gray_dark']));
        $imagick->annotateImage($draw, $textX1, $textYLabel, 0, strtoupper($siteLabel));
        
        // Site box - value (regular, 22px) with text wrapping
        $draw->setFont($this->getFont('Regular'));
        $draw->setFontSize(22);  // Meta value: 22px Regular
        $draw->setFillColor(new ImagickPixel(self::COLORS['black']));
        
        // Wrap text if it exceeds max characters per line
        $locationLines = $this->wrapText($siteText, self::META_TEXT_WRAP);
        
        // Draw each line (limit to prevent overflow)
        $lineY = $textYValue;
        foreach (array_slice($locationLines, 0, self::META_MAX_LINES) as $line) {
            $imagick->annotateImage($draw, $textX1, $lineY, 0, $line);
            $lineY += self::META_LINE_HEIGHT;
        }
        
        // Date box - label (bold, uppercase, 16px)
        $draw->setFont($this->getFont('Bold'));
        $draw->setFontSize(16);  // Meta label: 16px Bold
        $draw->setFillColor(new ImagickPixel(self::COLORS['gray_dark']));
        $imagick->annotateImage($draw, $textX2, $textYLabel, 0, strtoupper($dateLabel));
        
        // Date box - value (regular, 22px)
        $draw->setFont($this->getFont('Regular'));
        $draw->setFontSize(22);  // Meta value: 22px Regular
        $draw->setFillColor(new ImagickPixel(self::COLORS['black']));
        $imagick->annotateImage($draw, $textX2, $textYValue, 0, $dateText);
    }
    
    /**
     * Composite grid bitmap or images onto the canvas (single large image on right side)
     */
    private function compositeImage(Imagick $imagick, array $flashData): void
    {
        // Get the primary image to display (grid_bitmap or first available image)
        $imageData = $this->getPrimaryImage($flashData);
        
        if (empty($imageData)) {
            return;
        }
        
        try {
            // Create image from data
            $imageImagick = new Imagick();
            
            if (strpos($imageData, 'data:image/') === 0) {
                // Base64 data URL
                $imageImagick->readImageBlob(base64_decode(explode(',', $imageData)[1]));
            } else {
                // File path
                $imageImagick->readImage($imageData);
            }
            
            // Resize to fit the image area (750x750) while maintaining aspect ratio
            $imageImagick->thumbnailImage(self::IMAGE_WIDTH, self::IMAGE_HEIGHT, true);
            
            // Center the image in its area
            $imgWidth = $imageImagick->getImageWidth();
            $imgHeight = $imageImagick->getImageHeight();
            $offsetX = self::IMAGE_X + (self::IMAGE_WIDTH - $imgWidth) / 2;
            $offsetY = self::IMAGE_Y + (self::IMAGE_HEIGHT - $imgHeight) / 2;
            
            // Composite the image
            $imagick->compositeImage($imageImagick, Imagick::COMPOSITE_OVER, (int)$offsetX, (int)$offsetY);
            
            $imageImagick->clear();
            $imageImagick->destroy();
            
        } catch (Throwable $e) {
            error_log('Failed to composite image: ' . $e->getMessage());
        }
    }
    
    /**
     * Get the primary image to display
     * @return string|null Image data (path or base64 string) or null if no image
     */
    private function getPrimaryImage(array $flashData): ?string
    {
        // Priority: grid_bitmap > individual images (image_main, image_2, image_3)
        
        // 1. Check for grid bitmap (final composite) - if present, use only this
        $gridBitmap = $flashData['grid_bitmap'] ?? '';
        if (!empty($gridBitmap)) {
            if (strpos($gridBitmap, 'data:image/') === 0) {
                return $gridBitmap; // Base64 data URL
            } else {
                $gridPath = $this->uploadsDir . '/grids/' . $gridBitmap;
                if (file_exists($gridPath)) {
                    return $gridPath; // Return file path for better performance
                }
            }
        }
        
        // 2. Check for individual images - return first available
        $imageSources = [
            ['edited' => 'image1_edited_data', 'original' => 'image_main'],
            ['edited' => 'image2_edited_data', 'original' => 'image_2'],
            ['edited' => 'image3_edited_data', 'original' => 'image_3'],
        ];
        
        foreach ($imageSources as $source) {
            // Check for edited version first
            $edited = $flashData[$source['edited']] ?? '';
            if (!empty($edited) && strpos($edited, 'data:image/') === 0) {
                return $edited;
            }
            
            // Check for original image file
            $imageFile = $flashData[$source['original']] ?? '';
            if (!empty($imageFile)) {
                $imagePath = $this->uploadsDir . '/images/' . $imageFile;
                if (file_exists($imagePath)) {
                    return $imagePath;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get font path for text rendering
     */
    private function getFont(string $variant = 'Regular'): string
    {
        // Use local OpenSans font from project assets
        $fontPath = __DIR__ . '/../../assets/fonts/OpenSans-' . $variant . '.ttf';
        
        if (!file_exists($fontPath)) {
            throw new RuntimeException('Font file not found: ' . $fontPath);
        }
        
        return $fontPath;
    }
    
    /**
     * Get labels for given language
     */
    private function getLabels(string $lang): array
    {
        $labels = [
            'fi' => [
                'site' => 'Työmaa:',
                'date' => 'Milloin?',
                'type_yellow' => 'VAARATILANNE',
                'type_red' => 'ENSITIEDOTE',
                'type_green' => 'TUTKINTATIEDOTE',
                'root_causes' => 'Juurisyyt',
                'actions' => 'Toimenpiteet',
            ],
            'sv' => [
                'site' => 'Arbetsplats:',
                'date' => 'När?',
                'type_yellow' => 'FAROSITUASJON',
                'type_red' => 'FÖRSTA RAPPORT',
                'type_green' => 'UNDERSÖKNINGSRAPPORT',
                'root_causes' => 'Grundorsaker',
                'actions' => 'Åtgärder',
            ],
            'en' => [
                'site' => 'Worksite:',
                'date' => 'When?',
                'type_yellow' => 'HAZARD',
                'type_red' => 'INCIDENT',
                'type_green' => 'INVESTIGATION',
                'root_causes' => 'Root Causes',
                'actions' => 'Actions',
            ],
            'it' => [
                'site' => 'Cantiere:',
                'date' => 'Quando?',
                'type_yellow' => 'PERICOLO',
                'type_red' => 'INCIDENTE',
                'type_green' => 'INDAGINE',
                'root_causes' => 'Cause Radice',
                'actions' => 'Azioni',
            ],
            'el' => [
                'site' => 'Εργοτάξιο:',
                'date' => 'Πότε?',
                'type_yellow' => 'ΚΙΝΔΥΝΟΣ',
                'type_red' => 'ΣΥΜΒΑΝ',
                'type_green' => 'ΕΡΕΥΝΑ',
                'root_causes' => 'Βασικές Αιτίες',
                'actions' => 'Ενέργειες',
            ],
        ];
        
        return $labels[$lang] ?? $labels['fi'];
    }
    
    
    /**
     * Wrap text to fit width (character-based approximation)
     */
    private function wrapText(string $text, int $maxCharsPerLine): array
    {
        if (empty($text)) {
            return [];
        }
        
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';
        
        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            
            if (mb_strlen($testLine) <= $maxCharsPerLine) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }
        
        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }
        
        return $lines;
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
     * Get dynamic font size based on content length
     */
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
     * Calculate total content length for dynamic font sizing
     */
    private function calculateTotalContentLength(array $flashData): int
    {
        $title = trim((string) ($flashData['title_short'] ?? ''));
        $description = trim((string) ($flashData['description'] ?? ''));
        $rootCauses = trim((string) ($flashData['root_causes'] ?? ''));
        $actions = trim((string) ($flashData['actions'] ?? ''));
        
        return mb_strlen($title) + mb_strlen($description) + mb_strlen($rootCauses) + mb_strlen($actions);
    }
    
    /**
     * Get font sizes based on total content length
     * Returns array with keys: title, description, content
     */
    private function getFontSizesByTotalLength(int $totalLength): array
    {
        if ($totalLength < 500) {
            return ['title' => 38, 'description' => 26, 'content' => 22];
        } elseif ($totalLength < 700) {
            return ['title' => 36, 'description' => 24, 'content' => 20];
        } elseif ($totalLength < 900) {
            return ['title' => 34, 'description' => 22, 'content' => 18];
        } else {
            return ['title' => 32, 'description' => 20, 'content' => 16];
        }
    }
    
    /**
     * Wrap text with paragraph breaks support
     * Handles \n newlines in text to preserve user-defined paragraph breaks
     */
    private function wrapTextWithParagraphs(string $text, int $maxCharsPerLine): array
    {
        if (empty($text)) {
            return [];
        }
        
        // Split by newlines first to handle paragraph breaks
        $paragraphs = explode("\n", $text);
        $allLines = [];
        
        foreach ($paragraphs as $para) {
            if (trim($para) === '') {
                // Empty line for paragraph break
                $allLines[] = '';
            } else {
                // Wrap the paragraph
                $wrapped = $this->wrapText(trim($para), $maxCharsPerLine);
                $allLines = array_merge($allLines, $wrapped);
            }
        }
        
        return $allLines;
    }
    
    /**
     * Draw wrapped text with truncation support
     * 
     * @param Imagick $imagick The image object to draw on
     * @param ImagickDraw $draw The drawing object configured with font and color
     * @param string $text Text to draw
     * @param int $x X coordinate
     * @param int $y Y coordinate (top of first line)
     * @param int $width Available width in pixels
     * @param int $fontSize Font size in pixels
     * @param int $lineHeight Line height in pixels
     * @param int $maxLines Maximum number of lines to draw
     * @return int Number of lines drawn
     */
    private function drawWrappedText(
        Imagick $imagick,
        ImagickDraw $draw,
        string $text,
        int $x,
        int $y,
        int $width,
        int $fontSize,
        int $lineHeight,
        int $maxLines
    ): int {
        $text = trim((string)$text);
        if ($text === '') {
            return 0;
        }

        // Normalisoi rivinvaihdot ja rajoita tyhjien rivien määrä
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // Auto-fit: pienennä fonttia kunnes kaikki rivit mahtuu (ei truncationia)
        $minFontSize = 12;

        for ($fs = $fontSize; $fs >= $minFontSize; $fs--) {
            $draw->setFontSize($fs);

            // Skaalaa rivikorkeus fontin mukana
            $lh = (int)round($lineHeight * ($fs / max(1, $fontSize)));
            $lh = max(10, $lh);

            $charsPerLine = (int)($width / ($fs * 0.55));
            $charsPerLine = max(18, $charsPerLine);

            // Kappaleet mukaan (pitkä kuvaus käyttää \n)
            $lines = $this->wrapTextWithParagraphs($text, $charsPerLine);

            if (count($lines) <= $maxLines) {
                $linesDrawn = 0;
                foreach ($lines as $i => $line) {
                    $currentY = $y + ($i * $lh);
                    $imagick->annotateImage($draw, $x, $currentY, 0, $line);
                    $linesDrawn++;
                }
                return $linesDrawn;
            }
        }

        // Fallback: jos ihan ääripää (ei pitäisi osua 950 merkillä), piirrä minimifontilla maxLines
        $draw->setFontSize($minFontSize);
        $lh = max(10, (int)round($lineHeight * ($minFontSize / max(1, $fontSize))));
        $charsPerLine = max(18, (int)($width / ($minFontSize * 0.55)));
        $lines = $this->wrapTextWithParagraphs($text, $charsPerLine);

        $linesToDraw = array_slice($lines, 0, $maxLines);
        foreach ($linesToDraw as $i => $line) {
            $currentY = $y + ($i * $lh);
            $imagick->annotateImage($draw, $x, $currentY, 0, $line);
        }
        return count($linesToDraw);
    }
    
    /**
     * Render Investigation (Green) type with split view layout
     * Shows description in top box, root causes and actions in split bottom boxes
     * 
     * @param Imagick $imagick The image object
     * @param string $title Short title text
     * @param string $description Long description text
     * @param string $rootCauses Root causes text
     * @param string $actions Actions text
     * @param string $siteText Site information
     * @param string $dateText Date information
     * @param string $siteLabel Site label (translated)
     * @param string $dateLabel Date label (translated)
     * @param array $labels All translated labels
     * @param array $flashData Full flash data for image compositing
     */
    private function renderInvestigationSplitView(
        Imagick $imagick,
        string $title,
        string $description,
        string $rootCauses,
        string $actions,
        string $siteText,
        string $dateText,
        string $siteLabel,
        string $dateLabel,
        array $labels,
        array $flashData
    ): void {
        // Calculate total content length and get font sizes
        $totalLength = $this->calculateTotalContentLength($flashData);
        $fontSizes = $this->getFontSizesByTotalLength($totalLength);
        
        $titleFontSize = $fontSizes['title'];
        $descFontSize = $fontSizes['description'];
        $contentFontSize = $fontSizes['content'];
        
        // 1. Draw title - BLACK text, dynamic font size based on total content length
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Bold'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['black']));
        $draw->setFontSize($titleFontSize);
        
        // Title position with dynamic gap
        $titleY = self::TITLE_Y + $titleFontSize + ((self::TITLE_HEIGHT - $titleFontSize) / 2);
        $this->drawWrappedText(
            $imagick,
            $draw,
            $title,
            self::TITLE_X + self::SPLIT_VIEW_PADDING,
            $titleY,
            self::TITLE_WIDTH - (self::SPLIT_VIEW_PADDING * 2),
            $titleFontSize,
            $titleFontSize + self::SPLIT_VIEW_HEADER_OFFSET,
            2  // Max 2 lines for title
        );
        
        // Calculate title height for dynamic description positioning
        $titleLines = $this->wrapText($title, (int)(self::TITLE_WIDTH / ($titleFontSize * 0.5)));
        $titleLineCount = min(count($titleLines), 2);
        $titleHeight = $titleLineCount * ($titleFontSize + self::SPLIT_VIEW_HEADER_OFFSET);
        
        // 2. Draw description - starts dynamically after title with 40px gap
        $descStartY = self::TITLE_Y + $titleHeight + 40;
        
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Regular'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['black']));
        $draw->setFontSize($descFontSize);
        
        $descY = $descStartY + $descFontSize + self::SPLIT_VIEW_TOP_PADDING;
        $this->drawWrappedText(
            $imagick,
            $draw,
            $description,
            self::DESC_X + self::SPLIT_VIEW_PADDING,
            $descY,
            self::DESC_WIDTH - (self::SPLIT_VIEW_PADDING * 2),
            $descFontSize,
            (int)($descFontSize * self::SPLIT_VIEW_LINE_HEIGHT_MULTIPLIER),
            5  // Max 5 lines
        );
        
        // 3. Draw Root Causes section (bottom-left)
        // Header with black background
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel(self::COLORS['black_box']));
        $draw->rectangle(
            self::ROOT_X,
            self::SPLIT_Y,
            self::ROOT_X + self::SPLIT_WIDTH,
            self::SPLIT_Y + self::SPLIT_VIEW_HEADER_HEIGHT
        );
        $imagick->drawImage($draw);
        
        // Header text
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Bold'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['white']));
        $draw->setFontSize(18);  // Header font size: 18px
        $imagick->annotateImage(
            $draw,
            self::ROOT_X + self::SPLIT_VIEW_PADDING_SMALL,
            self::SPLIT_Y + 18 + self::SPLIT_VIEW_HEADER_OFFSET,
            0,
            $labels['root_causes']
        );
        
        // Content - with dynamic font sizing (using calculated contentFontSize)
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Regular'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['black']));
        $draw->setFontSize($contentFontSize);
        
        $rootContentY = self::SPLIT_Y + self::SPLIT_VIEW_HEADER_HEIGHT + $contentFontSize + self::SPLIT_VIEW_TOP_PADDING;
        
        // Calculate available height before meta boxes
        $metaTopY = self::META_Y - self::SPLIT_VIEW_META_BUFFER;
        $availableHeight = $metaTopY - $rootContentY;
        
        // Calculate max lines based on available height and font size
        $maxContentLines = (int) floor($availableHeight / ($contentFontSize * self::SPLIT_VIEW_LINE_HEIGHT_MULTIPLIER));
        $maxContentLines = max(self::SPLIT_VIEW_MIN_CONTENT_LINES, min($maxContentLines, self::SPLIT_VIEW_MAX_CONTENT_LINES));
        
        $this->drawWrappedText(
            $imagick,
            $draw,
            $rootCauses,
            self::ROOT_X + self::SPLIT_VIEW_PADDING_SMALL,
            $rootContentY,
            self::SPLIT_WIDTH - (self::SPLIT_VIEW_PADDING_SMALL * 2),
            $contentFontSize,
            (int)($contentFontSize * self::SPLIT_VIEW_LINE_HEIGHT_MULTIPLIER),
            $maxContentLines
        );
        
        // 4. Draw Actions section (bottom-right)
        // Header with black background
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel(self::COLORS['black_box']));
        $draw->rectangle(
            self::ACTION_X,
            self::SPLIT_Y,
            self::ACTION_X + self::SPLIT_WIDTH,
            self::SPLIT_Y + self::SPLIT_VIEW_HEADER_HEIGHT
        );
        $imagick->drawImage($draw);
        
        // Header text
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Bold'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['white']));
        $draw->setFontSize(18);  // Header font size: 18px
        $imagick->annotateImage(
            $draw,
            self::ACTION_X + self::SPLIT_VIEW_PADDING_SMALL,
            self::SPLIT_Y + 18 + self::SPLIT_VIEW_HEADER_OFFSET,
            0,
            $labels['actions']
        );
        
        // Content - with dynamic font sizing (using calculated contentFontSize)
        $draw = new ImagickDraw();
        $draw->setFont($this->getFont('Regular'));
        $draw->setFillColor(new ImagickPixel(self::COLORS['black']));
        $draw->setFontSize($contentFontSize);
        
        $actionContentY = self::SPLIT_Y + self::SPLIT_VIEW_HEADER_HEIGHT + $contentFontSize + self::SPLIT_VIEW_TOP_PADDING;
        $this->drawWrappedText(
            $imagick,
            $draw,
            $actions,
            self::ACTION_X + self::SPLIT_VIEW_PADDING_SMALL,
            $actionContentY,
            self::SPLIT_WIDTH - (self::SPLIT_VIEW_PADDING_SMALL * 2),
            $contentFontSize,
            (int)($contentFontSize * self::SPLIT_VIEW_LINE_HEIGHT_MULTIPLIER),
            $maxContentLines
        );
        
        // 5. Draw meta info (site and date) at fixed position
        $this->renderMetaInfo($imagick, $siteLabel, $siteText, $dateLabel, $dateText, self::META_Y);
        
        // 6. Composite grid bitmap image
        $this->compositeImage($imagick, $flashData);
    }
}