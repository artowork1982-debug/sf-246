/**
 * SafetyFlash - Playlist Manager JS
 *
 * Ajolistan järjestyksen hallinta: ylös/alas-nuolet + drag & drop (HTML5).
 * Tallentaa järjestyksen /app/api/playlist_reorder.php -endpointille.
 *
 * @package SafetyFlash
 * @subpackage JS
 * @created 2026-02-22
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ── Display navigation select ──────────────────────────────────────
        var navSelect = document.getElementById('sfPmDisplaySelect');
        if (navSelect) {
            navSelect.addEventListener('change', function () {
                var val = this.value;
                var navUrl = this.dataset.navUrl;
                if (val && navUrl) {
                    window.location.href = navUrl + encodeURIComponent(val);
                }
            });
        }

        var list = document.getElementById('sfPlaylistItems');
        if (!list) return;

        var saveBtn = document.getElementById('sfPlaylistSaveBtn');
        var saveMsg = document.getElementById('sfPlaylistSaveMsg');
        var displayKeyId = parseInt(list.dataset.displayKeyId, 10);
        var reorderUrl   = list.dataset.reorderUrl;
        var csrfToken    = list.dataset.csrf;

        // ── Up/Down arrow buttons ──────────────────────────────────────────
        list.addEventListener('click', function (e) {
            var btn = e.target;
            if (!btn || !btn.matches('.sf-pm-btn-up, .sf-pm-btn-down')) return;

            var item = btn.closest('.sf-playlist-manager-item');
            if (!item) return;

            if (btn.classList.contains('sf-pm-btn-up')) {
                var prev = item.previousElementSibling;
                if (prev) list.insertBefore(item, prev);
            } else {
                var next = item.nextElementSibling;
                if (next) list.insertBefore(next, item);
            }

            refreshButtonStates();
        });

        // ── Drag & Drop (HTML5) ────────────────────────────────────────────
        var dragSrc = null;

        list.addEventListener('dragstart', function (e) {
            dragSrc = e.target.closest('.sf-playlist-manager-item');
            if (!dragSrc) return;
            dragSrc.classList.add('sf-pm-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var target = e.target.closest('.sf-playlist-manager-item');
            if (target && target !== dragSrc) {
                var rect = target.getBoundingClientRect();
                var midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    list.insertBefore(dragSrc, target);
                } else {
                    list.insertBefore(dragSrc, target.nextElementSibling);
                }
            }
        });

        list.addEventListener('dragend', function () {
            if (dragSrc) dragSrc.classList.remove('sf-pm-dragging');
            dragSrc = null;
            refreshButtonStates();
        });

        // Make items draggable
        Array.prototype.forEach.call(
            list.querySelectorAll('.sf-playlist-manager-item'),
            function (item) { item.setAttribute('draggable', 'true'); }
        );

        // ── Refresh button disabled states ─────────────────────────────────
        function refreshButtonStates() {
            var items = list.querySelectorAll('.sf-playlist-manager-item');
            items.forEach(function (item, idx) {
                var upBtn   = item.querySelector('.sf-pm-btn-up');
                var downBtn = item.querySelector('.sf-pm-btn-down');
                if (upBtn)   upBtn.disabled   = (idx === 0);
                if (downBtn) downBtn.disabled = (idx === items.length - 1);
            });
        }

        // ── Save order ─────────────────────────────────────────────────────
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                var items = list.querySelectorAll('.sf-playlist-manager-item');
                var order = [];
                items.forEach(function (item, idx) {
                    order.push({
                        flash_id:   parseInt(item.dataset.flashId, 10),
                        sort_order: idx
                    });
                });

                saveBtn.disabled = true;

                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        csrf_token:     csrfToken,
                        display_key_id: displayKeyId,
                        order:          order
                    })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        if (saveMsg) {
                            saveMsg.style.display = '';
                            setTimeout(function () { saveMsg.style.display = 'none'; }, 3000);
                        }
                    } else {
                        alert('Tallennus epäonnistui.');
                    }
                })
                .catch(function () {
                    alert('Verkkovirhe. Yritä uudelleen.');
                })
                .finally(function () {
                    saveBtn.disabled = false;
                });
            });
        }
    });
}());
