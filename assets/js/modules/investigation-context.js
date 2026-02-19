// assets/js/modules/investigation-context.js
// Progressive field display for investigation context (Step 2)

import { getters } from './state.js';

const { getEl } = getters;

/**
 * Initialize investigation context UI
 * - Shows worksite/date fields only after user makes a choice
 * - Either select a base flash OR enable standalone toggle
 * - In EDIT mode for green type: locks context and shows fields directly
 */
export function initInvestigationContextUI() {
    const relatedFlashSelect = getEl('sf-related-flash');
    const standaloneToggle = getEl('sf-standalone-investigation');
    const worksiteSection = getEl('sf-step2-worksite');
    const relatedFlashField = relatedFlashSelect?.closest('.sf-field');

    if (!relatedFlashSelect || !standaloneToggle || !worksiteSection) {
        console.log('[Investigation Context] Required elements not found');
        return;
    }

    // Check if we're in edit mode with green type (investigation report)
    const idInput = document.querySelector('input[name="id"]');
    const typeInput = document.querySelector('input[name="type"][value="green"]');
    const isEditMode = idInput && idInput.value && parseInt(idInput.value) > 0;
    const isGreenType = typeInput && typeInput.checked;

    console.log('[Investigation Context] Initializing...', { isEditMode, isGreenType });

    // If editing investigation report: lock context, show fields directly
    if (isEditMode && isGreenType) {
        console.log('[Investigation Context] Edit mode + green type detected - locking context');

        // Hide Step 2 selection UI (base flash selector and standalone toggle)
        if (relatedFlashField) {
            relatedFlashField.style.display = 'none';
        }

        const standaloneField = standaloneToggle?.closest('.sf-field');
        if (standaloneField) {
            standaloneField.style.display = 'none';
        }

        const relatedFlashHelp = getEl('sf-related-flash-help');
        if (relatedFlashHelp) {
            relatedFlashHelp.style.display = 'none';
        }

        // Show worksite fields directly (investigation reports are locked after creation)
        worksiteSection.classList.remove('hidden');
        worksiteSection.style.display = '';

        // Update progress indicators
        if (typeof window.SFUpdateProgress === 'function') {
            window.SFUpdateProgress();
        }

        return; // Skip binding events - context is locked
    }

    console.log('[Investigation Context] Create mode or red/yellow type - normal behavior');

    function updateFieldsVisibility() {
        const hasRelatedFlash = relatedFlashSelect.value && relatedFlashSelect.value !== '';
        const isStandalone = standaloneToggle.checked;

        console.log('[Investigation Context] hasRelatedFlash:', hasRelatedFlash, 'isStandalone:', isStandalone);

        if (isStandalone) {
            // Standalone mode: hide related flash, show empty worksite fields
            if (relatedFlashField) {
                relatedFlashField.style.display = 'none';
            }

            // Show related flash help text (hidden)
            const relatedFlashHelp = getEl('sf-related-flash-help');
            if (relatedFlashHelp) {
                relatedFlashHelp.style.display = 'none';
            }

            // Clear worksite fields if they came from related flash
            clearWorksiteFields();

            // Show worksite fields
            worksiteSection.classList.remove('hidden');
            worksiteSection.style.display = '';

        } else if (hasRelatedFlash) {
            // Base flash selected: show related flash field, show prefilled worksite fields
            if (relatedFlashField) {
                relatedFlashField.style.display = '';
            }

            const relatedFlashHelp = getEl('sf-related-flash-help');
            if (relatedFlashHelp) {
                relatedFlashHelp.style.display = '';
            }

            // Prefill and show worksite fields (handled by related-flash.js)
            worksiteSection.classList.remove('hidden');
            worksiteSection.style.display = '';

        } else {
            // Nothing selected yet: show both options, hide worksite fields
            if (relatedFlashField) {
                relatedFlashField.style.display = '';
            }

            const relatedFlashHelp = getEl('sf-related-flash-help');
            if (relatedFlashHelp) {
                relatedFlashHelp.style.display = '';
            }

            // Hide worksite fields
            worksiteSection.classList.add('hidden');
            worksiteSection.style.display = 'none';
        }

        // Update progress indicators
        if (typeof window.SFUpdateProgress === 'function') {
            window.SFUpdateProgress();
        }
    }

    function clearWorksiteFields() {
        const worksiteInput = getEl('sf-worksite');
        const siteDetailInput = getEl('sf-site-detail');
        const dateInput = getEl('sf-date');

        // Only clear if values came from related flash
        if (worksiteInput && worksiteInput.dataset.fromRelated === '1') {
            worksiteInput.value = '';
            worksiteInput.dataset.fromRelated = '';
        }
        if (siteDetailInput && siteDetailInput.dataset.fromRelated === '1') {
            siteDetailInput.value = '';
            siteDetailInput.dataset.fromRelated = '';
        }
        if (dateInput && dateInput.dataset.fromRelated === '1') {
            dateInput.value = '';
            dateInput.dataset.fromRelated = '';
        }
    }

    // Bind events
    relatedFlashSelect.addEventListener('change', function () {
        console.log('[Investigation Context] Related flash changed:', this.value);
        updateFieldsVisibility();
    });

    standaloneToggle.addEventListener('change', function () {
        console.log('[Investigation Context] Standalone toggle changed:', this.checked);
        updateFieldsVisibility();
    });

    // Initial state
    updateFieldsVisibility();
}

/**
 * Mark fields as coming from related flash (for clearing logic)
 */
export function markFieldsFromRelatedFlash() {
    const worksiteInput = getEl('sf-worksite');
    const siteDetailInput = getEl('sf-site-detail');
    const dateInput = getEl('sf-date');

    if (worksiteInput) worksiteInput.dataset.fromRelated = '1';
    if (siteDetailInput) siteDetailInput.dataset.fromRelated = '1';
    if (dateInput) dateInput.dataset.fromRelated = '1';
}