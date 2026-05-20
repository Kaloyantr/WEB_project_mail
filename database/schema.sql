SET NAMES utf8mb4;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'moderator', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    author_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_topics_author_id (author_id),
    KEY idx_topics_created_at (created_at),
    CONSTRAINT fk_topics_author_id
        FOREIGN KEY (author_id) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `groups` (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_groups_name (name),
    KEY idx_groups_created_by (created_by),
    KEY idx_groups_created_at (created_at),
    CONSTRAINT fk_groups_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE group_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_group_members_group_user (group_id, user_id),
    KEY idx_group_members_user_id (user_id),
    CONSTRAINT fk_group_members_group_id
        FOREIGN KEY (group_id) REFERENCES `groups`(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_group_members_user_id
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE anonymous_boxes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_name VARCHAR(150) NOT NULL,
    real_user_id INT UNSIGNED NOT NULL,
    topic_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_anonymous_boxes_topic_display_name (topic_id, display_name),
    KEY idx_anonymous_boxes_display_name (display_name),
    KEY idx_anonymous_boxes_real_user_id (real_user_id),
    KEY idx_anonymous_boxes_topic_id (topic_id),
    KEY idx_anonymous_boxes_created_by (created_by),
    CONSTRAINT fk_anonymous_boxes_real_user_id
        FOREIGN KEY (real_user_id) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_anonymous_boxes_topic_id
        FOREIGN KEY (topic_id) REFERENCES topics(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_anonymous_boxes_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE review_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id INT UNSIGNED NOT NULL,
    reviewer_user_id INT UNSIGNED NOT NULL,
    anonymous_box_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    deadline DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_assignments_topic_reviewer_box (topic_id, reviewer_user_id, anonymous_box_id),
    KEY idx_review_assignments_reviewer_user_id (reviewer_user_id),
    KEY idx_review_assignments_anonymous_box_id (anonymous_box_id),
    KEY idx_review_assignments_assigned_by (assigned_by),
    KEY idx_review_assignments_deadline (deadline),
    CONSTRAINT fk_review_assignments_topic_id
        FOREIGN KEY (topic_id) REFERENCES topics(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_review_assignments_reviewer_user_id
        FOREIGN KEY (reviewer_user_id) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_review_assignments_anonymous_box_id
        FOREIGN KEY (anonymous_box_id) REFERENCES anonymous_boxes(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_review_assignments_assigned_by
        FOREIGN KEY (assigned_by) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    topic_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conversations_topic_id (topic_id),
    KEY idx_conversations_created_at (created_at),
    CONSTRAINT fk_conversations_topic_id
        FOREIGN KEY (topic_id) REFERENCES topics(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_user_id INT UNSIGNED NULL,
    sender_box_id INT UNSIGNED NULL,
    subject VARCHAR(255) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    message_type ENUM('normal', 'review') NOT NULL DEFAULT 'normal',
    status ENUM('sent', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'sent',
    parent_message_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_conversation_id (conversation_id),
    KEY idx_messages_sender_user_id (sender_user_id),
    KEY idx_messages_sender_box_id (sender_box_id),
    KEY idx_messages_parent_message_id (parent_message_id),
    KEY idx_messages_type_status (message_type, status),
    KEY idx_messages_created_at (created_at),
    CONSTRAINT fk_messages_conversation_id
        FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_messages_sender_user_id
        FOREIGN KEY (sender_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_messages_sender_box_id
        FOREIGN KEY (sender_box_id) REFERENCES anonymous_boxes(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_messages_parent_message_id
        FOREIGN KEY (parent_message_id) REFERENCES messages(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    recipient_user_id INT UNSIGNED NULL,
    recipient_box_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    KEY idx_message_recipients_message_id (message_id),
    KEY idx_message_recipients_recipient_user_id (recipient_user_id),
    KEY idx_message_recipients_recipient_box_id (recipient_box_id),
    KEY idx_message_recipients_is_read (is_read),
    CONSTRAINT fk_message_recipients_message_id
        FOREIGN KEY (message_id) REFERENCES messages(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_message_recipients_recipient_user_id
        FOREIGN KEY (recipient_user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_message_recipients_recipient_box_id
        FOREIGN KEY (recipient_box_id) REFERENCES anonymous_boxes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    rule_type ENUM('general', 'anonymity', 'review_deadline') NOT NULL DEFAULT 'general',
    start_date DATE NULL,
    end_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rules_rule_type (rule_type),
    KEY idx_rules_is_active (is_active),
    KEY idx_rules_created_by (created_by),
    KEY idx_rules_dates (start_date, end_date),
    CONSTRAINT fk_rules_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE moderation_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    moderator_id INT UNSIGNED NULL,
    moderator_note TEXT NULL,
    anonymity_violation TINYINT(1) NOT NULL DEFAULT 0,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_moderation_queue_message_id (message_id),
    KEY idx_moderation_queue_status (status),
    KEY idx_moderation_queue_moderator_id (moderator_id),
    KEY idx_moderation_queue_reviewed_at (reviewed_at),
    CONSTRAINT fk_moderation_queue_message_id
        FOREIGN KEY (message_id) REFERENCES messages(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_moderation_queue_moderator_id
        FOREIGN KEY (moderator_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(150) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_action (action),
    KEY idx_audit_logs_created_at (created_at),
    CONSTRAINT fk_audit_logs_user_id
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
