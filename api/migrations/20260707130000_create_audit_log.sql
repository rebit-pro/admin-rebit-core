CREATE TABLE IF NOT EXISTS audit_log (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    actor_id     BIGINT REFERENCES auth_users(id) ON DELETE SET NULL,
    action       VARCHAR(128) NOT NULL,
    subject_type VARCHAR(64),
    subject_id   VARCHAR(64),
    changes      JSONB NOT NULL DEFAULT '{}',
    ip           INET,
    user_agent   VARCHAR(255),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS ix_audit_actor ON audit_log (actor_id, created_at DESC);
CREATE INDEX IF NOT EXISTS ix_audit_subject ON audit_log (subject_type, subject_id);
CREATE INDEX IF NOT EXISTS ix_audit_action ON audit_log (action, created_at DESC);
