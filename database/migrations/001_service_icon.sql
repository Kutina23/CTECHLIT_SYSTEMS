-- ============================================================
-- CTECHLIT & SYSTEMS — Migration 001
-- Add an icon field to services so the public landing page can
-- render Font Awesome icons straight from the database.
-- ============================================================

ALTER TABLE services
    ADD COLUMN icon VARCHAR(80) NULL AFTER description;

UPDATE services SET icon = 'fa-solid fa-globe'          WHERE category = 'internet';
UPDATE services SET icon = 'fa-solid fa-print'          WHERE category = 'printing';
UPDATE services SET icon = 'fa-solid fa-file-lines'     WHERE category = 'scanning';
UPDATE services SET icon = 'fa-solid fa-gamepad'        WHERE category = 'gaming';
UPDATE services SET icon = 'fa-solid fa-pen-to-square'  WHERE category = 'typing';
UPDATE services SET icon = 'fa-solid fa-money-bill'     WHERE category = 'mobile_money';
UPDATE services SET icon = 'fa-solid fa-headset'        WHERE category = 'consultation';
UPDATE services SET icon = 'fa-solid fa-circle-info'    WHERE category = 'other';
