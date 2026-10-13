-- Run this ONCE if you imported schema.sql before the chatbot was added.
-- (If you import the current schema.sql from scratch you do not need it.)
USE hofars_db;

CREATE TABLE IF NOT EXISTS chat_usage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX idx_chat_user_time (user_id, created_at),
  INDEX idx_chat_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
