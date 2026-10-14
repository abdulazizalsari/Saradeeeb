-- Additive shared-session table for Vercel. Run once on the external MySQL database.
CREATE TABLE IF NOT EXISTS app_sessions (
  id VARCHAR(128) PRIMARY KEY,
  payload MEDIUMBLOB NOT NULL,
  expires_at DATETIME NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_app_sessions_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
