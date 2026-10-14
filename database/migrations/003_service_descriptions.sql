-- ============================================================
-- CTECHLIT & SYSTEMS — Migration 003
-- Backfill service descriptions and icons that the original
-- seed rows left empty, so the public landing page always has
-- meaningful copy to render.
-- ============================================================

UPDATE services SET description = 'High-speed internet access on wired workstations' WHERE name = 'Internet Browsing' AND (description IS NULL OR description = '');
UPDATE services SET description = 'Full-colour document printing, photos and lamination' WHERE name = 'Color Printing' AND (description IS NULL OR description = '');
UPDATE services SET description = 'Monochrome document printing, best value per page' WHERE name = 'Black & White Printing' AND (description IS NULL OR description = '');
UPDATE services SET description = 'Scan documents to email, phone or USB stick' WHERE name = 'Document Scanning' AND (description IS NULL OR description = '');
UPDATE services SET description = 'High-performance rigs for solo runs and squad nights' WHERE name = 'Gaming Session' AND (description IS NULL OR description = '');
UPDATE services SET description = 'CVs, letters, applications and data entry, printed on the spot' WHERE name = 'Typing Services' AND (description IS NULL OR description = '');
UPDATE services SET description = 'Cash-in and cash-out at the same front desk' WHERE name = 'Mobile Money' AND (description IS NULL OR description = '');
UPDATE services SET description = 'One-to-one tech help on any device or form' WHERE name = '1-on-1 Consultation' AND (description IS NULL OR description = '');
