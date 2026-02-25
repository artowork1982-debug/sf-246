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

    <div class="sf-preview-layout">
        <!-- Left column: preview image + tabs -->
        <div class="sf-preview-media-col">
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
                        style="display: none;"
                    >
                </div>

                <!-- Card 2 preview image (for green type two-slide layout) -->
                <div class="sf-preview-card-container" id="sfPreviewCard2Container" style="display: none;">
                    <img
                        id="sfPreviewImage2"
                        src=""
                        alt="Esikatselu kortti 2"
                        class="sf-preview-img"
                        style="display: none;"
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

        <!-- Right column: controls -->
        <div class="sf-preview-controls-col">
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

            <!-- Refresh preview button -->
            <button
                type="button"
                class="sf-refresh-btn"
                id="sfRefreshPreviewBtn"
                data-label="<?= htmlspecialchars($lblRefresh, ENT_QUOTES, 'UTF-8') ?>"
                data-loading-label="<?= htmlspecialchars($lblRefreshing, ENT_QUOTES, 'UTF-8') ?>"
                aria-busy="false"
                title="<?= htmlspecialchars($lblRefresh, ENT_QUOTES, 'UTF-8') ?>"
            >
                <span class="sf-btn-spinner" aria-hidden="true" style="display:none;">
                    <svg width="16" height="16" viewBox="0 0 50 50">
                        <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-dasharray="90 35">
                            <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.8s" repeatCount="indefinite"/>
                        </circle>
                    </svg>
                </span>
                <span class="sf-btn-icon" aria-hidden="true" style="display:inline-flex;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M21 3v6h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="sf-btn-label"><?= htmlspecialchars($lblRefresh, ENT_QUOTES, 'UTF-8') ?></span>
            </button>
        </div>
    </div>
    <?php if (!empty($sfPreviewControlsSlot)) echo $sfPreviewControlsSlot; ?>
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
.sf-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 16px;
    height: 40px;
    border-radius: 8px;
    background: #2563eb;
    border: none;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.1s ease;
    white-space: nowrap;
    align-self: flex-end;
}

.sf-refresh-btn:hover {
    background: #1d4ed8;
}

.sf-refresh-btn:active {
    background: #1e40af;
    transform: scale(0.98);
}

.sf-refresh-btn[disabled] {
    opacity: 0.65;
    cursor: not-allowed;
}

/* Controls row: font size selector + refresh button side-by-side */
.sf-preview-controls-col {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.sf-preview-controls-col .sf-font-size-selector {
    margin-bottom: 0;
    flex: 1 1 auto;
}

/* Preview image: large and centred */
.sf-preview-img,
#sfPreviewImage1,
#sfPreviewImage2 {
    width: 100%;
    max-width: 850px;
    height: auto;
    display: block;
    margin: 0 auto;
}

.sf-preview-card-container {
    transition: opacity 0.3s;
}

.sf-preview-card-container.hidden {
    display: none;
}

/* Default (mobile-first): single column, image above controls */
.sf-preview-layout {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Desktop: two-column dashboard layout */
@media (min-width: 992px) {
    .sf-preview-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        align-items: start;
    }

    .sf-preview-media-col {
        min-width: 0;
    }

    .sf-preview-media-col .sf-preview-wrapper {
        width: 100%;
        max-width: none;
    }

    .sf-preview-media-col .sf-preview-img,
    .sf-preview-media-col #sfPreviewImage1,
    .sf-preview-media-col #sfPreviewImage2 {
        max-width: none;
        margin: 0;
    }

    .sf-preview-controls-col {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .sf-preview-controls-col .sf-font-size-selector {
        flex: none;
    }

    .sf-preview-controls-col .sf-refresh-btn {
        width: 100%;
        height: 48px;
        font-size: 1rem;
        justify-content: center;
    }
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