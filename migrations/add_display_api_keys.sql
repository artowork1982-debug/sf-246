-- Migration: Add API keys table for display playlist authentication
-- Purpose: Enable secure API key authentication for Xibo display endpoints
-- Date: 2026-02-19

CREATE TABLE sf_display_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    site VARCHAR(100) NOT NULL COMMENT 'Työmaan tunniste',
    label VARCHAR(255) DEFAULT NULL COMMENT 'Näytön nimi, esim. Helsinki toimisto 2.krs',
    lang VARCHAR(5) DEFAULT 'fi',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME DEFAULT NULL COMMENT 'Milloin viimeksi käytetty',
    last_used_ip VARCHAR(45) DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL COMMENT 'NULL = ei vanhene',
    INDEX idx_api_key (api_key),
    INDEX idx_site (site)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
