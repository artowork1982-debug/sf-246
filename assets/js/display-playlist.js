/**
 * SafetyFlash - Display Playlist Management
 * 
 * JavaScript-logiikka infonäyttö-playlist-toiminnoille:
 * - TTL-chip valitsin
 * - Esikatselupäivämäärä
 * - Poista/Palauta-painikkeet
 * 
 * @package SafetyFlash
 * @subpackage JavaScript
 * @created 2026-02-19
 */

(function() {
    'use strict';
    
    /**
     * Alusta TTL-chip valitsin (julkaisumodaalissa)
     */
    function initTtlChips() {
        // Support multiple TTL sections on the same page (e.g. different modals)
        document.querySelectorAll('.sf-publish-ttl-section').forEach(function(container) {
            const chips = container.querySelectorAll('.sf-ttl-chip');
            const preview = container.querySelector('.sf-ttl-preview');
            const previewDate = container.querySelector('.sf-ttl-preview-date');

            if (!chips.length) {
                return;
            }

            // Päivitä esikatselu
            function updatePreview() {
                const selectedRadio = container.querySelector('.sf-ttl-radio:checked');
                if (!selectedRadio) {
                    return;
                }

                const days = parseInt(selectedRadio.value, 10);

                if (!preview) {
                    return;
                }

                if (days === 0) {
                    // Ei aikarajaa
                    preview.classList.add('sf-ttl-preview-hidden');
                    return;
                }

                preview.classList.remove('sf-ttl-preview-hidden');

                // Laske vanhenemispäivä
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + days);

                // Suomalainen päivämääräformaatti
                const formatted = expiryDate.toLocaleDateString('fi-FI', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                if (previewDate) {
                    previewDate.textContent = formatted;
                }
            }

            // Käsittele chip-klikkaukset
            chips.forEach(chip => {
                chip.addEventListener('click', function() {
                    // Poista selected-luokka kaikilta
                    chips.forEach(c => c.classList.remove('sf-ttl-chip-selected'));

                    // Lisää selected-luokka klikatulle
                    this.classList.add('sf-ttl-chip-selected');

                    // Valitse radio
                    const radio = this.querySelector('.sf-ttl-radio');
                    if (radio) {
                        radio.checked = true;
                    }

                    // Päivitä esikatselu
                    updatePreview();
                });
            });

            // Alusta esikatselu
            updatePreview();
        });
    }
    
    /**
     * Alusta display chip valitsimet (julkaisumodaalissa)
     */
    function initDisplayChips() {
        document.querySelectorAll('.sf-display-chip').forEach(function(chip) {
            var input = chip.querySelector('.sf-display-chip-input');
            if (input) {
                input.addEventListener('change', function() {
                    chip.classList.toggle('sf-display-chip-selected', this.checked);
                });
            }
        });
    }
    
    /**
    function initPlaylistButtons() {
        const btnRemove = document.getElementById('btnRemoveFromPlaylist');
        const btnRestore = document.getElementById('btnRestoreToPlaylist');
        
        if (btnRemove) {
            btnRemove.addEventListener('click', handleRemoveFromPlaylist);
        }
        
        if (btnRestore) {
            btnRestore.addEventListener('click', handleRestoreToPlaylist);
        }
    }
    
    /**
     * Poista flash playlistasta
     */
    function handleRemoveFromPlaylist(event) {
        const btn = event.target;
        const flashId = btn.getAttribute('data-flash-id');
        
        if (!flashId) {
            console.error('Flash ID not found');
            return;
        }
        
        // Vahvistus
        const confirmMsg = window.SF_TERMS?.confirm_remove_from_playlist || 
                          'Haluatko varmasti poistaa flashin infonäyttö-playlistasta?';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        btn.disabled = true;
        
        // Lähetä API-pyyntö
        sendPlaylistAction(flashId, 'remove')
            .then(response => {
                if (response.ok) {
                    // Lataa sivu uudelleen näyttääksesi päivitetyn statuksen
                    window.location.reload();
                } else {
                    alert(response.message || 'Virhe poistossa');
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Remove error:', error);
                alert('Verkkovirhe. Yritä uudelleen.');
                btn.disabled = false;
            });
    }
    
    /**
     * Palauta flash playlistaan
     */
    function handleRestoreToPlaylist(event) {
        const btn = event.target;
        const flashId = btn.getAttribute('data-flash-id');
        
        if (!flashId) {
            console.error('Flash ID not found');
            return;
        }
        
        btn.disabled = true;
        
        // Lähetä API-pyyntö
        sendPlaylistAction(flashId, 'restore')
            .then(response => {
                if (response.ok) {
                    // Lataa sivu uudelleen näyttääksesi päivitetyn statuksen
                    window.location.reload();
                } else {
                    alert(response.message || 'Virhe palautuksessa');
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Restore error:', error);
                alert('Verkkovirhe. Yritä uudelleen.');
                btn.disabled = false;
            });
    }
    
    /**
     * Lähetä playlist-toiminto API:lle
     */
    function sendPlaylistAction(flashId, action) {
        const baseUrl = window.SF_BASE_URL || '';
        const csrfToken = window.SF_CSRF_TOKEN || document.querySelector('[name="csrf_token"]')?.value || '';
        
        return fetch(baseUrl + '/app/api/display_playlist_manage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                flash_id: parseInt(flashId, 10),
                action: action,
                csrf_token: csrfToken
            })
        })
        .then(response => response.json());
    }
    
    /**
     * DOMContentLoaded - Alusta kaikki
     */
    document.addEventListener('DOMContentLoaded', function() {
        initTtlChips();
        initDisplayChips();
        initPlaylistButtons();
    });
    
})();
