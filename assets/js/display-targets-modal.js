/**
 * SafetyFlash - Display Targets Modal
 *
 * Handles open/close, chip toggle logic and AJAX save for
 * the "Infonäytöt" (display targets) management modal on the view page.
 */
(function () {
    'use strict';

    var modalId = 'displayTargetsModal';

    function openDisplayTargetsModal() {
        if (window._sf && window._sf.openModal) {
            window._sf.openModal(modalId);
        } else if (window.openModal) {
            window.openModal(modalId);
        } else {
            var el = document.getElementById(modalId);
            if (el) {
                el.classList.remove('hidden');
                document.body.classList.add('sf-modal-open');
            }
        }
        clearStatus();
    }

    function closeDisplayTargetsModal() {
        if (window._sf && window._sf.closeModal) {
            window._sf.closeModal(modalId);
        } else if (window.closeModal) {
            window.closeModal(modalId);
        } else {
            var el = document.getElementById(modalId);
            if (el) {
                el.classList.add('hidden');
                var anyOpen = document.querySelector('.sf-modal:not(.hidden)');
                if (!anyOpen) {
                    document.body.classList.remove('sf-modal-open');
                }
            }
        }
    }

    function clearStatus() {
        var statusEl = document.getElementById('dtSaveStatus');
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.className = 'sf-dt-status';
        }
    }

    function setStatus(msg, isError) {
        var statusEl = document.getElementById('dtSaveStatus');
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.className = 'sf-dt-status ' + (isError ? 'sf-dt-status-error' : 'sf-dt-status-ok');
    }

    // Chip toggle for display target checkboxes (visual state)
    function initChipToggles() {
        var chips = document.querySelectorAll('#displayTargetsModal .sf-display-chip');
        chips.forEach(function (chip) {
            var cb = chip.querySelector('.sf-display-chip-input');
            if (!cb) return;
            chip.addEventListener('click', function (e) {
                if (e.target === cb) return; // native checkbox handles itself
                e.preventDefault();
                cb.checked = !cb.checked;
                chip.classList.toggle('sf-display-chip-selected', cb.checked);
            });
        });
    }

    // Save handler
    function initSaveButton() {
        var btn = document.getElementById('btnSaveDisplayTargets');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var flashId = parseInt(btn.getAttribute('data-flash-id'), 10);
            if (!flashId) return;

            // Collect TTL
            var ttlInput = document.querySelector('#displayTargetsModal input[name="dt_display_ttl_days"]:checked');
            var ttlDays = ttlInput ? parseInt(ttlInput.value, 10) : 30;

            // Collect Duration
            var durationInput = document.querySelector('#displayTargetsModal input[name="dt_display_duration_seconds"]:checked');
            var durationSeconds = durationInput ? parseInt(durationInput.value, 10) : 30;

            // Collect selected display IDs
            var displayTargets = [];
            document.querySelectorAll('#displayTargetsModal .dt-display-chip-cb:checked').forEach(function (cb) {
                var val = parseInt(cb.value, 10);
                if (val > 0) displayTargets.push(val);
            });

            var payload = {
                flash_id: flashId,
                display_targets: displayTargets,
                display_ttl_days: ttlDays,
                display_duration_seconds: durationSeconds,
                csrf_token: window.SF_CSRF_TOKEN || ''
            };

            btn.disabled = true;
            clearStatus();

            var baseUrl = window.SF_BASE_URL || '';
            fetch(baseUrl + '/app/api/display_targets_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                btn.disabled = false;
                if (data && data.ok) {
                    setStatus(data.message || 'Tallennettu!', false);
                    // Reload page after short delay to reflect changes
                    setTimeout(function () {
                        closeDisplayTargetsModal();
                        window.location.reload();
                    }, 800);
                } else {
                    setStatus((data && data.error) ? data.error : 'Tallentaminen epäonnistui.', true);
                }
            })
            .catch(function () {
                btn.disabled = false;
                setStatus('Verkkovirhe. Yritä uudelleen.', true);
            });
        });
    }

    function init() {
        // Expose open/close globally so PHP-rendered button onclick can call them
        window.openDisplayTargetsModal = openDisplayTargetsModal;
        window.closeDisplayTargetsModal = closeDisplayTargetsModal;

        var openBtn = document.getElementById('footerDisplayTargets');
        if (openBtn) {
            openBtn.addEventListener('click', openDisplayTargetsModal);
        }

        initChipToggles();
        initSaveButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
