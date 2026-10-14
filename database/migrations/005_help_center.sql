-- Public help center content and guest support ticket contact details.
-- Run after schema.sql.

USE ctechlit_systems;

CREATE TABLE IF NOT EXISTS help_resources (
    resource_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resource_type  ENUM('documentation', 'tutorial', 'faq') NOT NULL,
    title          VARCHAR(255) NOT NULL,
    content        MEDIUMTEXT NOT NULL,
    url            VARCHAR(2048) NULL,
    sort_order     INT NOT NULL DEFAULT 0,
    is_published   BOOLEAN NOT NULL DEFAULT TRUE,
    created_by     BIGINT UNSIGNED NULL,
    updated_by     BIGINT UNSIGNED NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_type_published_order (resource_type, is_published, sort_order),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB;

INSERT INTO help_resources (resource_type, title, content, sort_order, is_published)
SELECT 'faq', 'How do I navigate the dashboard?',
       'Use the left sidebar to navigate between sections. On mobile, tap the menu icon to open or close it.', 10, TRUE
WHERE NOT EXISTS (SELECT 1 FROM help_resources WHERE resource_type = 'faq' AND title = 'How do I navigate the dashboard?');

INSERT INTO help_resources (resource_type, title, content, sort_order, is_published)
SELECT 'faq', 'How do I update my profile?',
       'Go to Settings, then My Profile, to update your name, email, and phone number.', 20, TRUE
WHERE NOT EXISTS (SELECT 1 FROM help_resources WHERE resource_type = 'faq' AND title = 'How do I update my profile?');

INSERT INTO help_resources (resource_type, title, content, sort_order, is_published)
SELECT 'faq', 'How do I view my tasks?',
       'Click My Tasks in the sidebar to view assigned tasks and their status.', 30, TRUE
WHERE NOT EXISTS (SELECT 1 FROM help_resources WHERE resource_type = 'faq' AND title = 'How do I view my tasks?');

INSERT INTO help_resources (resource_type, title, content, sort_order, is_published)
SELECT 'faq', 'How do I contact support?',
       'Use the Contact Us page or submit a support ticket from the public Help menu.', 40, TRUE
WHERE NOT EXISTS (SELECT 1 FROM help_resources WHERE resource_type = 'faq' AND title = 'How do I contact support?');

SET @schema_name = DATABASE();
SET @add_requester_name = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = @schema_name AND table_name = 'support_tickets' AND column_name = 'requester_name') = 0,
    'ALTER TABLE support_tickets ADD COLUMN requester_name VARCHAR(100) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE add_requester_name_stmt FROM @add_requester_name;
EXECUTE add_requester_name_stmt;
DEALLOCATE PREPARE add_requester_name_stmt;

SET @add_requester_email = IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = @schema_name AND table_name = 'support_tickets' AND column_name = 'requester_email') = 0,
    'ALTER TABLE support_tickets ADD COLUMN requester_email VARCHAR(255) NULL AFTER requester_name',
    'SELECT 1'
);
PREPARE add_requester_email_stmt FROM @add_requester_email;
EXECUTE add_requester_email_stmt;
DEALLOCATE PREPARE add_requester_email_stmt;
