SET NAMES utf8mb4;

INSERT INTO users (id, name, email, password_hash, role) VALUES
    (1, 'System Admin', 'admin@example.com', '$2y$12$QHmkCbo2i/DAlSGDPx3j8.DIhC51FzPrepPbpx/M8fM0VF.RHxzVy', 'admin'),
    (2, 'Main Moderator', 'moderator@example.com', '$2y$12$BEuTBrhjU4wgIrmMDAAlUuxbdh0.iQ35Xm5sYTwm0REmaaoL6KR6u', 'moderator'),
    (3, 'User One', 'user1@example.com', '$2y$12$kdPq.uwzhGn8BSHb86tLSOx4O56liQlNF0d2svp8525WAlzWnf9DK', 'user'),
    (4, 'User Two', 'user2@example.com', '$2y$12$kdPq.uwzhGn8BSHb86tLSOx4O56liQlNF0d2svp8525WAlzWnf9DK', 'user');

INSERT INTO topics (id, title, description, author_id) VALUES
    (1, 'Доклад по сигурност', 'Вътрешен доклад за процедурите по сигурност и последващите рецензии.', 3),
    (2, 'Процес по анонимно докладване', 'Тема за управление на анонимни сигнали и вътрешни отговори.', 4);

INSERT INTO `groups` (id, name, description, created_by) VALUES
    (1, 'Moderators', 'Група за модератори и рецензенти.', 1),
    (2, 'Research Team', 'Екип за доклади, реферати и вътрешни рецензии.', 1);

INSERT INTO group_members (group_id, user_id) VALUES
    (1, 2),
    (2, 3),
    (2, 4);

INSERT INTO anonymous_boxes (id, display_name, real_user_id, topic_id, created_by) VALUES
    (1, 'Review Box A', 2, 1, 1),
    (2, 'Ethics Box', 4, 2, 1);

INSERT INTO review_assignments (topic_id, reviewer_user_id, anonymous_box_id, assigned_by, deadline) VALUES
    (1, 2, 1, 1, '2026-06-15 17:00:00'),
    (2, 3, 2, 1, '2026-06-20 17:00:00');

INSERT INTO conversations (id, subject, topic_id) VALUES
    (1, 'Рецензия на доклад по сигурност', 1),
    (2, 'Анонимен сигнал по процес', 2);

INSERT INTO messages (
    id,
    conversation_id,
    sender_user_id,
    sender_box_id,
    subject,
    body,
    message_type,
    status,
    parent_message_id
) VALUES
    (1, 1, 3, NULL, 'Първоначален доклад', 'Изпращам доклада за преглед и обратна връзка.', 'normal', 'sent', NULL),
    (2, 1, NULL, 1, 'Рецензия по доклада', 'Има нужда от допълнителен раздел за оценка на риска.', 'review', 'approved', 1),
    (3, 2, NULL, 2, 'Анонимен сигнал', 'Подавам анонимен сигнал относно вътрешен процес.', 'normal', 'pending', NULL);

INSERT INTO message_recipients (message_id, recipient_user_id, recipient_box_id, is_read, read_at) VALUES
    (1, 2, NULL, 1, '2026-05-20 10:30:00'),
    (2, 3, NULL, 0, NULL),
    (3, 1, NULL, 0, NULL);

INSERT INTO rules (title, description, rule_type, start_date, end_date, is_active, created_by) VALUES
    ('Общи правила за вътрешна поща', 'Всички официални съобщения трябва да бъдат архивирани в системата.', 'general', '2026-01-01', NULL, 1, 1),
    ('Защита на анонимността', 'При анонимни сигнали се забранява публикуване на лични данни.', 'anonymity', '2026-01-01', NULL, 1, 1),
    ('Срок за рецензии', 'Рецензиите трябва да бъдат изпращани преди крайния срок на assignment-а.', 'review_deadline', '2026-01-01', NULL, 1, 1);

INSERT INTO moderation_queue (message_id, status, moderator_id, moderator_note, anonymity_violation, reviewed_at) VALUES
    (2, 'approved', 2, 'Рецензията е коректна и без чувствителни данни.', 0, '2026-05-20 11:00:00'),
    (3, 'pending', NULL, NULL, 0, NULL);

INSERT INTO audit_logs (user_id, action, details) VALUES
    (1, 'seed.users.created', 'Създадени са началните потребители за администрация и тест.'),
    (1, 'seed.rules.created', 'Добавени са базови правила за обща комуникация, анонимност и срокове за рецензии.'),
    (2, 'moderation.reviewed', 'Модераторът одобри примерна рецензия за тема 1.');
