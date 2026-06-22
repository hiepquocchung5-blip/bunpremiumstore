-- update_3.sql
-- Database update for Avatar / Profile Picture Upload and User In-App Notifications Feed

ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `avatar_path` VARCHAR(255) DEFAULT NULL AFTER `email`;

CREATE TABLE IF NOT EXISTS `user_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `url` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
