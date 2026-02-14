#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

usage() {
  cat <<'EOF'
Usage: bin/prod.sh <cmd> [options]

This is the production-oriented helper. It runs Docker Compose with:
  -f docker-compose.yml -f docker-compose.prod.yml

Commands:
  up [--no-build] [--seed]   Start prod stack, run migrations (and optionally seed demo)
  down                      Stop containers (keeps volumes)
  reset                     Stop containers + delete volumes (DB wipe) (asks confirmation)
  ps                        Show containers
  logs [service]            Tail logs (all or one service)
  migrate                   Run DB migrations inside php container
  seed                      Seed demo data inside php container
  sh                        Open a shell inside php container
  db                        Open a MariaDB shell inside db container
  help                      Show this help

Examples:
  bin/prod.sh up
  bin/prod.sh logs caddy
EOF
}

if ! command -v docker >/dev/null 2>&1; then
  echo "Error: docker is not installed." >&2
  exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "Error: docker compose is not available (docker compose v2 required)." >&2
  exit 1
fi

# Load env (required in prod).
if [[ -f "$ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "$ROOT/.env"
  set +a
fi

DB_NAME="${DB_NAME:-dueldesk}"
DB_USER="${DB_USER:-dueldesk}"
DB_PASSWORD="${DB_PASSWORD:-dueldesk}"

compose() {
  docker compose -f docker-compose.yml -f docker-compose.prod.yml "$@"
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

    echo "OK"
    ;;

  down)
    compose down
    ;;

  reset)
    echo "This will delete Docker volumes (DB wipe): db_data + uploads_data (+ caddy_data/caddy_config)" >&2
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

