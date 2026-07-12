#!/usr/bin/env bash
# Публикация versioned Swarm config/secrets стека `admin` (форк эталона P2P под deploy-юзера).
# Отличия от эталона: свои дефолты путей/имён + проверка приватности секрет-файлов
# (fail при group/other-readable — docs/04-devops.md §8).
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  VERSION=42 ./deploy/swarm-publish-runtime.sh
  ./deploy/swarm-publish-runtime.sh 42

Environment overrides:
  BASE_DIR=/srv/admin-rebit-core/swarm
  BACKEND_ENV_FILE=/srv/admin-rebit-core/swarm/backend.env
  SECRETS_DIR=/srv/admin-rebit-core/swarm/secrets
  REQUIRED_SECRET_NAMES="admin_db_password admin_smartcaptcha_server_key"
  OPTIONAL_SECRET_NAMES="admin_backup_aws_secret_access_key admin_smtp_password admin_sentry_dsn"
  OUTPUT_ENV_FILE=/tmp/admin-swarm-runtime.env

Optional explicit object names:
  BACKEND_ENV_CONFIG_NAME
  <SECRET_FILE_NAME_UPPERCASE>_SECRET_NAME

Запускается на Swarm-manager от пользователя deploy (root не требуется и не используется).
Создаёт immutable versioned config/secrets, существующие объекты не трогает.
EOF
}

log() {
    printf '[swarm-runtime] %s\n' "$1"
}

fail() {
    printf '[swarm-runtime][error] %s\n' "$1" >&2
    exit 1
}

require_readable_file() {
    local file_path="$1"

    if [[ ! -f "$file_path" ]]; then
        fail "File not found: $file_path"
    fi

    if [[ ! -r "$file_path" ]]; then
        fail "File is not readable: $file_path"
    fi
}

require_private_file() {
    local file_path="$1"
    local mode

    require_readable_file "$file_path"

    mode="$(stat -c '%a' "$file_path")"

    if [[ "${mode: -2}" != '00' ]]; then
        fail "Secret file must not be group/other-readable (chmod 0600): $file_path (mode $mode)"
    fi
}

build_secret_env_var_name() {
    local secret_file_name="$1"

    printf '%s_SECRET_NAME' "$(printf '%s' "$secret_file_name" | tr '[:lower:]' '[:upper:]' | sed 's/[^A-Z0-9]/_/g')"
}

contains_secret_key() {
    local needle="$1"
    local secret_key

    for secret_key in "${SECRET_KEYS[@]:-}"; do
        if [[ "$secret_key" == "$needle" ]]; then
            return 0
        fi
    done

    return 1
}

register_secret_source() {
    local secret_key="$1"
    local source_file="$2"

    if contains_secret_key "$secret_key"; then
        return
    fi

    SECRET_KEYS+=("$secret_key")
    SECRET_SOURCE_FILES+=("$source_file")
}

resolve_required_secret_file() {
    local secret_key="$1"
    local secret_path="$SECRETS_DIR/$secret_key"

    if [[ -f "$secret_path" ]]; then
        require_private_file "$secret_path"
        printf '%s' "$secret_path"
        return
    fi

    fail "Required secret file not found: $secret_key (expected at $secret_path)"
}

resolve_optional_secret_file() {
    local secret_key="$1"
    local secret_path="$SECRETS_DIR/$secret_key"

    if [[ -f "$secret_path" ]]; then
        require_private_file "$secret_path"
        printf '%s' "$secret_path"
        return
    fi

    log "Optional secret file not found, skipping: $secret_key"
}

discover_secret_sources() {
    local secret_key
    local secret_path

    for secret_key in "${REQUIRED_SECRET_KEYS[@]}"; do
        if [[ -z "$secret_key" ]]; then
            continue
        fi

        secret_path="$(resolve_required_secret_file "$secret_key")"
        register_secret_source "$secret_key" "$secret_path"
    done

    for secret_key in "${OPTIONAL_SECRET_KEYS[@]}"; do
        if [[ -z "$secret_key" ]]; then
            continue
        fi

        secret_path="$(resolve_optional_secret_file "$secret_key")"

        if [[ -n "$secret_path" ]]; then
            register_secret_source "$secret_key" "$secret_path"
        fi
    done

    if [[ ! -d "$SECRETS_DIR" ]]; then
        return
    fi

    while IFS= read -r secret_key; do
        if [[ -z "$secret_key" ]] || contains_secret_key "$secret_key"; then
            continue
        fi

        secret_path="$SECRETS_DIR/$secret_key"
        require_private_file "$secret_path"
        register_secret_source "$secret_key" "$secret_path"
    done < <(find "$SECRETS_DIR" -mindepth 1 -maxdepth 1 -type f -printf '%f\n' | LC_ALL=C sort)
}

