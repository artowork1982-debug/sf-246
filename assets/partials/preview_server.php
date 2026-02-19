<?php
/**
 * Server-side rendered preview (unified rendering engine)
 * Replaces client-side HTML/CSS preview with server-generated images
 */

if (!isset($base)) {
    throw new RuntimeException('preview_server.php requires $base to be defined');
}

// Get flash type for conditional display
$type_val = $flash['type'] ?? 'yellow';
$lang_val = $flash['lang'] ?? 'fi';
?>

<div class="sf-preview-section" id="sfServerPreviewSection">
    <h2 class="sf-preview-step-title">Esikatselu</h2>
    
    <!-- Font Size Selector - Available for all types -->
    <div id="sfFontSizeSelector" class="sf-font-size-selector">
        <label class="sf-label"><?= htmlspecialchars(sf_term('font_size_label', $uiLang) ?? 'Text size', ENT_QUOTES, 'UTF-8') ?></label>
        <div class="sf-font-size-options">
            <label class="sf-font-size-option selected" data-size="auto">
                <input type="radio" name="font_size" value="auto" checked>
                <span class="sf-font-size-btn">Auto</span>
            </label>
            <label class="sf-font-size-option" data-size="S">
                <input type="radio" name="font_size" value="S">
                <span class="sf-font-size-btn">S</span>
            </label>
            <label class="sf-font-size-option" data-size="M">
                <input type="radio" name="font_size" value="M">
                <span class="sf-font-size-btn">M</span>
            </label>
            <label class="sf-font-size-option" data-size="L">
                <input type="radio" name="font_size" value="L">
                <span class="sf-font-size-btn">L</span>
            </label>
            <label class="sf-font-size-option" data-size="XL">
                <input type="radio" name="font_size" value="XL">
                <span class="sf-font-size-btn">XL</span>
            </label>
        </div>
        <input type="hidden" name="font_size_override" id="sfFontSizeOverride" value="">
    </div>
    
    <!-- Preview tabs for green type (investigation reports) -->
    <div class="sf-preview-tabs" id="sfPreviewTabs" style="display: none;">
        <button type="button" class="sf-preview-tab-btn active" data-card="1" id="sfPreviewTab1">
            1. Yhteenveto & kuvat
        </button>
        <button type="button" class="sf-preview-tab-btn" data-card="2" id="sfPreviewTab2">
            2. Juurisyyt & toimenpiteet
        </button>
    </div>
    
    <!-- Preview container with loading state -->
    <div class="sf-preview-wrapper" id="sfServerPreviewWrapper">
        <!-- Card 1 preview image -->
        <div class="sf-preview-card-container" id="sfPreviewCard1Container">
            <img 
                id="sfPreviewImage1" 
                src="" 
                alt="Esikatselu"
                class="sf-preview-img"
                style="width: 100%; max-width: 960px; height: auto; display: none;"
            >
        </div>
        
        <!-- Card 2 preview image (for green type two-slide layout) -->
        <div class="sf-preview-card-container" id="sfPreviewCard2Container" style="display: none;">
            <img 
                id="sfPreviewImage2" 
                src="" 
                alt="Esikatselu kortti 2"
                class="sf-preview-img"
                style="width: 100%; max-width: 960px; height: auto; display: none;"
            >
        </div>
        
        <!-- Loading indicator -->
        <div class="sf-preview-loading" id="sfPreviewLoading" style="display: flex; align-items: center; justify-content: center; min-height: 270px;">
            <div style="text-align: center;">
                <div class="sf-spinner" style="margin: 0 auto 10px;"></div>
                <span>Luodaan esikatselua...</span>
            </div>
        </div>
        
        <!-- Error message -->
        <div class="sf-preview-error" id="sfPreviewError">
            <strong>Virhe:</strong> <span id="sfPreviewErrorMessage"></span>
        </div>
    </div>
</div>

<style>
/* Spinner animation */
.sf-spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #0066cc;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: sf-spin 1s linear infinite;
}

@keyframes sf-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Modernit esikatselu-tabit */
.sf-preview-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    padding: 4px;
    background: #f1f5f9;
    border-radius: 12px;
    width: fit-content;
}

.sf-preview-tab-btn {
    padding: 10px 20px;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    transition: all 0.2s ease;
}

.sf-preview-tab-btn:hover {
    background: rgba(255, 255, 255, 0.6);
    color: #334155;
}

.sf-preview-tab-btn.active {
    background: white;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Moderni virheviesti */
.sf-preview-error {
    display: none;
    padding: 16px 20px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    color: #dc2626;
    font-size: 14px;
}

.sf-preview-error strong {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.sf-preview-error strong::before {
    content: '⚠️';
}

/* Moderni refresh-nappi */
.sf-icon-btn#sfRefreshPreviewBtn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

.sf-icon-btn#sfRefreshPreviewBtn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: rotate(15deg);
}

.sf-icon-btn#sfRefreshPreviewBtn:active {
    transform: rotate(0deg) scale(0.95);
}

.sf-preview-card-container {
    transition: opacity 0.3s;
}

.sf-preview-card-container.hidden {
    display: none;
}
</style>

<script>
// Initialize server-side preview on page load
(function() {
    'use strict';
    
    // This will be replaced by the preview-server.js module
    // For now, just a placeholder to show the structure
    console.log('[Server Preview] Waiting for preview-server.js module to initialize');
})();
</script>