-- HOFARS (Hostel Facility Damage Report System) - database schema
-- Import with:  mysql -u root -p < database/schema.sql
-- (or paste into phpMyAdmin / HeidiSQL in Laragon)

CREATE DATABASE IF NOT EXISTS hofars_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hofars_db;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','technician','admin') NOT NULL DEFAULT 'student',
  matric_number VARCHAR(30) NULL,
  avatar VARCHAR(64) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE facilities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  student_name VARCHAR(100) NOT NULL,
  matric_number VARCHAR(30) NOT NULL,
  room_number VARCHAR(20) NOT NULL,
  block_number VARCHAR(50) NOT NULL,
  facility_id INT UNSIGNED NOT NULL,
  description TEXT NOT NULL,
  photo VARCHAR(64) NULL,
  status ENUM('Pending','Assigned','In Progress','Under Review','Resolved','Cancelled') NOT NULL DEFAULT 'Pending',
  technician_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_facility FOREIGN KEY (facility_id) REFERENCES facilities (id),
  CONSTRAINT fk_reports_tech FOREIGN KEY (technician_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_reports_status ON reports (status);
CREATE INDEX idx_reports_user ON reports (user_id);
CREATE INDEX idx_reports_tech ON reports (technician_id);
CREATE INDEX idx_reports_created ON reports (created_at);

-- Work reports written by technicians for a job. A "final" update sends the job for admin review.
CREATE TABLE report_updates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  technician_id INT UNSIGNED NOT NULL,
  progress_description TEXT NOT NULL,
  is_final TINYINT(1) NOT NULL DEFAULT 0,
  review_status ENUM('None','Pending','Approved','Rejected') NOT NULL DEFAULT 'None',
  review_note VARCHAR(500) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_updates_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_updates_tech FOREIGN KEY (technician_id) REFERENCES users (id),
  CONSTRAINT fk_updates_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_updates_report ON report_updates (report_id);
CREATE INDEX idx_updates_review ON report_updates (review_status);

CREATE TABLE update_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  update_id INT UNSIGNED NOT NULL,
  stored_name VARCHAR(64) NOT NULL,
  original_name VARCHAR(150) NOT NULL,
  mime VARCHAR(50) NOT NULL,
  CONSTRAINT fk_files_update FOREIGN KEY (update_id) REFERENCES report_updates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_files_update ON update_files (update_id);

-- Status timeline shown on the report detail page.
CREATE TABLE report_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL,
  note VARCHAR(500) NOT NULL,
  actor_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_history_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_history_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_history_report ON report_history (report_id);

-- Counts AI chatbot calls so usage can be capped (see CHAT_HOURLY_LIMIT / CHAT_DAILY_LIMIT in .env).
CREATE TABLE chat_usage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_chat_user_time ON chat_usage (user_id, created_at);
CREATE INDEX idx_chat_time ON chat_usage (created_at);

INSERT INTO facilities (name) VALUES
  ('Tombol Pintu'),
  ('Kunci Bilik'),
  ('Pintu'),
  ('Kipas Siling/Dinding'),
  ('Langsir/Rel Langsir'),
  ('Tingkap'),
  ('Lampu'),
  ('Rak/Meja/Almari'),
  ('Lain-lain');
