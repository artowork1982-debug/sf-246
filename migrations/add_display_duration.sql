-- Migration: Add display duration column to sf_flashes table
-- Purpose: Enable per-image display duration for information displays
-- Date: 2026-02-19

ALTER TABLE sf_flashes
    ADD COLUMN display_duration_seconds INT DEFAULT 30
        COMMENT 'Kuinka monta sekuntia tämä flash näkyy infonäytöllä (oletus 30s)';
