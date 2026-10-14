-- ============================================================
-- CTECHLIT & SYSTEMS — Migration 002
-- Public site settings consumed by index.php (the landing page).
-- All rows are is_public = 1 so they can be read without auth.
-- ============================================================

INSERT INTO system_settings (setting_key, setting_value, data_type, description, is_public) VALUES
('business_name',        'CTECHLIT & SYSTEMS', 'string', 'Business name shown in nav, header and footer', TRUE),
('business_tagline',     'Internet Café & Digital Services', 'string', 'Short tagline under the business name', TRUE),
('currency_symbol',      'GHS', 'string', 'Symbol prefixed to every displayed price', TRUE),
('hero_eyebrow',         'Internet Café & Digital Services', 'string', 'Hero section eyebrow text', TRUE),
('hero_title',           'A faster, friendlier place to browse, print and get things done', 'string', 'Hero headline', TRUE),
('hero_lead',            'Fast machines, a comfortable space, and staff who actually help — internet access, printing, and digital services, all under one roof.', 'string', 'Hero supporting paragraph', TRUE),
('hero_highlight',       'faster, friendlier', 'string', 'Phrase inside hero_title rendered in the accent style', TRUE),
('gap_first_card',       'Browsing, printing, typing, scanning, mobile money and more in one visit', 'string', 'Body copy of the first "why people choose us" card', TRUE),
('services_eyebrow',     'Services', 'string', 'Services section eyebrow text', TRUE),
('services_title',       'Everything one desk in Daffiama can now do', 'string', 'Services section headline', TRUE),
('services_lead',        'From an hour of browsing to a finished passport application — walk in, and walk out done.', 'string', 'Services section supporting text', TRUE),
('pricing_eyebrow',      'Pricing', 'string', 'Pricing section eyebrow text', TRUE),
('pricing_title',        'Straightforward rates', 'string', 'Pricing section headline', TRUE),
('pricing_lead',         'Indicative pricing — ask the front desk for the current full list.', 'string', 'Pricing section supporting text', TRUE),
('visit_eyebrow',        'Visit us', 'string', 'Visit section eyebrow text', TRUE),
('visit_title',          'Central Daffiama, easy to find', 'string', 'Visit section headline', TRUE),
('visit_lead',           'Near the Daffiama Senior High School & Technical Institute, along the Daffiama–Busie road.', 'string', 'Visit section supporting text', TRUE),
('contact_address',      'Central Daffiama, Daffiama-Bussie-Issa District, Upper West Region', 'string', 'Shop address', TRUE),
('contact_phone',        '0543 070 985', 'string', 'Display phone number', TRUE),
('contact_phone_raw',    '0543070985', 'string', 'Phone number in dialable format', TRUE),
('contact_email',        'ctechlit@gmail.com', 'string', 'Public email address', TRUE),
('opening_hours',        'Monday – Saturday, 7:00 AM – 8:00 PM', 'string', 'Opening hours shown on the landing page', TRUE),
('map_url',              'https://www.google.com/maps/search/?api=1&query=Central+Daffiama+Upper+West+Region', 'string', 'Get Directions target', TRUE),
('footer_note',          'Sole Proprietorship · Daffiama-Bussie-Issa District', 'string', 'Footer ownership line', TRUE),
('footer_about',         'Daffiama''s digital access, ICT training and technology support centre.', 'string', 'Footer about text', TRUE)
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    description   = VALUES(description),
    is_public     = VALUES(is_public);
