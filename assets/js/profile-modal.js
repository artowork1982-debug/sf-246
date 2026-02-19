
// assets/js/profile-modal.js
(function () {
    "use strict";

    const base = window.SF_BASE_URL || '';

    // ✅ Yhtenäinen helper toast-funktio
    function showToast(message, type = 'success') {
        if (typeof window.sfToast === 'function') {
            window.sfToast(type, message);
        }
    }

    // Profile tabs
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.sf-profile-tab');
        if (!tab) return;

        var tabName = tab.dataset.tab;
        var modal = tab.closest('.sf-modal');

        // Deactivate all tabs
        modal.querySelectorAll('.sf-profile-tab').forEach(function (t) {
            t.classList.remove('active');
        });
        modal.querySelectorAll('.sf-profile-tab-content').forEach(function (c) {
            c.classList.remove('active');
        });

        // Activate clicked tab
        tab.classList.add('active');
        var targetContent = modal.querySelector('[data-tab-content="' + tabName + '"]');
        if (targetContent) {
            targetContent.classList.add('active');
        }
    });

    // Open profile modal
    document.addEventListener('click', function (e) {
        const opener = e.target.closest('[data-modal-open="modalProfile"]');
        if (!opener) return;

        e.preventDefault();
        const profileTab = opener.dataset.profileTab;
        openProfileModal(profileTab);
    });

    async function openProfileModal(tabToOpen) {
        const modal = document.getElementById('modalProfile');
        if (!modal) return;

        try {
            const response = await fetch(base + '/app/api/profile_get.php');
            const data = await response.json();

            if (data.ok && data.user) {
                document.getElementById('modalProfileFirst').value = data.user.first_name || '';
                document.getElementById('modalProfileLast').value = data.user.last_name || '';
                document.getElementById('modalProfileEmail').value = data.user.email || '';
                document.getElementById('modalProfileRole').textContent = data.user.role_name || '-';

                const worksiteSelect = document.getElementById('modalProfileWorksite');
                if (worksiteSelect && data.worksites) {
                    const firstOption = worksiteSelect.options[0];
                    worksiteSelect.innerHTML = '';
                    worksiteSelect.appendChild(firstOption);

                    data.worksites.forEach(function (ws) {
                        const option = document.createElement('option');
                        option.value = ws.id;
                        option.textContent = ws.name;
                        if (parseInt(ws.id) === parseInt(data.user.home_worksite_id || 0)) {
                            option.selected = true;
                        }
                        worksiteSelect.appendChild(option);
                    });
                }

                // Set email notifications checkbox
                const emailNotifCheckbox = document.getElementById('modalProfileEmailNotifications');
                if (emailNotifCheckbox && data.user.email_notifications_enabled !== undefined) {
                    emailNotifCheckbox.checked = data.user.email_notifications_enabled == 1;
                }
            }
        } catch (err) {
            console.error('Error loading profile:', err);
        }

        // Reset to first tab or specified tab
        modal.querySelectorAll('.sf-profile-tab').forEach(function (t) {
            t.classList.remove('active');
        });
        modal.querySelectorAll('.sf-profile-tab-content').forEach(function (c) {
            c.classList.remove('active');
        });

        // If a specific tab is requested, activate it; otherwise default to basics
        const targetTab = tabToOpen || 'basics';
        // Validate tab name to prevent injection
        const validTabs = ['basics', 'settings', 'password'];
        const safeTab = validTabs.includes(targetTab) ? targetTab : 'basics';
        const tabButton = modal.querySelector(`[data-tab="${safeTab}"]`);
        const tabContent = modal.querySelector(`[data-tab-content="${safeTab}"]`);
        if (tabButton) tabButton.classList.add('active');
        if (tabContent) tabContent.classList.add('active');

        modal.classList.remove('hidden');
    }

    // Save profile (Basics tab)
    const profileForm = document.getElementById('sfProfileModalForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch(base + '/app/api/profile_update.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.ok) {
                    // Close modal
                    const modal = document.getElementById('modalProfile');
                    if (modal) {
                        modal.classList.add('hidden');
                    }

                    // Reload page to reflect changes
                    window.location.reload();
                } else {
                    showToast(result.error || (window.SF_I18N?.error || 'Virhe tallennuksessa'), 'error');
                }
            } catch (err) {
                console.error('Profile update error:', err);
                showToast(window.SF_I18N?.error || 'Virhe tallennuksessa', 'error');
            }
        });
    }

    // Save settings (Settings tab)
    const settingsForm = document.getElementById('sfProfileSettingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch(base + '/app/api/profile_update.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.ok) {
                    // Close modal
                    const modal = document.getElementById('modalProfile');
                    if (modal) {
                        modal.classList.add('hidden');
                    }

                    // Reload page to reflect home worksite change (affects list filtering)
                    window.location.reload();
                } else {
                    showToast(result.error || (window.SF_I18N?.error || 'Virhe tallennuksessa'), 'error');
                }
            } catch (err) {
                console.error('Profile update error:', err);
                showToast(window.SF_I18N?.error || 'Virhe tallennuksessa', 'error');
            }
        });
    }

    // Change password
    const passwordForm = document.getElementById('sfPasswordModalForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const newPass = document.getElementById('modalNewPassword').value;
            const confirmPass = document.getElementById('modalConfirmPassword').value;

            if (newPass !== confirmPass) {
                showToast(window.SF_I18N?.passwordsMismatch || 'Salasanat eivät täsmää', 'error');
                return;
            }

            const formData = new FormData(this);

            try {
                const response = await fetch(base + '/app/api/profile_password.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.ok) {
                    // Clear password fields
                    this.reset();

                    // Show simple feedback and stay on modal
                    showToast(window.SF_I18N?.passwordChanged || 'Salasana vaihdettu onnistuneesti!', 'success');
                } else {
                    showToast(result.error || (window.SF_I18N?.error || 'Virhe salasanan vaihdossa'), 'error');
                }
            } catch (err) {
                console.error('Password change error:', err);
                showToast(window.SF_I18N?.error || 'Virhe salasanan vaihdossa', 'error');
            }
        });
    }
})();