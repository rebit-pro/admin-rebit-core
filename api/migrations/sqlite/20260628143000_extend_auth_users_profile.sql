ALTER TABLE auth_users ADD COLUMN login TEXT;
ALTER TABLE auth_users ADD COLUMN phone TEXT;
ALTER TABLE auth_users ADD COLUMN address TEXT;

UPDATE auth_users
SET login = substr(email, 1, instr(email, '@') - 1)
WHERE login IS NULL OR login = '';

CREATE UNIQUE INDEX IF NOT EXISTS uniq_auth_users_login ON auth_users (login);
