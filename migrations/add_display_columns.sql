-- Migration: Add display columns to sf_flashes table (consolidated, safe to re-run)
-- Purpose: Ensures all columns required by display_targets_save.php are present
-- Date: 2026-02-25

ALTER TABLE sf_flashes
    ADD COLUMN IF NOT EXISTS display_expires_at DATETIME DEFAULT NULL
        COMMENT 'Milloin flash poistuu automaattisesti infonäyttö-playlistasta. NULL = ei vanhenemista',
    ADD COLUMN IF NOT EXISTS display_removed_at DATETIME DEFAULT NULL
        COMMENT 'Manuaalinen poisto playlistasta. NULL = näytetään normaalisti',
    ADD COLUMN IF NOT EXISTS display_removed_by INT DEFAULT NULL
        COMMENT 'Kuka poisti playlistasta',
    ADD COLUMN IF NOT EXISTS display_duration_seconds INT DEFAULT 30
        COMMENT 'Kuinka monta sekuntia tämä flash näkyy infonäytöllä (oletus 30s)';
