ALTER TABLE auth_users ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'active';
CREATE INDEX IF NOT EXISTS ix_auth_users_status ON auth_users (status);
