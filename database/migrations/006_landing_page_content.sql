-- ============================================================
-- CTECHLIT & SYSTEMS — Migration 006
-- Landing page content tables for dynamic sections.
-- ============================================================

-- Testimonials shown in the Reviews section
CREATE TABLE IF NOT EXISTS testimonials (
    testimonial_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name    VARCHAR(100) NOT NULL,
    customer_role    VARCHAR(100) NOT NULL,
    content          TEXT NOT NULL,
    rating           TINYINT UNSIGNED DEFAULT 5,
    avatar_color     VARCHAR(20) DEFAULT 'violet',
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- Public FAQ items shown on the landing page
CREATE TABLE IF NOT EXISTS landing_faqs (
    faq_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question         VARCHAR(255) NOT NULL,
    answer           TEXT NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- Trust badges / partners shown in the Trusted by section
CREATE TABLE IF NOT EXISTS trust_badges (
    badge_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- How It Works steps
CREATE TABLE IF NOT EXISTS how_it_works (
    step_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    step_number      INT NOT NULL,
    title            VARCHAR(100) NOT NULL,
    description      TEXT NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- Roadmap items
CREATE TABLE IF NOT EXISTS roadmap_items (
    item_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period           VARCHAR(50) NOT NULL,
    title            VARCHAR(100) NOT NULL,
    description      TEXT NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- Personas (Who we serve)
CREATE TABLE IF NOT EXISTS personas (
    persona_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    description      TEXT NOT NULL,
    glyph            VARCHAR(10) NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- Gallery items (labels for the SVG tiles)
CREATE TABLE IF NOT EXISTS gallery_items (
    item_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label            VARCHAR(100) NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- Spotlight / Most requested items
CREATE TABLE IF NOT EXISTS spotlight_items (
    item_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    icon_class       VARCHAR(80) NOT NULL,
    title            VARCHAR(100) NOT NULL,
    description      TEXT NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- Why-here feature rows
CREATE TABLE IF NOT EXISTS why_features (
    feature_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_number   VARCHAR(10) NOT NULL,
    title            VARCHAR(100) NOT NULL,
    description      TEXT NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- About section feature blocks
CREATE TABLE IF NOT EXISTS about_features (
    feature_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(100) NOT NULL,
    description      TEXT NOT NULL,
    icon_class       VARCHAR(80) NOT NULL,
    sort_order       INT DEFAULT 0,
    is_published     BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_order (is_published, sort_order)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO testimonials (customer_name, customer_role, content, rating, avatar_color, sort_order) VALUES
('Ama S.', 'Student, Daffiama SHS', 'The attendant helped me fill out my university application form in under 20 minutes. I would have been stuck for hours on my own.', 5, 'violet', 1),
('Kofi M.', 'Traders\' Cooperative', 'I come here to print and scan my shop receipts. Fast machines, clean space, and the prices are fair. Better than traveling to Wa every time.', 5, 'cyan', 2),
('Abena T.', 'Remote Worker', 'The gaming bay is a hit with the family. Weekend tournaments keep the kids engaged, and I can get some work done on the same visit.', 5, 'magenta', 3);

INSERT INTO landing_faqs (question, answer, sort_order) VALUES
('How much does a browsing session cost?', 'Sessions start from as little as GHS 2 for 30 minutes. Ask the front desk for our full rate card — we also offer hourly, daily and weekly passes.', 1),
('Do I need to bring my own USB or documents?', 'Yes, bring your own USB drive or cloud login. We also provide printing from email and WhatsApp if you prefer not to carry a drive.', 2),
('Is there parking at the café?', 'Yes — informal parking is available right outside the building. The café is in central Daffiama, near the SHS & Technical Institute, so it is easy to find.', 3),
('Can I book a station in advance?', 'Walk-ins are welcome every day. For groups of 4 or more, call ahead and we will reserve a bay for you.', 4),
('What payment methods do you accept?', 'Cash and all major mobile money networks (MTN, Vodafone, AirtelTigo). Receipts are available on request.', 5);

INSERT INTO trust_badges (name, sort_order) VALUES
('Daffiama SHS', 1),
('Technical Institute', 2),
('Local Businesses', 3),
('Community Groups', 4);

INSERT INTO how_it_works (step_number, title, description, sort_order) VALUES
(1, 'Walk in', 'Two rooms, central Daffiama, near the SHS & Technical Institute.', 1),
(2, 'Tell us what you need', 'Browsing, printing, an application — the attendant points you right.', 2),
(3, 'Get guided help', 'We sit with you through the screen, not just past it.', 3),
(4, 'Pay & go', 'Cash or mobile money, receipt on request.', 4);

INSERT INTO roadmap_items (period, title, description, sort_order) VALUES
('NOW', 'The café', 'Six stations, full service, open daily.', 1),
('YEAR 2', 'ICT training', 'Off-peak courses in computer literacy & Office skills.', 2),
('YEAR 2–3', 'Cybersecurity awareness', 'Guidance for local shops, schools and cooperatives.', 3),
('ONGOING', 'Green initiative', 'Tree planting & responsible e-waste disposal.', 4);

INSERT INTO personas (name, description, glyph, sort_order) VALUES
('Students', 'Research, assignments and applications, without the wait.', 'Aa', 1),
('Job seekers', 'Recruitment portals and a properly formatted CV.', 'Jb', 2),
('Traders & small businesses', 'Printing, scanning and typing for everyday records.', 'Tc', 3),
('Remote workers', 'A reliable desk, fast Wi-Fi, and coffee within reach.', 'Td', 4);

INSERT INTO gallery_items (label, sort_order) VALUES
('LAN Workstation', 1),
('Workstation Row', 2),
('Cloud Connect', 3);

INSERT INTO spotlight_items (icon_class, title, description, sort_order) VALUES
('fa-solid fa-stamp', 'ID & official document forms', 'Correct formats and uploads, guided start to finish.', 1),
('fa-solid fa-file-signature', 'CVs & job applications', 'Properly formatted, printed and ready to send.', 2),
('fa-solid fa-scanner', 'Scanning & document repair', 'Clean digital copies of anything on paper.', 3),
('fa-solid fa-money-bill-transfer', 'Bill payments & online orders', 'Utility bills, deliveries and portals, handled with you.', 4);

INSERT INTO why_features (feature_number, title, description, sort_order) VALUES
('01', 'Warranty-backed hardware', 'Six brand-new Core i5 desktops, not refurbished units — chosen to keep downtime, and repair costs, out of your visit.', 1),
('02', 'Power you can rely on', 'UPS backup on every pair of stations keeps your session — and your unsaved form — running through "dumsor."', 2),
('03', 'Secure, monitored space', 'CCTV coverage and a supervised front desk, so parents and institutions alike trust who\'s on the machines.', 3);

INSERT INTO about_features (title, description, icon_class, sort_order) VALUES
('Brand-New Hardware', 'Core i5 desktops with full manufacturer warranty and latest security patches', 'fa-solid fa-microchip', 1),
('Guided Support', 'A trained attendant sits with you through the screen, not just past it', 'fa-solid fa-headset', 2),
('Comfortable Environment', 'Quiet corner with coffee and snacks for extended work sessions', 'fa-solid fa-mug-hot', 3);
