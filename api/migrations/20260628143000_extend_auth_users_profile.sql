ALTER TABLE auth_users ADD COLUMN IF NOT EXISTS login VARCHAR(80);
ALTER TABLE auth_users ADD COLUMN IF NOT EXISTS phone VARCHAR(32);
ALTER TABLE auth_users ADD COLUMN IF NOT EXISTS address VARCHAR(500);

UPDATE auth_users
SET login = split_part(email, '@', 1)
WHERE login IS NULL OR login = '';

ALTER TABLE auth_users ALTER COLUMN login SET NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_auth_users_login ON auth_users (login);
