/**
 * Safetyflash - Communications Modal (Multi-step)
 * Handles the 4-step "Send to Communications" modal workflow
 */
(function () {
    'use strict';

    // Utilities
    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

    // Get terms from window.SF_TERMS
    function getTerm(key, fallback) {
        return (window.SF_TERMS && window.SF_TERMS[key]) || fallback || key;
    }

    // Sync chip visual state with checkbox state
    function syncChipState(chip) {
        var checkbox = chip.querySelector('input[type="checkbox"]');
        if (checkbox) {
            chip.classList.toggle('selected', checkbox.checked);
        }
    }

    function initCommsModal() {
        // IMPORTANT: Check if modal exists on this page
        var modal = document.getElementById('modalToComms');
        if (!modal) return;

        var currentStep = 1;
        var totalSteps = 4;

        // Navigation buttons
        var btnStep1Next = document.getElementById('btnCommsStep1Next');
        var btnStep2Back = document.getElementById('btnCommsStep2Back');
        var btnStep2Next = document.getElementById('btnCommsStep2Next');
        var btnStep3Back = document.getElementById('btnCommsStep3Back');
        var btnStep3Next = document.getElementById('btnCommsStep3Next');
        var btnStep4Back = document.getElementById('btnCommsStep4Back');
        var btnCommsSend = document.getElementById('btnCommsSend');

        // Screens radio toggle
        var screensAll = document.getElementById('screensAll');
        var screensSelected = document.getElementById('screensSelected');
        var screensSelection = document.getElementById('commsScreensSelection');

        // Language chips toggle behavior - FIXED
        qsa('.sf-chip-toggle').forEach(function (chip) {
            var checkbox = chip.querySelector('input[type="checkbox"]');
            if (!checkbox) return;

            // Handle checkbox change to update visual state
            checkbox.addEventListener('change', function () {
                syncChipState(chip);
            });

            // Initialize state
            syncChipState(chip);
        });

        // Flag chips toggle behavior
        qsa('.sf-flag-chip').forEach(function (label) {
            label.addEventListener('click', function (e) {
                e.preventDefault();
                var input = this.querySelector('input[type="checkbox"]');
                if (input) {
                    input.checked = !input.checked;
                    // Trigger change event for any listeners
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        // Show/hide worksite selection based on radio choice
        function updatePanelVisibility() {
            if (!screensSelection) return;

            if (screensAll && screensAll.checked) {
                screensSelection.classList.add('hidden');
            } else {
                screensSelection.classList.remove('hidden');
            }
        }

        // Initialize panel visibility on load
        updatePanelVisibility();

        if (screensAll && screensSelected && screensSelection) {
            [screensAll, screensSelected].forEach(function (radio) {
                radio.addEventListener('change', updatePanelVisibility);
            });
        }

        // Toggle wider distribution label
        var widerDistribution = document.getElementById('widerDistribution');
        var widerDistributionLabel = document.getElementById('widerDistributionLabel');

        if (widerDistribution && widerDistributionLabel) {
            widerDistribution.addEventListener('change', function () {
                if (this.checked) {
                    widerDistributionLabel.textContent = getTerm('comms_wider_distribution_yes', 'Kyllä, lähetä laajempaan jakeluun');
                } else {
                    widerDistributionLabel.textContent = getTerm('comms_wider_distribution_no', 'Ei, vain valitut näytöt');
                }
            });
        }

        function showStep(step) {
            for (var i = 1; i <= totalSteps; i++) {
                var stepEl = document.getElementById('commsStep' + i);
                if (stepEl) {
                    if (i === step) {
                        stepEl.classList.remove('hidden');
                    } else {
                        stepEl.classList.add('hidden');
                    }
                }
            }
            currentStep = step;

            // Update panel visibility when showing step 2
            if (step === 2) {
                updatePanelVisibility();
            }

            // Update summary when reaching step 4
            if (step === 4) {
                updateSummary();
            }
        }

        function updateSummary() {
            // Languages
            var selectedLangs = [];
            qsa('#commsForm input[name="languages[]"]:checked').forEach(function (input) {
                var chip = input.closest('.sf-chip-toggle');
                if (chip) {
                    var label = chip.querySelector('span');
                    if (label) selectedLangs.push(label.textContent);
                }
            });
            var langsSummary = document.getElementById('commsSummaryLanguages');
            if (langsSummary) {
                langsSummary.textContent = selectedLangs.length > 0 ? selectedLangs.join(', ') : getTerm('comms_summary_none', 'Ei valintoja');
            }

            // Screens/worksites summary - UPDATED TO SHOW COUNTRY NAMES
            var screensSummary = document.getElementById('commsSummaryScreens');
            if (screensSummary) {
                if (screensAll && screensAll.checked) {
                    screensSummary.textContent = getTerm('comms_all_countries', 'Kaikki maat');
                } else {
                    var parts = [];

                    // Add country names with flags
                    selectedCountries.forEach(function (code) {
                        var data = countryNames[code];
                        parts.push(data.flag + ' ' + data.name);
                    });

                    // Add individual worksite names
                    selectedWorksites.forEach(function (name) {
                        parts.push(name);
                    });

                    if (parts.length > 0) {
                        screensSummary.textContent = parts.join(', ');
                    } else {
                        screensSummary.textContent = getTerm('comms_summary_none', 'Ei valintoja');
                    }
                }
            }

            // Distribution (simplified toggle)
            var distSummary = document.getElementById('commsSummaryDistribution');
            if (distSummary) {
                var widerDist = document.getElementById('widerDistribution');
                if (widerDist && widerDist.checked) {
                    distSummary.textContent = getTerm('comms_summary_yes', 'Kyllä');
                } else {
                    distSummary.textContent = getTerm('comms_summary_no', 'Ei');
                }
            }
        }

        // Step navigation
        if (btnStep1Next) {
            btnStep1Next.addEventListener('click', function () {
                var selectedCount = qsa('#commsForm input[name="languages[]"]:checked').length;
                if (selectedCount === 0) {
                    alert(getTerm('comms_error_no_languages', 'Valitse vähintään yksi kieliversio'));
                    return;
                }
                showStep(2);
            });
        }

        if (btnStep2Back) {
            btnStep2Back.addEventListener('click', function () {
                showStep(1);
            });
        }

        if (btnStep2Next) {
            btnStep2Next.addEventListener('click', function () {
                showStep(3);
            });
        }

        if (btnStep3Back) {
            btnStep3Back.addEventListener('click', function () {
                showStep(2);
            });
        }

        if (btnStep3Next) {
            btnStep3Next.addEventListener('click', function () {
                showStep(4);
            });
        }

        if (btnStep4Back) {
            btnStep4Back.addEventListener('click', function () {
                showStep(3);
            });
        }

        // Form submission
        if (btnCommsSend) {
            btnCommsSend.addEventListener('click', function (e) {
                e.preventDefault();

                var form = document.getElementById('commsForm');
                if (!form) return;

                var formData = new FormData(form);

                // Add message from step 4
                var message = document.getElementById('commsMessage');
                if (message) {
                    formData.append('message', message.value);
                }

                // Add screens option
                var screensOption = qsa('input[name="screens_option"]:checked')[0];
                if (screensOption) {
                    formData.append('screens_option', screensOption.value);
                }

                // EXPLICITLY add selected countries
                qsa('.sf-country-chip-compact input[type="checkbox"]:checked').forEach(function (cb) {
                    formData.append('countries[]', cb.value);
                });

                // EXPLICITLY add selected worksites
                qsa('#worksiteSearchResults input[type="checkbox"]:checked').forEach(function (cb) {
                    formData.append('worksites[]', cb.value);
                });

                // Add wider distribution toggle
                var widerDist = document.getElementById('widerDistribution');
                if (widerDist && widerDist.checked) {
                    formData.append('wider_distribution', '1');
                }

                // Debug log
                console.log('=== DEBUG: Form submission ===');
                console.log('screens_option:', screensOption ? screensOption.value : 'none');

                var countryCbs = qsa('.sf-country-chip-compact input:checked');
                console.log('Selected countries:', countryCbs.length);
                countryCbs.forEach(function (cb) {
                    console.log('  - Country:', cb.value);
                });

                var worksiteCbs = qsa('#worksiteSearchResults input:checked');
                console.log('Selected worksites:', worksiteCbs.length);
                worksiteCbs.forEach(function (cb) {
                    console.log('  - Worksite ID:', cb.value);
                });

                console.log('Form data being sent:');
                for (var pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }

                // Get flash ID from URL or window
                var flashId = window.SF_FLASH_ID || new URLSearchParams(window.location.search).get('id');
                var baseUrl = window.SF_BASE_URL || '';

                // Show loading state
                btnCommsSend.disabled = true;
                btnCommsSend.textContent = getTerm('status_sending', 'Lähetetään...');

                // Submit via AJAX
                fetch(baseUrl + '/app/actions/send_to_comms.php?id=' + flashId, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                    .then(function (response) {
                        // Check if response is ok (2xx status)
                        if (response.ok) {
                            return response.json().catch(function () {
                                // If not JSON but response is OK, treat as success
                                return { ok: true };
                            });
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        if (data && data.ok === true) {
                            // Success - close modal and reload
                            if (window.closeModal) {
                                window.closeModal('modalToComms');
                            }
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            } else {
                                window.location.reload();
                            }
                        } else {
                            // Error
                            alert(data && data.message ? data.message : getTerm('error_sending', 'Virhe lähetyksessä'));
                            btnCommsSend.disabled = false;
                            btnCommsSend.textContent = getTerm('btn_send_comms', 'Lähetä viestintään');
                        }
                    })
                    .catch(function (err) {
                        console.error('Send to comms error:', err);
                        alert(getTerm('error_network', 'Verkkovirhe'));
                        btnCommsSend.disabled = false;
                        btnCommsSend.textContent = getTerm('btn_send_comms', 'Lähetä viestintään');
                    });
            });
        }

        // Worksite chip grid functionality - REPLACED WITH COUNTRY-BASED SELECTION
        // Country and worksite selection - COMPACT VERSION
        var countryChips = qsa('.sf-country-chip-compact');
        var worksiteSearch = document.getElementById('worksiteSearchInput');
        var worksiteResults = document.getElementById('worksiteSearchResults');
        var selectionDisplay = document.getElementById('selectionDisplay');
        var selectionTags = document.getElementById('selectionTags');

        // Country data for summary
        var countryNames = {
            'fi': { flag: '🇫🇮', name: getTerm('country_finland', 'Suomi') },
            'it': { flag: '🇮🇹', name: getTerm('country_italy', 'Italia') },
            'el': { flag: '🇬🇷', name: getTerm('country_greece', 'Kreikka') }
        };

        // Track selections
        var selectedCountries = new Set();
        var selectedWorksites = new Map(); // id -> name

        // Country chip toggle - FIXED: Listen to change event, not click
        countryChips.forEach(function (chip) {
            var checkbox = chip.querySelector('input[type="checkbox"]');
            if (!checkbox) return;

            // Listen to change event (triggered by native label behavior)
            checkbox.addEventListener('change', function () {
                // Update visual state
                chip.classList.toggle('selected', this.checked);
                updateSelectionDisplay();
            });

            // Initialize state
            chip.classList.toggle('selected', checkbox.checked);
        });

        // Worksite search - ONLY show results when typing
        if (worksiteSearch && worksiteResults) {
            // Ensure all results are hidden initially
            var allResults = worksiteResults.querySelectorAll('.sf-ws-result');
            allResults.forEach(function (r) {
                r.classList.add('hidden');
            });

            worksiteSearch.addEventListener('input', function () {
                var term = this.value.toLowerCase().trim();
                var results = worksiteResults.querySelectorAll('.sf-ws-result');
                var hasVisible = false;

                if (term.length === 0) {
                    // HIDE all results when search is empty
                    results.forEach(function (r) {
                        r.classList.add('hidden');
                    });
                    worksiteResults.classList.add('hidden');
                    return;
                }

                // Show results container and filter
                worksiteResults.classList.remove('hidden');

                results.forEach(function (result) {
                    var searchText = result.getAttribute('data-search') || '';
                    if (searchText.includes(term)) {
                        result.classList.remove('hidden');
                        hasVisible = true;
                    } else {
                        result.classList.add('hidden');
                    }
                });

                // Hide container if no matches
                if (!hasVisible) {
                    worksiteResults.classList.add('hidden');
                }
            });
        }

        // Worksite checkbox change handler
        if (worksiteResults) {
            worksiteResults.addEventListener('change', function (e) {
                if (e.target.type !== 'checkbox') return;

                var label = e.target.closest('.sf-ws-result');
                var id = e.target.value;
                var name = label.querySelector('.sf-ws-name').textContent;

                if (e.target.checked) {
                    selectedWorksites.set(id, name);
                } else {
                    selectedWorksites.delete(id);
                }

                updateSelectionDisplay();
            });
        }

        // Clear selections button
        var btnClearSelections = document.getElementById('btnClearSelections');
        if (btnClearSelections) {
            btnClearSelections.addEventListener('click', function () {
                // Clear country selections
                qsa('.sf-country-chip-compact').forEach(function (chip) {
                    chip.classList.remove('selected');
                    var cb = chip.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = false;
                });

                // Clear worksite selections
                qsa('#worksiteSearchResults input[type="checkbox"]').forEach(function (cb) {
                    cb.checked = false;
                });

                // Clear search field
                var searchInput = document.getElementById('worksiteSearchInput');
                if (searchInput) {
                    searchInput.value = '';
                    // Hide search results
                    var results = document.getElementById('worksiteSearchResults');
                    if (results) {
                        results.querySelectorAll('.sf-ws-result').forEach(function (r) {
                            r.classList.add('hidden');
                        });
                        results.classList.add('hidden');
                    }
                }

                // Explicitly clear data structures for clarity
                selectedCountries.clear();
                selectedWorksites.clear();

                // Update display (also rebuilds data structures from checkboxes)
                updateSelectionDisplay();
            });
        }

        // Update selection display
        function updateSelectionDisplay() {
            if (!selectionDisplay || !selectionTags) return;

            selectionTags.innerHTML = '';
            var hasSelection = false;

            // Clear and rebuild selectedCountries and selectedWorksites
            selectedCountries.clear();
            selectedWorksites.clear();

            // Add country tags
            qsa('.sf-country-chip-compact input:checked').forEach(function (cb) {
                hasSelection = true;
                var chip = cb.closest('.sf-country-chip-compact');
                var flag = chip.querySelector('.sf-cc-flag').textContent;
                var name = chip.querySelector('.sf-cc-name').textContent;
                var country = chip.getAttribute('data-country');

                // Update selectedCountries for summary
                selectedCountries.add(country);

                var tag = document.createElement('span');
                tag.className = 'sf-sel-tag';
                tag.innerHTML = flag + ' ' + name + ' <span class="sf-sel-tag-remove" data-type="country" data-value="' + country + '">×</span>';
                selectionTags.appendChild(tag);
            });

            // Add worksite tags
            qsa('#worksiteSearchResults input:checked').forEach(function (cb) {
                hasSelection = true;
                var label = cb.closest('.sf-ws-result');
                var name = label.querySelector('.sf-ws-name').textContent;
                var id = cb.value;

                // Update selectedWorksites for summary
                selectedWorksites.set(id, name);

                var tag = document.createElement('span');
                tag.className = 'sf-sel-tag';
                tag.innerHTML = name + ' <span class="sf-sel-tag-remove" data-type="worksite" data-value="' + id + '">×</span>';
                selectionTags.appendChild(tag);
            });

            // Show/hide display
            selectionDisplay.classList.toggle('hidden', !hasSelection);

            // Add remove handlers
            selectionTags.querySelectorAll('.sf-sel-tag-remove').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var type = this.getAttribute('data-type');
                    var value = this.getAttribute('data-value');

                    if (type === 'country') {
                        var chip = document.querySelector('.sf-country-chip-compact[data-country="' + value + '"]');
                        if (chip) {
                            chip.querySelector('input').checked = false;
                            chip.classList.remove('selected');
                        }
                    } else {
                        var cb = document.querySelector('#worksiteSearchResults input[value="' + value + '"]');
                        if (cb) cb.checked = false;
                    }

                    updateSelectionDisplay();
                });
            });
        }

        // Reset to step 1 when modal opens
        var commsModal = document.getElementById('modalToComms');
        if (commsModal) {
            // Watch for modal opening
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.attributeName === 'class') {
                        var isVisible = !commsModal.classList.contains('hidden');
                        if (isVisible) {
                            showStep(1);
                            // Re-sync chip visual states
                            qsa('.sf-chip-toggle').forEach(syncChipState);
                        }
                    }
                });
            });
            observer.observe(commsModal, { attributes: true });
        }
    }

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCommsModal);
    } else {
        initCommsModal();
    }
})();