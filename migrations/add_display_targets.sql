-- Migration: Add display targets table for flash-to-display mapping
-- Purpose: Enable per-language-version display selection for Xibo info screens
-- Date: 2026-02-19

CREATE TABLE sf_flash_display_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flash_id INT NOT NULL COMMENT 'sf_flashes.id — yksittäisen kieliversion ID, EI translation_group_id',
    display_key_id INT NOT NULL COMMENT 'sf_display_api_keys.id',
    is_active TINYINT(1) DEFAULT 0 COMMENT '1 = julkaistu, 0 = esiasetettu (ei vielä julkaistu)',
    selected_by INT DEFAULT NULL COMMENT 'sf_users.id',
    selected_at DATETIME DEFAULT NULL,
    activated_at DATETIME DEFAULT NULL COMMENT 'NULL = ei vielä aktivoitu',
    INDEX idx_flash_id (flash_id),
    INDEX idx_display_key_id (display_key_id),
    INDEX idx_is_active (is_active),
    UNIQUE KEY uq_flash_display (flash_id, display_key_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
