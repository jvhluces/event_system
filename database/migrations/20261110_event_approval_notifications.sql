-- Event moderation, scheduling, and notification schema.
-- Existing events are approved to preserve current public visibility.
-- New event submissions use the default pending approval state.

ALTER TABLE events
    ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected')
        NOT NULL DEFAULT 'pending',
    ADD COLUMN admin_remarks VARCHAR(255) NULL,
    ADD COLUMN end_date DATETIME NULL;

UPDATE events
SET approval_status = 'approved'
WHERE approval_status = 'pending';

UPDATE events
SET end_date = DATE_ADD(event_date, INTERVAL 2 HOUR)
WHERE end_date IS NULL;

CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_read_created (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Older project installs used notification_id and did not store a destination link.
SET @rename_notification_id = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'
       AND COLUMN_NAME = 'notification_id') > 0,
    'ALTER TABLE notifications CHANGE COLUMN notification_id id INT(11) NOT NULL AUTO_INCREMENT',
    'SELECT 1'
);
PREPARE migration_statement FROM @rename_notification_id;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @add_notification_link = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'
       AND COLUMN_NAME = 'link') = 0,
    'ALTER TABLE notifications ADD COLUMN link VARCHAR(500) NULL AFTER message',
    'SELECT 1'
);
PREPARE migration_statement FROM @add_notification_link;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @add_notifications_fk = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'
       AND CONSTRAINT_NAME = 'fk_notifications_user') = 0,
    'ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE migration_statement FROM @add_notifications_fk;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
