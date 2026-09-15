-- Перевод сотрудников на users и удаление legacy staff.
-- Выполнять только после резервной копии и проверки результата.
-- Полный SQL-дамп в production не импортировать.

ALTER TABLE users
    MODIFY role ENUM('admin','manager','cashier','scanner') NOT NULL DEFAULT 'manager';

-- Перенос legacy staff в users. Email копируется только если он ещё не занят.
INSERT INTO users
    (username, password_hash, full_name, role, is_active, email, created_at, updated_at, password_changed_at)
SELECT
    CONCAT('legacy_staff_', s.id),
    s.password_hash,
    s.full_name,
    CASE WHEN s.role IN ('admin', 'manager', 'cashier', 'scanner') THEN s.role ELSE 'manager' END,
    s.is_active,
    CASE
        WHEN NULLIF(s.email, '') IS NOT NULL
             AND NOT EXISTS (SELECT 1 FROM users u WHERE u.email = s.email)
        THEN s.email
        ELSE NULL
    END,
    s.created_at,
    s.updated_at,
    NULL
FROM staff s
LEFT JOIN users existing ON existing.username = CONCAT('legacy_staff_', s.id)
WHERE existing.id IS NULL;

-- Сначала снимаем старые ограничения, иначе перенос ID в users будет отклонён.
ALTER TABLE audit_logs
    DROP FOREIGN KEY fk_audit_logs_staff;

ALTER TABLE checkins
    DROP FOREIGN KEY fk_checkins_scanner;

ALTER TABLE refunds
    DROP FOREIGN KEY fk_refunds_processed_by;

ALTER TABLE tickets
    DROP FOREIGN KEY fk_tickets_sold_by;

-- Перенос ссылок на legacy staff в users.
UPDATE audit_logs a
JOIN users u ON u.username = CONCAT('legacy_staff_', a.staff_id)
SET a.user_id = COALESCE(a.user_id, u.id),
    a.staff_id = NULL
WHERE a.staff_id IS NOT NULL;

UPDATE checkins c
JOIN users u ON u.username = CONCAT('legacy_staff_', c.scanner_id)
SET c.scanner_id = u.id
WHERE c.scanner_id IS NOT NULL;

UPDATE refunds r
JOIN users u ON u.username = CONCAT('legacy_staff_', r.processed_by)
SET r.processed_by = u.id
WHERE r.processed_by IS NOT NULL;

UPDATE tickets t
JOIN users u ON u.username = CONCAT('legacy_staff_', t.sold_by_staff_id)
SET t.sold_by_staff_id = u.id
WHERE t.sold_by_staff_id IS NOT NULL;

-- Перенастройка внешних ключей с staff на users.
ALTER TABLE audit_logs
    DROP COLUMN staff_id;

ALTER TABLE checkins
    ADD CONSTRAINT fk_checkins_scanner_user
        FOREIGN KEY (scanner_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE refunds
    ADD CONSTRAINT fk_refunds_processed_by_user
        FOREIGN KEY (processed_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_sold_by_user
        FOREIGN KEY (sold_by_staff_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

DROP TABLE staff;
