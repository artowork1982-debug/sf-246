-- Migration: Add display TTL and playlist management columns to sf_flashes table
-- Purpose: Enable Xibo information display integration with TTL and manual removal features
-- Date: 2026-02-19

ALTER TABLE sf_flashes
    ADD COLUMN display_expires_at DATETIME DEFAULT NULL 
        COMMENT 'Milloin flash poistuu automaattisesti infonäyttö-playlistasta. NULL = ei vanhenemista',
    ADD COLUMN display_removed_at DATETIME DEFAULT NULL
        COMMENT 'Manuaalinen poisto playlistasta. NULL = näytetään normaalisti',
    ADD COLUMN display_removed_by INT DEFAULT NULL
        COMMENT 'Kuka poisti playlistasta';

ALTER TABLE sf_flashes
    ADD INDEX idx_display_active (state, display_expires_at, display_removed_at);
