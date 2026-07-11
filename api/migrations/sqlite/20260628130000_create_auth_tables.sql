CREATE TABLE IF NOT EXISTS auth_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS auth_registration_codes (
    email TEXT PRIMARY KEY,
    password_hash TEXT NOT NULL,
    name TEXT NOT NULL,
    code TEXT NOT NULL,
    code_expires_at TEXT NOT NULL,
    resend_available_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS auth_access_tokens (
    token_hash TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES auth_users(id) ON DELETE CASCADE,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_auth_access_tokens_user_id ON auth_access_tokens (user_id);
CREATE INDEX IF NOT EXISTS idx_auth_access_tokens_expires_at ON auth_access_tokens (expires_at);
CREATE INDEX IF NOT EXISTS idx_auth_registration_codes_expires_at ON auth_registration_codes (code_expires_at);