ensure_swarm_manager() {
    local swarm_state
    local control_available

    swarm_state="$(docker info --format '{{.Swarm.LocalNodeState}}')"
    control_available="$(docker info --format '{{.Swarm.ControlAvailable}}')"

    if [[ 'active' != "$swarm_state" ]]; then
        fail 'Docker Swarm is not active on this host'
    fi

    if [[ 'true' != "$control_available" ]]; then
        fail 'This node is not a Swarm manager'
    fi
}

create_config_if_missing() {
    local config_name="$1"
    local source_file="$2"

    if docker config inspect "$config_name" >/dev/null 2>&1; then
        log "Config already exists, keeping immutable version: $config_name"
        return
    fi

    docker config create "$config_name" "$source_file" >/dev/null
    log "Config created: $config_name"
}

create_secret_if_missing() {
    local secret_name="$1"
    local source_file="$2"

    if docker secret inspect "$secret_name" >/dev/null 2>&1; then
        log "Secret already exists, keeping immutable version: $secret_name"
        return
    fi

    docker secret create "$secret_name" "$source_file" >/dev/null
    log "Secret created: $secret_name"
}

write_output_env_file() {
    local output_file="$1"

    {
        printf 'BACKEND_ENV_CONFIG_NAME=%s\n' "$BACKEND_ENV_CONFIG_NAME"

        for output_env_line in "${OUTPUT_ENV_LINES[@]}"; do
            printf '%s\n' "$output_env_line"
        done
    } > "$output_file"

    log "Environment file written: $output_file"
}

if [[ "${1:-}" == '--help' || "${1:-}" == '-h' ]]; then
    usage
    exit 0
fi

VERSION="${VERSION:-${1:-}}"

if [[ -z "$VERSION" ]]; then
    usage
    fail 'VERSION is required'
fi

BASE_DIR="${BASE_DIR:-/srv/admin-rebit-core/swarm}"
BACKEND_ENV_FILE="${BACKEND_ENV_FILE:-$BASE_DIR/backend.env}"
SECRETS_DIR="${SECRETS_DIR:-$BASE_DIR/secrets}"
REQUIRED_SECRET_NAMES="${REQUIRED_SECRET_NAMES:-admin_db_password admin_smartcaptcha_server_key}"
OPTIONAL_SECRET_NAMES="${OPTIONAL_SECRET_NAMES:-admin_backup_aws_secret_access_key admin_smtp_password admin_sentry_dsn}"
OUTPUT_ENV_FILE="${OUTPUT_ENV_FILE:-}"

BACKEND_ENV_CONFIG_NAME="${BACKEND_ENV_CONFIG_NAME:-admin_backend_env_$VERSION}"

IFS=' ' read -r -a REQUIRED_SECRET_KEYS <<< "$REQUIRED_SECRET_NAMES"
IFS=' ' read -r -a OPTIONAL_SECRET_KEYS <<< "$OPTIONAL_SECRET_NAMES"

declare -a SECRET_KEYS=()
declare -a SECRET_SOURCE_FILES=()
declare -a OUTPUT_ENV_LINES=()

command -v docker >/dev/null 2>&1 || fail 'docker command is not available'

ensure_swarm_manager
require_readable_file "$BACKEND_ENV_FILE"
discover_secret_sources

create_config_if_missing "$BACKEND_ENV_CONFIG_NAME" "$BACKEND_ENV_FILE"

for secret_index in "${!SECRET_KEYS[@]}"; do
    secret_key="${SECRET_KEYS[$secret_index]}"
    secret_source_file="${SECRET_SOURCE_FILES[$secret_index]}"
    secret_env_var_name="$(build_secret_env_var_name "$secret_key")"
    secret_object_name="${!secret_env_var_name:-${secret_key}_$VERSION}"

    create_secret_if_missing "$secret_object_name" "$secret_source_file"
    OUTPUT_ENV_LINES+=("$secret_env_var_name=$secret_object_name")
done

if [[ -n "$OUTPUT_ENV_FILE" ]]; then
    write_output_env_file "$OUTPUT_ENV_FILE"
fi

printf '\nCreated or reused versioned Swarm objects:\n'
printf '  BACKEND_ENV_CONFIG_NAME=%s\n' "$BACKEND_ENV_CONFIG_NAME"

for output_env_line in "${OUTPUT_ENV_LINES[@]}"; do
    printf '  %s\n' "$output_env_line"
done

printf '\nUse the same VERSION as deployment BUILD_NUMBER so make deploy resolves identical names.\n'
printf 'Example:\n'
printf '  HOST=37.143.8.221 BUILD_NUMBER=%s REGISTRY=ghcr.io/rebit-pro IMAGE_TAG=<git-sha> make deploy\n' "$VERSION"
