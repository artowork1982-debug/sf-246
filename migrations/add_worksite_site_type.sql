-- Migration: Add site_type to sf_worksites
-- Purpose: Allow categorising worksites as tunnel or opencast
-- Date: 2026-02-26

ALTER TABLE sf_worksites
    ADD COLUMN IF NOT EXISTS site_type VARCHAR(50) DEFAULT NULL
        COMMENT 'Worksite type: tunnel | opencast | NULL = unspecified' AFTER name;
