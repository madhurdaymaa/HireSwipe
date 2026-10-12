-- JobSwipe schema: import via phpMyAdmin (Import tab) or paste into the SQL tab
CREATE DATABASE IF NOT EXISTS jobswipe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jobswipe;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type     ENUM('employee','employer') NOT NULL,
  name          VARCHAR(100)  NOT NULL,
  mobile        VARCHAR(20)   NOT NULL UNIQUE,
  country       VARCHAR(80)   NOT NULL,
  description   TEXT          NOT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_type (user_type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS swipes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  swiper_id  INT UNSIGNED NOT NULL,
  target_id  INT UNSIGNED NOT NULL,
  direction  ENUM('left','right') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_swipe (swiper_id, target_id),
  FOREIGN KEY (swiper_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- A "match" = two rows where both users swiped 'right' on each other.
