-- Migration: add_display_targets
-- Välitaulu: mitkä flashit näytetään millä näytöillä
-- Date: 2026-02-19

CREATE TABLE sf_flash_display_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flash_id INT NOT NULL COMMENT 'sf_flashes.id (translation_group_id)',
    display_key_id INT NOT NULL COMMENT 'sf_display_api_keys.id (= näyttö)',
    is_active TINYINT(1) DEFAULT 0 COMMENT '0 = valittu mutta ei vielä julkaistu, 1 = julkaistu ja aktiivinen',
    selected_by INT DEFAULT NULL COMMENT 'Kuka valitsi (turvatiimi/viestintä user_id)',
    selected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME DEFAULT NULL COMMENT 'Milloin julkaistiin tälle näytölle',

    UNIQUE KEY uq_flash_display (flash_id, display_key_id),
    INDEX idx_flash_id (flash_id),
    INDEX idx_display_key (display_key_id),
    INDEX idx_active (is_active, display_key_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lisää ryhmittelykenttä ja järjestys näyttöihin
ALTER TABLE sf_display_api_keys
    ADD COLUMN site_group VARCHAR(100) DEFAULT NULL
        COMMENT 'Ryhmä, esim. "Helsinki", "Espoo" — ryhmittelyä varten UI:ssa',
    ADD COLUMN sort_order INT DEFAULT 0
        COMMENT 'Järjestys valintalistassa';
