// assets/js/modules/preview_tutkinta.js
// Tutkintatiedotteen laajennus PreviewCore:sta

import { PreviewCore } from './preview-core.js';

class PreviewTutkintaClass extends PreviewCore {
    constructor() {
        super({
            idSuffix: 'Green',
            cardId: 'sfPreviewCard',
            gridSelectorId: 'sfGridSelector',
            sliderXId: 'sfPreviewSliderX',
            sliderYId: 'sfPreviewSliderY',
            sliderZoomId: 'sfPreviewSliderZoom',
            slidersPanelId: 'sfSlidersPanel',
            annotationsPanelId: 'sfAnnotationsPanel'
        });

        this.tutkintaIds = {
            card1: 'sfPreviewCardGreen',
            card2: 'sfPreviewCard2Green',
            tabs: 'sfPreviewTabsTutkinta',
            tab2: 'sfPreviewTab2Green',
            bg1: 'sfPreviewBgGreen',
            bg2: 'sfPreviewBg2Green',
            title1: 'sfPreviewTitleGreen',
            title2: 'sfPreviewTitle2Green',
            desc: 'sfPreviewDescGreen',
            site1: 'sfPreviewSiteGreen',
            site2: 'sfPreviewSite2Green',
            date1: 'sfPreviewDateGreen',
            date2: 'sfPreviewDate2Green',
            rootCauses: 'sfPreviewRootCausesGreen',
            rootCausesCard1: 'sfPreviewRootCausesCard1Green',
            actions: 'sfPreviewActionsGreen',
            actionsCard1: 'sfPreviewActionsCard1Green'
        };

        this.activeCard = 1;
        this._tutkintaEventsBound = false;

        this.LIMITS = {
            shortText: 85,
            descSingleSlide: 400,
            descTwoSlides: 650,
            rootCausesSingleSlide: 500,
            actionsSingleSlide: 500,
            rootCausesTwoSlides: 800,
            actionsTwoSlides: 800,
            rootCausesActionsCombined: 800,
            lineBreakCost: 30,
            maxColumnLines: 14,      // Max lines that fit in a column on single-slide layout
            charsPerLine: 45         // Average characters per line
        };

        // Font size ratios (proportional to base size)
        this.FONT_RATIOS = {
            shortTitle: 1.6,
            description: 1.0,
            rootCauses: 0.9,
            actions: 0.9
        };

        // Preset sizes (base size for description)
        this.FONT_PRESETS = {
            'XS': 14,
            'S': 16,
            'M': 18,
            'L': 20,
            'XL': 22
        };

        // Font size calculation constants
        this.FONT_SIZE_AUTO = {
            max: 22,      // Maximum base size for auto mode
            min: 12,      // Minimum base size for auto mode
            step: 1       // Step size when searching for optimal size
        };

        // Layout constraint constants for card fitting calculations
        this.CARD_LAYOUT = {
            card1DescMaxHeight: 420,   // Max height for description on card 1
            card1DescWidth: 880,       // Width for description text (TEXT_COL_WIDTH 920 - 40 padding)
            columnMaxHeight: 400,      // Max height for root causes/actions columns
            columnWidth: 420,          // Width for columns ((920-20)/2 - 30 padding)
            headersSpacing: 100,       // Extra space for headers and spacing (header boxes + gaps)
            singleCardMaxHeight: 850,  // Total max height for single card
            charWidthRatio: 0.48       // Approximate character width as ratio of font size
            // (calibrated for Open Sans font - actual average ~0.48)
        };

        this.SINGLE_SLIDE_TOTAL_LIMIT = 900;
        this._resizeListenerBound = false;
    }

    /**
     * Update card scale to fit container
     * Called on init, resize, and tab switch
     */
    updateCardScale() {
        const wrapper = document.getElementById('sfPreviewWrapperGreen');
        const card1 = document.getElementById(this.tutkintaIds.card1);
        const card2 = document.getElementById(this.tutkintaIds.card2);

        if (!wrapper || !card1) return;

        // Get available width (container width minus padding from .sf-preview-section: 0 12px)
        const containerWidth = wrapper.parentElement?.offsetWidth || wrapper.offsetWidth;
        const availableWidth = containerWidth - 24; // 12px padding on each side

        // Card base dimensions (960x540)
        const cardWidth = 960;

        // Calculate scale to fit
        const scale = Math.min(1, availableWidth / cardWidth);

        // Apply scale transform
        card1.style.transform = `scale(${scale})`;

        if (card2) {
            card2.style.transform = `scale(${scale})`;
        }

        // Adjust wrapper height to match scaled card
        const scaledHeight = 540 * scale;
        wrapper.style.height = `${scaledHeight}px`;
    }

