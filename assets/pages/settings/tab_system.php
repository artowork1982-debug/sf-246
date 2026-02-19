<?php
// assets/pages/settings/tab_system.php
declare(strict_types=1);

$baseUrl = rtrim($config['base_url'] ?? '', '/');
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>

<style>
/* System settings styles */
.sf-settings-section {
    margin-bottom: 32px;
}

.sf-settings-section h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.sf-setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid #f1f5f9;
}

.sf-setting-row:last-child {
    border-bottom: none;
}

.sf-setting-info {
    flex: 1;
}

.sf-setting-info label {
    font-weight: 500;
    color: #1e293b;
    display: block;
    margin-bottom: 4px;
}

.sf-setting-description {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

.sf-setting-control {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: 24px;
}

.sf-input-small {
    width: 80px;
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
    text-align: center;
}

.sf-input-suffix {
    font-size: 14px;
    color: #64748b;
}

/* Toggle switch */
.sf-toggle {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}

.sf-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.sf-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: 0.3s;
    border-radius: 26px;
}

.sf-toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.sf-toggle input:checked + .sf-toggle-slider {
    background-color: #10b981;
}

.sf-toggle input:checked + .sf-toggle-slider:before {
    transform: translateX(22px);
}

/* Actions */
.sf-settings-actions {
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
    margin-top: 16px;
}
</style>

<div class="sf-settings-section">
    <h3><?= htmlspecialchars(sf_term('settings_list_page', $currentUiLang) ?? 'Lista-sivu', ENT_QUOTES, 'UTF-8') ?></h3>
    
    <div class="sf-setting-row">
        <div class="sf-setting-info">
            <label><?= htmlspecialchars(sf_term('settings_editing_indicator', $currentUiLang) ?? 'Näytä muokkaus-indikaattori', ENT_QUOTES, 'UTF-8') ?></label>
            <p class="sf-setting-description"><?= htmlspecialchars(sf_term('settings_editing_indicator_desc', $currentUiLang) ?? 'Näyttää listalla reaaliaikaisesti kuka muokkaa mitäkin SafetyFlashia', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="sf-setting-control">
            <label class="sf-toggle">
                <input type="checkbox" id="editing_indicator_enabled" name="editing_indicator_enabled" <?= sf_get_setting('editing_indicator_enabled', false) ? 'checked' : '' ?>>
                <span class="sf-toggle-slider"></span>
            </label>
        </div>
    </div>
    
    <div class="sf-setting-row">
        <div class="sf-setting-info">
            <label for="editing_indicator_interval"><?= htmlspecialchars(sf_term('settings_polling_interval', $currentUiLang) ?? 'Päivitysväli', ENT_QUOTES, 'UTF-8') ?></label>
        </div>
        <div class="sf-setting-control">
            <input type="number" id="editing_indicator_interval" name="editing_indicator_interval" value="<?= (int)sf_get_setting('editing_indicator_interval', 30) ?>" min="10" max="120" class="sf-input-small">
            <span class="sf-input-suffix"><?= htmlspecialchars(sf_term('seconds', $currentUiLang) ?? 'sekuntia', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
    
    <div class="sf-setting-row">
        <div class="sf-setting-info">
            <label for="soft_lock_timeout"><?= htmlspecialchars(sf_term('settings_lock_timeout', $currentUiLang) ?? 'Lukituksen vanhenemisaika', ENT_QUOTES, 'UTF-8') ?></label>
        </div>
        <div class="sf-setting-control">
            <input type="number" id="soft_lock_timeout" name="soft_lock_timeout" value="<?= (int)sf_get_setting('soft_lock_timeout', 15) ?>" min="5" max="60" class="sf-input-small">
            <span class="sf-input-suffix"><?= htmlspecialchars(sf_term('minutes', $currentUiLang) ?? 'minuuttia', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</div>

<div class="sf-settings-actions">
    <button type="button" id="saveSystemSettings" class="sf-btn sf-btn-primary"><?= htmlspecialchars(sf_term('btn_save', $currentUiLang) ?? 'Tallenna', ENT_QUOTES, 'UTF-8') ?></button>
</div>

<script>
(function() {
    'use strict';
    
    const baseUrl = window.SF_BASE_URL || '<?= $baseUrl ?>';
    const csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>';
    const saveBtn = document.getElementById('saveSystemSettings');
    
    if (saveBtn) {
        saveBtn.addEventListener('click', async function() {
            const data = {
                editing_indicator_enabled: document.getElementById('editing_indicator_enabled')?.checked || false,
                editing_indicator_interval: parseInt(document.getElementById('editing_indicator_interval')?.value || '30', 10),
                soft_lock_timeout: parseInt(document.getElementById('soft_lock_timeout')?.value || '15', 10)
            };
            
            saveBtn.disabled = true;
            saveBtn.textContent = '<?= htmlspecialchars(sf_term('saving', $currentUiLang) ?? 'Tallennetaan...', ENT_QUOTES, 'UTF-8') ?>';
            
            try {
                const response = await fetch(baseUrl + '/app/api/save_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(data)
                });
                
                if (response.ok) {
                    saveBtn.textContent = '<?= htmlspecialchars(sf_term('saved', $currentUiLang) ?? 'Tallennettu!', ENT_QUOTES, 'UTF-8') ?>';
                    setTimeout(() => {
                        saveBtn.textContent = '<?= htmlspecialchars(sf_term('btn_save', $currentUiLang) ?? 'Tallenna', ENT_QUOTES, 'UTF-8') ?>';
                        saveBtn.disabled = false;
                    }, 2000);
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    const errorMsg = errorData.error || '<?= htmlspecialchars(sf_term('save_error', $currentUiLang) ?? 'Tallennus epäonnistui', ENT_QUOTES, 'UTF-8') ?>';
                    throw new Error(errorMsg);
                }
            } catch (e) {
                alert(e.message || '<?= htmlspecialchars(sf_term('save_error', $currentUiLang) ?? 'Tallennus epäonnistui', ENT_QUOTES, 'UTF-8') ?>');
                saveBtn.textContent = '<?= htmlspecialchars(sf_term('btn_save', $currentUiLang) ?? 'Tallenna', ENT_QUOTES, 'UTF-8') ?>';
                saveBtn.disabled = false;
            }
        });
    }
})();
</script>