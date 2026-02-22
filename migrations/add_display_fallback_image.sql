-- Migration: Add display fallback image setting
-- Purpose: Global fallback image shown on Xibo displays when playlist is empty
-- Date: 2026-02-22

INSERT INTO sf_settings (setting_key, setting_value, setting_type, description)
VALUES ('display_fallback_image', '', 'file', 'Fallback image for empty display playlist');
