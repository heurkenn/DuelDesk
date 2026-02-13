#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

usage() {
  cat <<'EOF'
Usage: bin/dev.sh <cmd> [options]

Commands:
  up [--no-build] [--seed]   Start docker compose, run migrations (and optionally seed demo)
  down                      Stop containers (keeps volumes)
  reset                     Stop containers + delete volumes (DB wipe) (asks confirmation)
  ps                        Show containers
  logs [service]            Tail logs (all or one service: nginx/php/db)
  migrate                   Run DB migrations inside php container
  seed                      Seed demo data inside php container
  sh                        Open a shell inside php container
  db                        Open a MariaDB shell inside db container
  help                      Show this help

Examples:
  bin/dev.sh up --seed
  bin/dev.sh logs nginx
  bin/dev.sh db
EOF
}

if ! command -v docker >/dev/null 2>&1; then
  echo "Error: docker is not installed." >&2
  exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "Error: docker compose is not available (try Docker Desktop or docker-compose v2)." >&2
  exit 1
fi

# Load local env (optional). This is convenient for DB_* / APP_URL in helper commands.
if [[ -f "$ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "$ROOT/.env"
  set +a
fi

APP_URL="${APP_URL:-http://localhost:8080}"
DB_NAME="${DB_NAME:-dueldesk}"
DB_USER="${DB_USER:-dueldesk}"
DB_PASSWORD="${DB_PASSWORD:-dueldesk}"

compose() {
  docker compose "$@"
}

php() {
  compose exec -T php php "$@"
}

cmd="${1:-up}"
shift || true

case "$cmd" in
  help|-h|--help)
    usage
    ;;

  up)
    build=1
    seed=0

    while [[ $# -gt 0 ]]; do
      case "$1" in
        --no-build) build=0 ;;
        --seed) seed=1 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
      esac
      shift
    done

    if [[ $build -eq 1 ]]; then
      compose up -d --build
    else
      compose up -d
    fi

    php bin/migrate.php
    if [[ $seed -eq 1 ]]; then
      php bin/seed_demo.php
    fi

    echo "OK: $APP_URL"
    ;;

  down)
    compose down
    ;;

  reset)
    echo "This will delete Docker volumes (DB wipe): db_data + uploads_data" >&2
    read -r -p "Type 'reset' to continue: " answer
    if [[ "$answer" != "reset" ]]; then
      echo "Cancelled." >&2
      exit 1
    fi
    compose down -v --remove-orphans
    ;;

  ps)
    compose ps
    ;;

  logs)
    service="${1:-}"
    if [[ -n "$service" ]]; then
      shift || true
    fi
    compose logs -f --tail=200 ${service:+$service}
    ;;

  migrate)
    php bin/migrate.php
    ;;

  seed)
    php bin/seed_demo.php
    ;;

  sh)
    compose exec php sh
    ;;

  db)
    compose exec db env MYSQL_PWD="$DB_PASSWORD" mariadb -u"$DB_USER" "$DB_NAME"
    ;;

  *)
    echo "Unknown command: $cmd" >&2
    usage
    exit 1
    ;;
esac

