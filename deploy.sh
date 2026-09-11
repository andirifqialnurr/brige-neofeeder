#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

BRANCH="${DEPLOY_BRANCH:-main}"
COMPOSE="${COMPOSE_CMD:-docker compose}"

fail() {
  echo "ERROR: $*" >&2
  exit 1
}

info() {
  echo
  echo "==> $*"
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "$1 is required on this server."
}

copy_env_if_missing() {
  local example_file="$1"
  local target_file="$2"

  if [[ ! -f "$target_file" ]]; then
    cp "$example_file" "$target_file"
    echo "Created $target_file from $example_file."
    echo "Edit $target_file, then run ./deploy.sh again."
    return 1
  fi

  return 0
}

contains_placeholder() {
  local file="$1"

  grep -Eq 'bridge\.example\.com|example\.com|domain-anda|change_this|password-kuat|password-db-yang-sama' "$file"
}

env_value() {
  local file="$1"
  local key="$2"

  sed -n "s/^${key}=//p" "$file" | tail -n 1 | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

update_env_value() {
  local file="$1"
  local key="$2"
  local value="$3"
  local escaped_value

  escaped_value="$(printf '%s' "$value" | sed 's/[\/&]/\\&/g')"

  if grep -q "^${key}=" "$file"; then
    sed -i "s|^${key}=.*|${key}=${escaped_value}|" "$file"
  else
    printf '%s=%s\n' "$key" "$value" >> "$file"
  fi
}

wait_for_mysql() {
  info "Waiting for MySQL readiness ..."

  for attempt in {1..40}; do
    if $COMPOSE exec -T mysql sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null 2>&1; then
      echo "MySQL is ready."
      return 0
    fi

    if [[ "$attempt" -eq 40 ]]; then
      $COMPOSE logs --tail=120 mysql >&2 || true
      fail "MySQL did not become ready in time."
    fi

    sleep 2
  done
}

wait_for_redis() {
  info "Waiting for Redis readiness ..."

  for attempt in {1..30}; do
    if [[ "$($COMPOSE exec -T redis redis-cli ping 2>/dev/null || true)" = "PONG" ]]; then
      echo "Redis is ready."
      return 0
    fi

    if [[ "$attempt" -eq 30 ]]; then
      $COMPOSE logs --tail=80 redis >&2 || true
      fail "Redis did not become ready in time."
    fi

    sleep 2
  done
}

require_command git
require_command docker

info "Pulling latest code from origin/${BRANCH} ..."
git pull origin "$BRANCH"

env_ready=true
copy_env_if_missing .env.deploy.example .env || env_ready=false
copy_env_if_missing backend/.env.docker.example backend/.env || env_ready=false

if [[ "$env_ready" = false ]]; then
  fail "Environment files were created. Fill them first, then rerun deploy."
fi

if contains_placeholder .env || contains_placeholder backend/.env; then
  fail "Replace placeholder values in .env and backend/.env before deploy."
fi

root_db_name="$(env_value .env MYSQL_DATABASE)"
root_db_user="$(env_value .env MYSQL_USER)"
root_db_password="$(env_value .env MYSQL_PASSWORD)"
backend_db_name="$(env_value backend/.env DB_DATABASE)"
backend_db_user="$(env_value backend/.env DB_USERNAME)"
backend_db_password="$(env_value backend/.env DB_PASSWORD)"

[[ "$root_db_name" = "$backend_db_name" ]] || fail "MYSQL_DATABASE and DB_DATABASE must match."
[[ "$root_db_user" = "$backend_db_user" ]] || fail "MYSQL_USER and DB_USERNAME must match."
[[ "$root_db_password" = "$backend_db_password" ]] || fail "MYSQL_PASSWORD and DB_PASSWORD must match."

info "Building Docker images ..."
$COMPOSE build backend worker scheduler frontend

info "Starting MySQL and Redis ..."
$COMPOSE up -d mysql redis
wait_for_mysql
wait_for_redis

app_key="$(env_value backend/.env APP_KEY)"
if [[ -z "$app_key" ]]; then
  info "Generating Laravel APP_KEY ..."
  generated_key="$($COMPOSE run --rm --no-deps backend php artisan key:generate --show)"
  update_env_value backend/.env APP_KEY "$generated_key"
  echo "APP_KEY written to backend/.env."
fi

info "Starting application services ..."
$COMPOSE up -d --build backend worker scheduler frontend

info "Running database migrations ..."
$COMPOSE exec -T backend php artisan migrate --force

info "Seeding admin user ..."
$COMPOSE exec -T backend php artisan db:seed --force

info "Preparing Laravel runtime ..."
$COMPOSE exec -T backend php artisan storage:link || true
$COMPOSE exec -T backend php artisan optimize:clear
$COMPOSE exec -T backend php artisan optimize

info "Current containers"
$COMPOSE ps

info "Backend health"
if command -v curl >/dev/null 2>&1; then
  curl -fsS http://127.0.0.1:2000/api/health || true
  echo
else
  echo "curl is not installed; skipping local health request."
fi

info "Recent backend logs"
$COMPOSE logs --tail=80 backend