    init() {
        if (this.initialized) {
            console.log('PreviewTutkinta already initialized');
            return this;
        }

        const card = document.getElementById(this.tutkintaIds.card1);
        if (!card) {
            console.warn('PreviewTutkinta init: Card not found');
            return this;
        }

        super.init();

        if (!this._tutkintaEventsBound) {
            this._initTabs();
            this._bindFormEvents();
            this._bindFontSizeSelector();
            this._tutkintaEventsBound = true;
        }

        // Show font size selector for green type
        this._showFontSizeSelector();

        this.updatePreviewContent();

        // Update card scale on init and resize (debounced)
        this.updateCardScale();

        if (!this._resizeListenerBound) {
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => this.updateCardScale(), 100);
            });
            this._resizeListenerBound = true;
        }

        console.log('PreviewTutkinta initialized');
        return this;
    }

    _initTabs() {
        const tabsWrapper = document.getElementById(this.tutkintaIds.tabs);
        if (!tabsWrapper) return;

        const buttons = tabsWrapper.querySelectorAll('.sf-preview-tab-btn');
        const self = this;

        buttons.forEach(btn => {
            if (btn.dataset.tutkintaTabBound) return;
            btn.dataset.tutkintaTabBound = '1';

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                self._switchCard(this.dataset.target, buttons);
            });
        });

        this._switchCard(this.tutkintaIds.card1, buttons);
    }

    _switchCard(targetId, buttons) {
        const card1 = document.getElementById(this.tutkintaIds.card1);
        const card2 = document.getElementById(this.tutkintaIds.card2);
        const showCard1 = !targetId || targetId === this.tutkintaIds.card1;

        if (card1) {
            card1.style.display = showCard1 ? 'block' : 'none';
        }
        if (card2) {
            card2.style.display = showCard1 ? 'none' : 'block';
        }

        this.activeCard = showCard1 ? 1 : 2;

        if (buttons) {
            buttons.forEach(btn => {
                const isActive =
                    btn.dataset.target === (showCard1 ? this.tutkintaIds.card1 : this.tutkintaIds.card2);
                btn.classList.toggle('sf-preview-tab-active', isActive);
            });
        }

        this._toggleTools(showCard1);

        if (showCard1) {
            this.applyGridClass();
            this._syncSlidersToState();
        }

        // Update scale after switching
        requestAnimationFrame(() => this.updateCardScale());
    }
    _toggleTools(show) {
        const gridSelector = document.getElementById(this.ids.gridSelector);
        const toolsTabs = document.querySelector('.sf-tools-tabs.sf-green-card1-only');
        const toolsPanels = document.querySelectorAll('.sf-tools-panel.sf-green-card1-only');
        const slidersPanel = document.getElementById(this.ids.slidersPanel);
        const annotationsPanel = document.getElementById(this.ids.annotationsPanel);

        if (gridSelector) gridSelector.style.display = show ? '' : 'none';
        if (toolsTabs) toolsTabs.style.display = show ? '' : 'none';

        toolsPanels.forEach(p => {
            if (!show) {
                p.style.display = 'none';
            } else if (p.classList.contains('active')) {
                p.style.display = 'block';
            }
        });

        if (slidersPanel) slidersPanel.style.display = show ? '' : 'none';
        if (annotationsPanel) annotationsPanel.style.display = show ? '' : 'none';
    }

    _bindFormEvents() {
        const self = this;
        const fields = [
            'sf-short-text', 'sf-description', 'sf-worksite',
            'sf-site-detail', 'sf-date', 'sf-root-causes', 'sf-actions'
        ];

        // Debounce timer to prevent flickering notification while typing
        let debounceTimer = null;

        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.dataset.tutkintaInputBound) {
                el.dataset.tutkintaInputBound = '1';
                el.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        self.updatePreviewContent();
                    }, 300);
                });
            }
        });
    }

    _calculateTextLength(text) {
        if (!text) return 0;
        // Use Array.from to count actual characters, not UTF-16 code units
        // This matches mb_strlen behavior on server side for multi-byte characters
        return Array.from(String(text)).length;
    }

    /**
     * Estimate the number of lines needed to display text
     * Takes into account line breaks (bullets) and text wrapping
     * @param {string} text Text to estimate
     * @param {number} charsPerLine Average characters per line
     * @return {number} Estimated number of lines
     */
    _estimateLines(text, charsPerLine = null) {
        if (!text) return 0;

        charsPerLine = charsPerLine || this.LIMITS.charsPerLine;
        let lines = 0;
        const paragraphs = text.split('\n');

        for (const p of paragraphs) {
            const trimmed = p.trim();
            if (trimmed === '') continue;

            // Each paragraph/bullet point is at least 1 line
            // Additional lines based on character count
            lines += Math.max(1, Math.ceil(this._calculateTextLength(trimmed) / charsPerLine));
        }

        return lines;
    }

    /**
     * Determine if content requires two slides
     * @param {string} title - Title text
     * @param {string} desc - Description text
     * @param {string} rootCauses - Root causes text
     * @param {string} actions - Actions text
     * @param {Object|null} sizes - Optional pre-calculated font sizes (null to calculate automatically)
     * @return {boolean} True if two slides are needed
     */
    _shouldUseTwoSlides(title, desc, rootCauses, actions, sizes = null) {
        const hasRootCauses = (rootCauses || '').trim().length > 0;
        const hasActions = (actions || '').trim().length > 0;

        if (!hasRootCauses && !hasActions) {
            return false;
        }

        // Use pixel-based calculation with the ACTUAL font sizes
        const calculatedSizes = sizes || this._getCurrentFontSizes();
        return !this._contentFitsOnSingleCard(title, desc, rootCauses, actions, calculatedSizes);
    }

    /**
     * Get dynamic size class based on total content length
     * Matches the logic in preview_tutkinta.php and PreviewImageGenerator.php
     */
    _getDynamicSizeClass(title, desc, rootCauses, actions) {
        // Use _calculateTextLength to match PHP mb_strlen() behavior for multi-byte characters
        const totalLength = this._calculateTextLength(title) + this._calculateTextLength(desc) +
            this._calculateTextLength(rootCauses) + this._calculateTextLength(actions);

        if (totalLength < 500) return 'sf-content-size-lg';
        if (totalLength < 700) return 'sf-content-size-md';
        if (totalLength < 900) return 'sf-content-size-sm';
        return 'sf-content-size-xs';
    }

    _updateTwoSlidesNotice(show) {
        const notice = document.getElementById('sfTwoSlidesNotice');
        if (notice) {
            notice.style.display = show ? 'flex' : 'none';
        }
    }
    updatePreviewContent() {
        const title = document.getElementById('sf-short-text')?.value || '';
        const desc = document.getElementById('sf-description')?.value || '';
        const site = document.getElementById('sf-worksite')?.value || '';
        const siteDetail = document.getElementById('sf-site-detail')?.value || '';
        const siteText = [site, siteDetail].filter(Boolean).join(' – ');
        const rootCauses = document.getElementById('sf-root-causes')?.value || '';
        const actions = document.getElementById('sf-actions')?.value || '';

        const formattedDate = this._formatDate();

        const sizes = this._getCurrentFontSizes();
        const useTwoSlides = this._shouldUseTwoSlides(title, desc, rootCauses, actions, sizes);
        const hasRootOrActions = (rootCauses.trim().length > 0) || (actions.trim().length > 0);

        const tab2 = document.getElementById(this.tutkintaIds.tab2);
        if (tab2) tab2.style.display = useTwoSlides ? '' : 'none';

        this._setMultiline(this.tutkintaIds.title1, title, 'Lyhyt kuvaus tapahtumasta');
        this._setMultiline(this.tutkintaIds.desc, desc, 'Tarkempi kuvaus');
        this._setMultiline(this.tutkintaIds.site1, siteText, '–');
        this._setMultiline(this.tutkintaIds.date1, formattedDate, '–');

        // Kortti 1:n juurisyyt/toimenpiteet rivi
        const rootActionsRow = document.getElementById('sfRootActionsCard1Green');
        const rootCausesCard1 = document.getElementById('sfPreviewRootCausesCard1Green');
        const actionsCard1 = document.getElementById('sfPreviewActionsCard1Green');

        if (useTwoSlides) {
            // Piilota kortti 1:n juurisyyt, näytä kortti 2:lla
            if (rootActionsRow) rootActionsRow.style.display = 'none';
        } else {
            // Näytä kortti 1:llä jos on sisältöä
            if (rootActionsRow) {
                rootActionsRow.style.display = hasRootOrActions ? 'grid' : 'none';
            }
            if (rootCausesCard1) {
                rootCausesCard1.innerHTML = this._formatBulletList(rootCauses);
            }
            if (actionsCard1) {
                actionsCard1.innerHTML = this._formatBulletList(actions);
            }
        }

        if (useTwoSlides) {
            this._setMultiline(this.tutkintaIds.title2, title, 'Kuvaus');
            this._setMultiline(this.tutkintaIds.site2, siteText, '–');
            this._setMultiline(this.tutkintaIds.date2, formattedDate, '–');

            const rootEl = document.getElementById(this.tutkintaIds.rootCauses);
            if (rootEl) rootEl.innerHTML = this._formatBulletList(rootCauses);

            const actionsEl = document.getElementById(this.tutkintaIds.actions);
            if (actionsEl) actionsEl.innerHTML = this._formatBulletList(actions);

            // Calculate total content length for Card 2
            const totalCard2Chars = title.length + rootCauses.length + actions.length;
            const card2 = document.getElementById(this.tutkintaIds.card2);

            if (card2) {
                // Remove existing content classes
                card2.classList.remove('content-medium', 'content-large', 'content-xlarge');

                // Add appropriate class based on content length
                if (totalCard2Chars > 1200) {
                    card2.classList.add('content-xlarge');
                } else if (totalCard2Chars > 900) {
                    card2.classList.add('content-large');
                } else if (totalCard2Chars > 600) {
                    card2.classList.add('content-medium');
                }
                // else: use default (normal) sizing
            }
        }

        // Apply font sizes to preview DOM elements AFTER content is set
        this._applyFontSizesToPreview(sizes);

        this._updateTwoSlidesNotice(useTwoSlides);
        this._updateBackgroundImages(useTwoSlides);
        this.applyGridClass();

        // Update scale after content changes
        this.updateCardScale();
    }

    _formatDate() {
        const dateEl = document.getElementById('sf-date');
        if (!dateEl?.value) return '–';

        const d = new Date(dateEl.value);
        if (isNaN(d.getTime())) return '–';

        const pad = n => (n < 10 ? '0' + n : '' + n);
        return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    _escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    _setMultiline(id, text, fallback) {
        const el = document.getElementById(id);
        if (el) {
            const value = (text?.trim()) ? text : (fallback || '–');
            el.innerHTML = this._escapeHtml(value).replace(/\n/g, '<br>');
        }
    }

    _formatBulletList(text) {
        if (!text || !text.trim()) return '–';

        const lines = text.split('\n');
        const result = [];

        for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed) continue;

            // Tarkista alkaako rivi bullet-merkillä
            const bulletMatch = trimmed.match(/^[-•·*]\s*(.+)$/);
            if (bulletMatch) {
                result.push(
                    '<div class="sf-bullet-line">' +
                    '<span class="sf-bullet">•</span>' +
                    '<span class="sf-bullet-text">' + this._escapeHtml(bulletMatch[1]) + '</span>' +
                    '</div>'
                );
            } else {
                result.push('<div>' + this._escapeHtml(trimmed) + '</div>');
            }
        }

        return result.join('');
    }

    _updateBackgroundImages(hasTwoCards) {
        const card1 = document.getElementById(this.tutkintaIds.card1);
        if (!card1) return;

        const lang = card1.dataset.lang || 'fi';
        const base = card1.dataset.baseUrl || '';

        const bg1 = document.getElementById(this.tutkintaIds.bg1);
        const bg2 = document.getElementById(this.tutkintaIds.bg2);

        if (hasTwoCards) {
            if (bg1) bg1.src = `${base}/assets/img/templates/SF_bg_green_1_${lang}.jpg`;
            if (bg2) bg2.src = `${base}/assets/img/templates/SF_bg_green_2_${lang}.jpg`;
        } else {
            if (bg1) bg1.src = `${base}/assets/img/templates/SF_bg_green_${lang}.jpg`;
        }

        card1.dataset.hasCard2 = hasTwoCards ? '1' : '0';
    }

    /**
     * Calculate all font sizes from base size using ratios
     */
    _calculateFontSizes(baseSize) {
        return {
            shortTitle: Math.round(baseSize * this.FONT_RATIOS.shortTitle),
            description: Math.round(baseSize * this.FONT_RATIOS.description),
            rootCauses: Math.round(baseSize * this.FONT_RATIOS.rootCauses),
            actions: Math.round(baseSize * this.FONT_RATIOS.actions)
        };
    }

    /**
     * Get current font sizes based on selector
     */
    _getCurrentFontSizes() {
        const selected = document.querySelector('input[name="font_size"]:checked')?.value || 'auto';

        let maxBase;
        if (selected === 'auto') {
            maxBase = this.FONT_SIZE_AUTO.max;
        } else {
            maxBase = this.FONT_PRESETS[selected];
        }

        // Try from selected size down until content fits
        const baseSize = this._calculateOptimalBaseSizeFrom(maxBase);
        return this._calculateFontSizes(baseSize);
    }

    /**
     * Calculate optimal base size to fit content on single card
     */
    _calculateOptimalBaseSize() {
        return this._calculateOptimalBaseSizeFrom(this.FONT_SIZE_AUTO.max);
    }

    /**
     * Calculate optimal base size starting from a maximum
     * Tries progressively smaller sizes until content fits
     */
    _calculateOptimalBaseSizeFrom(maxBase) {
        const title = document.getElementById('sf-short-text')?.value || '';
        const desc = document.getElementById('sf-description')?.value || '';
        const rootCauses = document.getElementById('sf-root-causes')?.value || '';
        const actions = document.getElementById('sf-actions')?.value || '';

        // Try from largest to smallest until content fits
        for (let baseSize = maxBase; baseSize >= this.FONT_SIZE_AUTO.min; baseSize -= this.FONT_SIZE_AUTO.step) {
            const sizes = this._calculateFontSizes(baseSize);

            if (this._contentFitsOnSingleCard(title, desc, rootCauses, actions, sizes)) {
                return baseSize;
            }
        }

        return this.FONT_SIZE_AUTO.min; // Minimum
    }

    /**
     * Check if content fits with given font sizes
     * Enhanced version that takes font sizes into account
     */
    _contentFitsOnSingleCard(title, desc, rootCauses, actions, sizes) {
        // Estimate description height with dynamic font size
        const descLines = this._estimateLinesWithFontSize(desc, this.CARD_LAYOUT.card1DescWidth, sizes.description);
        const descHeight = descLines * (sizes.description * 1.35);

        if (descHeight > this.CARD_LAYOUT.card1DescMaxHeight) {
            return false; // Description alone doesn't fit
        }

        // Card 2 area (root causes + actions side by side)
        const rootLines = this._estimateLinesWithFontSize(rootCauses, this.CARD_LAYOUT.columnWidth, sizes.rootCauses);
        const actionsLines = this._estimateLinesWithFontSize(actions, this.CARD_LAYOUT.columnWidth, sizes.actions);

        const rootHeight = rootLines * (sizes.rootCauses * 1.35);
        const actionsHeight = actionsLines * (sizes.actions * 1.35);
        const maxColumnHeight = Math.max(rootHeight, actionsHeight);

        // In single-card mode, need space for BOTH description AND root/actions
        const totalContentHeight = descHeight + maxColumnHeight + this.CARD_LAYOUT.headersSpacing;

        return totalContentHeight <= this.CARD_LAYOUT.singleCardMaxHeight;
    }

    /**
     * Estimate lines with specific font size and width
     */
    _estimateLinesWithFontSize(text, maxWidth, fontSize) {
        if (!text) return 0;

        // Approximate characters per line based on font size
        const charsPerLine = Math.floor(maxWidth / (fontSize * this.CARD_LAYOUT.charWidthRatio));
        let lines = 0;

        const paragraphs = text.split('\n');
        for (const p of paragraphs) {
            const trimmed = p.trim();
            if (trimmed === '') continue;

            lines += Math.max(1, Math.ceil(this._calculateTextLength(trimmed) / charsPerLine));
        }

        return lines;
    }

    /**
     * Apply calculated font sizes to preview DOM elements
     * Preview card is 960x540 (half of 1920x1080), so scale fonts by 0.5
     * In capture mode (1920x1080), use scale 1.0
     * @param {Object} sizes - The font size object containing properties like shortTitle, description, rootCauses, and actions
     */
    _applyFontSizesToPreview(sizes) {
        const card = document.getElementById(this.tutkintaIds.card1);
        const isCaptureMode = card?.classList.contains('sf-capture-mode') ?? false;
        const scale = isCaptureMode ? 1.0 : 0.5;

        // Card 1 elements
        const title1 = document.getElementById(this.tutkintaIds.title1);
        const desc = document.getElementById(this.tutkintaIds.desc);
        const rootCausesCard1 = document.getElementById('sfPreviewRootCausesCard1Green');
        const actionsCard1 = document.getElementById('sfPreviewActionsCard1Green');

        if (title1) {
            title1.style.fontSize = `${sizes.shortTitle * scale}px`;
            title1.style.lineHeight = '1.2';
        }
        if (desc) {
            desc.style.fontSize = `${sizes.description * scale}px`;
            desc.style.lineHeight = '1.35';
        }
        if (rootCausesCard1) {
            rootCausesCard1.style.fontSize = `${sizes.rootCauses * scale}px`;
            rootCausesCard1.style.lineHeight = '1.3';
        }
        if (actionsCard1) {
            actionsCard1.style.fontSize = `${sizes.actions * scale}px`;
            actionsCard1.style.lineHeight = '1.3';
        }

        // Card 2 elements (also need font sizes applied)
        const title2 = document.getElementById(this.tutkintaIds.title2);
        const rootCauses2 = document.getElementById(this.tutkintaIds.rootCauses);
        const actions2 = document.getElementById(this.tutkintaIds.actions);

        if (title2) {
            title2.style.fontSize = `${sizes.shortTitle * scale}px`;
            title2.style.lineHeight = '1.2';
        }
        if (rootCauses2) {
            rootCauses2.style.fontSize = `${sizes.rootCauses * scale}px`;
            rootCauses2.style.lineHeight = '1.3';
        }
        if (actions2) {
            actions2.style.fontSize = `${sizes.actions * scale}px`;
            actions2.style.lineHeight = '1.3';
        }
    }

    /**
     * Bind font size selector events
     */
    _bindFontSizeSelector() {
        const options = document.querySelectorAll('.sf-font-size-option');

        options.forEach(option => {
            option.addEventListener('click', (e) => {
                // Update selected state
                options.forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');

                // Update hidden input
                const value = option.dataset.size;
                const hiddenInput = document.getElementById('sfFontSizeOverride');
                if (hiddenInput) {
                    hiddenInput.value = value === 'auto' ? '' : value;
                }

                // Update preview
                this.updatePreviewContent();

                // Recalculate if tabs should show/hide
                this._updateTabsVisibility();
            });
        });
    }

    /**
     * Show font size selector for green type
     */
    _showFontSizeSelector() {
        const selector = document.getElementById('sfFontSizeSelector');
        if (selector) {
            selector.style.display = 'block';
        }
    }

    /**
     * Update tabs based on content fit
     */
    _updateTabsVisibility() {
        const sizes = this._getCurrentFontSizes();
        const title = document.getElementById('sf-short-text')?.value || '';
        const desc = document.getElementById('sf-description')?.value || '';
        const rootCauses = document.getElementById('sf-root-causes')?.value || '';
        const actions = document.getElementById('sf-actions')?.value || '';

        const fitsOnSingle = this._contentFitsOnSingleCard(title, desc, rootCauses, actions, sizes);

        const tab2 = document.getElementById(this.tutkintaIds.tab2);
        const twoSlidesNotice = document.getElementById('sfTwoSlidesNotice');

        if (fitsOnSingle) {
            // Hide card 2 tab
            if (tab2) tab2.style.display = 'none';
            if (twoSlidesNotice) twoSlidesNotice.style.display = 'none';

            // Activate tab 1
            const tab1 = document.getElementById('sfPreviewTab1');
            if (tab1) tab1.click();
        } else {
            // Show card 2 tab
            if (tab2) tab2.style.display = '';
            if (twoSlidesNotice) twoSlidesNotice.style.display = 'flex';
        }
    }
}

export const PreviewTutkinta = new PreviewTutkintaClass();

if (typeof window !== 'undefined') {
    window.PreviewTutkinta = PreviewTutkinta;
}

export default PreviewTutkinta;