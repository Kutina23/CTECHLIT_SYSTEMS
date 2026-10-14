-- Allow administrators to approve visitor feedback for the public testimonials section.
USE ctechlit_systems;

ALTER TABLE feedback
    ADD COLUMN testimonial_approved BOOLEAN NOT NULL DEFAULT FALSE AFTER resolved_at,
    ADD COLUMN testimonial_approved_by BIGINT UNSIGNED NULL AFTER testimonial_approved,
    ADD COLUMN testimonial_approved_at TIMESTAMP NULL AFTER testimonial_approved_by,
    ADD INDEX idx_testimonial_approved (testimonial_approved),
    ADD CONSTRAINT fk_feedback_testimonial_approved_by
        FOREIGN KEY (testimonial_approved_by) REFERENCES staff(staff_id) ON DELETE SET NULL;
