#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# DuelDesk smoke tests (Docker + HTTP).
# - Starts stack
# - Runs migrations + seed demo
# - Tests core pages + admin login
# - Tests 2XKO lineup duels UI presence
# - Tests Fall Guys multi-round: add rounds + finalize
#
# Usage:
#   ./test.sh
#
# Notes:
# - Requires `docker compose` and `curl`.
# - Uses default dev URL: http://localhost:8080

BASE_URL="${BASE_URL:-http://localhost:8080}"
ADMIN_USER="${ADMIN_USER:-dd_admin}"
ADMIN_PASS="${ADMIN_PASS:-password123}"
RESET_DB="${RESET_DB:-0}"          # set to 1 to run `docker compose down -v` before tests (destructive)
SKIP_AUTH_TESTS="${SKIP_AUTH_TESTS:-0}"  # set to 1 to skip login/admin + POST actions

TMP_DIR="${TMP_DIR:-/tmp/dueldesk-test.$$}"
COOKIE_JAR="$TMP_DIR/cookies.txt"

mkdir -p "$TMP_DIR"
trap 'rm -rf "$TMP_DIR"' EXIT

fail() { echo "FAIL: $*" >&2; exit 1; }
info() { echo "==> $*" >&2; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "missing command: $1"
}

http_get() {
  local url="$1"
  local out="$2"
  local code
  code="$(curl -sS -o "$out" -w "%{http_code}" "$url")" || return 1
  echo "$code"
}

http_get_auth() {
  local url="$1"
  local out="$2"
  local code
  code="$(curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -o "$out" -w "%{http_code}" "$url")" || return 1
  echo "$code"
}

http_post_auth() {
  local url="$1"
  local data_file="$2"
  local out="$3"
  local hdr="$4"
  local code
  code="$(curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -D "$hdr" -o "$out" -w "%{http_code}" -X POST \
    -H "Content-Type: application/x-www-form-urlencoded" \
    --data-binary @"$data_file" "$url")" || return 1
  echo "$code"
}

assert_code() {
  local got="$1"
  local want="$2"
  [[ "$got" == "$want" ]] || fail "expected HTTP $want, got $got"
}

assert_code_any() {
  local got="$1"; shift
  local ok="0"
  for w in "$@"; do
    if [[ "$got" == "$w" ]]; then ok="1"; break; fi
  done
  [[ "$ok" == "1" ]] || fail "unexpected HTTP $got (wanted: $*)"
}

assert_contains() {
  local file="$1"
  local needle="$2"
  rg -n --fixed-strings "$needle" "$file" >/dev/null 2>&1 || fail "missing text: $needle"
}

extract_csrf() {
  local file="$1"
  # <input type="hidden" name="csrf_token" value="...">
  sed -n 's/.*name="csrf_token"[[:space:]]\+value="\([^"]\+\)".*/\1/p' "$file" | head -n 1
}

urlencode() {
  # Percent-encode a string for application/x-www-form-urlencoded (space -> +).
  local s="$1"
  local out=""
  local i c hex
  for ((i=0; i<${#s}; i++)); do
    c="${s:i:1}"
    case "$c" in
      [a-zA-Z0-9.~_-]) out+="$c" ;;
      ' ') out+='+' ;;
      *) printf -v hex '%%%02X' "'$c"; out+="$hex" ;;
    esac
  done
  printf '%s' "$out"
}

write_form() {
  # Write a form body to a file without a trailing newline.
  local file="$1"; shift
  printf '%s' "$*" >"$file"
}

extract_first_match_id_from_html() {
  local file="$1"
  # match links: /tournaments/{id}/matches/{matchId}
  awk '
    match($0, /\/matches\/[0-9]+/) {
      s = substr($0, RSTART, RLENGTH)
      sub(/^\/matches\//, "", s)
      print s
      exit
    }
  ' "$file"
}

extract_tournament_id_by_label() {
  local file="$1"
  local label="$2"
  # On /tournaments (table), the name appears before the "Ouvrir" link in the same <tr>.
  # We search forward from the label until we see the first /tournaments/{id} href.
  awk -v label="$label" '
    index($0, label) {
      want = 1
      next
    }
    want == 1 && match($0, /\/tournaments\/[0-9]+/) {
      s = substr($0, RSTART, RLENGTH)
      sub(/^\/tournaments\//, "", s)
      print s
      exit
    }
  ' "$file"
}

need_cmd docker
need_cmd curl
need_cmd rg

info "Starting Docker stack"
if [[ "$RESET_DB" == "1" ]]; then
  info "RESET_DB=1: wiping compose volumes (docker compose down -v)"
  docker compose down -v --remove-orphans >/dev/null 2>&1 || true
fi
docker compose up -d --build >/dev/null

info "Running migrations"
docker compose exec -T php php bin/migrate.php >/dev/null

info "Seeding demo data"
docker compose exec -T php php bin/seed_demo.php >/dev/null

info "HTTP: home page"
HOME_HTML="$TMP_DIR/home.html"
code="$(http_get "$BASE_URL/" "$HOME_HTML")"
assert_code "$code" "200"
assert_contains "$HOME_HTML" "DuelDesk"

info "HTTP: tournaments index"
TOUR_HTML="$TMP_DIR/tournaments.html"
code="$(http_get "$BASE_URL/tournaments" "$TOUR_HTML")"
assert_code "$code" "200"
assert_contains "$TOUR_HTML" "Tournois"

info "Login as admin ($ADMIN_USER)"
LOGIN_HTML="$TMP_DIR/login.html"
code="$(http_get_auth "$BASE_URL/login" "$LOGIN_HTML")"
assert_code "$code" "200"
csrf="$(extract_csrf "$LOGIN_HTML")"
[[ -n "$csrf" ]] || fail "could not extract csrf from /login"

if [[ "$SKIP_AUTH_TESTS" == "1" ]]; then
  info "SKIP_AUTH_TESTS=1: skipping authenticated tests."
  info "All (public-only) tests passed."
  exit 0
fi

LOGIN_POST="$TMP_DIR/login.post"
write_form "$LOGIN_POST" \
  "csrf_token=$(urlencode "$csrf")&username=$(urlencode "$ADMIN_USER")&password=$(urlencode "$ADMIN_PASS")"

LOGIN_OUT="$TMP_DIR/login.out"
LOGIN_HDR="$TMP_DIR/login.hdr"
code="$(http_post_auth "$BASE_URL/login" "$LOGIN_POST" "$LOGIN_OUT" "$LOGIN_HDR")"
if [[ "$code" != "302" && "$code" != "303" ]]; then
  if [[ "$code" == "200" ]]; then
    if rg -n --fixed-strings "Identifiants invalides." "$LOGIN_OUT" >/dev/null 2>&1; then
      fail "admin login failed (bad credentials). Set ADMIN_USER/ADMIN_PASS, or run with RESET_DB=1 to start from a clean DB."
    fi
    fail "admin login failed (HTTP 200). Set ADMIN_USER/ADMIN_PASS, or inspect $LOGIN_OUT."
  fi
  fail "unexpected HTTP $code (wanted: 302 303)"
fi

info "HTTP: admin dashboard"
ADMIN_HTML="$TMP_DIR/admin.html"
code="$(http_get_auth "$BASE_URL/admin" "$ADMIN_HTML")"
assert_code "$code" "200"
assert_contains "$ADMIN_HTML" "Dashboard"

info "LAN: signup registers and auto-enrolls to all tournaments in the LAN (admin bypass)"
LAN_HTML="$TMP_DIR/lan.html"
code="$(http_get_auth "$BASE_URL/lan/demo-lan-solo" "$LAN_HTML")"
assert_code "$code" "200"
assert_contains "$LAN_HTML" "Inscription"
csrf="$(extract_csrf "$LAN_HTML")"
[[ -n "$csrf" ]] || fail "could not extract csrf from /lan/demo-lan-solo"

LAN_SIGNUP_POST="$TMP_DIR/lan_signup.post"
write_form "$LAN_SIGNUP_POST" "csrf_token=$(urlencode "$csrf")"
LAN_SIGNUP_OUT="$TMP_DIR/lan_signup.out"
LAN_SIGNUP_HDR="$TMP_DIR/lan_signup.hdr"
code="$(http_post_auth "$BASE_URL/lan/demo-lan-solo/signup" "$LAN_SIGNUP_POST" "$LAN_SIGNUP_OUT" "$LAN_SIGNUP_HDR")"
assert_code_any "$code" "302" "303"

info "LAN: tournaments in LAN are hidden from /tournaments"
LAN_TOUR_HTML="$TMP_DIR/lan_tour.html"
code="$(http_get_auth "$BASE_URL/tournaments" "$LAN_TOUR_HTML")"
assert_code "$code" "200"
if rg -n --fixed-strings "Demo LAN Solo A (inscriptions)" "$LAN_TOUR_HTML" >/dev/null 2>&1; then
  fail "LAN tournament leaked into /tournaments list"
fi

info "Check 2XKO crew tournament page contains crew battle section (lineup_duels)"
X2KO_HTML="$TMP_DIR/2xko.html"
code="$(http_get_auth "$BASE_URL/tournaments" "$X2KO_HTML")"
assert_code "$code" "200"
assert_contains "$X2KO_HTML" "2XKO"

# We don't know the tournament id reliably from HTML; use known seed name by searching /tournaments page for links.
# Fallback: try common seed ids (5th tournament in seed is usually id 5, but DB may vary). We'll locate by grep.
X2KO_TID="$(extract_tournament_id_by_label "$X2KO_HTML" "Demo 2XKO Crew")"
if [[ -z "$X2KO_TID" ]]; then
  # fallback: first /tournaments/{id} in page
  X2KO_TID="$(awk 'match($0, /\/tournaments\/[0-9]+/) { s=substr($0,RSTART,RLENGTH); sub(/^\/tournaments\//,"",s); print s; exit }' "$X2KO_HTML")"
fi
[[ -n "$X2KO_TID" ]] || fail "could not determine 2XKO tournament id from /tournaments"

X2KO_SHOW="$TMP_DIR/2xko_show.html"
code="$(http_get_auth "$BASE_URL/tournaments/$X2KO_TID" "$X2KO_SHOW")"
assert_code "$code" "200"
mid="$(extract_first_match_id_from_html "$X2KO_SHOW")"
[[ -n "$mid" ]] || fail "could not find a match id on 2XKO tournament page"

X2KO_MATCH="$TMP_DIR/2xko_match.html"
code="$(http_get_auth "$BASE_URL/tournaments/$X2KO_TID/matches/$mid" "$X2KO_MATCH")"
assert_code "$code" "200"
assert_contains "$X2KO_MATCH" "Crew battle"

info "Fall Guys multi-round: add rounds + finalize"
FG_LIST="$TMP_DIR/fg_list.html"
code="$(http_get_auth "$BASE_URL/tournaments" "$FG_LIST")"
assert_code "$code" "200"
assert_contains "$FG_LIST" "Fall Guys"

FG_TID="$(extract_tournament_id_by_label "$FG_LIST" "Demo Fall Guys Multi-round")"
if [[ -z "$FG_TID" ]]; then
  FG_TID="$(awk 'match($0, /\/tournaments\/[0-9]+/) { s=substr($0,RSTART,RLENGTH); sub(/^\/tournaments\//,"",s); print s; exit }' "$FG_LIST")"
fi
[[ -n "$FG_TID" ]] || fail "could not determine Fall Guys tournament id"

FG_SHOW="$TMP_DIR/fg_show.html"
code="$(http_get_auth "$BASE_URL/tournaments/$FG_TID" "$FG_SHOW")"
assert_code "$code" "200"
fg_mid="$(extract_first_match_id_from_html "$FG_SHOW")"
[[ -n "$fg_mid" ]] || fail "could not find a match id on Fall Guys tournament page"

FG_MATCH="$TMP_DIR/fg_match.html"
code="$(http_get_auth "$BASE_URL/tournaments/$FG_TID/matches/$fg_mid" "$FG_MATCH")"
assert_code "$code" "200"
assert_contains "$FG_MATCH" "Multi-round (points)"

csrf="$(extract_csrf "$FG_MATCH")"
[[ -n "$csrf" ]] || fail "could not extract csrf from Fall Guys match page"

ROUND1_POST="$TMP_DIR/round1.post"
write_form "$ROUND1_POST" \
  "csrf_token=$(urlencode "$csrf")&kind=regular&points1=10&points2=8&note=$(urlencode "manche 1")"
ROUND1_OUT="$TMP_DIR/round1.out"
ROUND1_HDR="$TMP_DIR/round1.hdr"
code="$(http_post_auth "$BASE_URL/tournaments/$FG_TID/matches/$fg_mid/rounds/add" "$ROUND1_POST" "$ROUND1_OUT" "$ROUND1_HDR")"
assert_code_any "$code" "302" "303"

FG_MATCH2="$TMP_DIR/fg_match2.html"
code="$(http_get_auth "$BASE_URL/tournaments/$FG_TID/matches/$fg_mid" "$FG_MATCH2")"
assert_code "$code" "200"
assert_contains "$FG_MATCH2" "10 - 8"

csrf="$(extract_csrf "$FG_MATCH2")"
ROUND2_POST="$TMP_DIR/round2.post"
write_form "$ROUND2_POST" \
  "csrf_token=$(urlencode "$csrf")&kind=regular&points1=5&points2=9&note=$(urlencode "manche 2")"
ROUND2_OUT="$TMP_DIR/round2.out"
ROUND2_HDR="$TMP_DIR/round2.hdr"
code="$(http_post_auth "$BASE_URL/tournaments/$FG_TID/matches/$fg_mid/rounds/add" "$ROUND2_POST" "$ROUND2_OUT" "$ROUND2_HDR")"
assert_code_any "$code" "302" "303"

FG_MATCH3="$TMP_DIR/fg_match3.html"
code="$(http_get_auth "$BASE_URL/tournaments/$FG_TID/matches/$fg_mid" "$FG_MATCH3")"
assert_code "$code" "200"
assert_contains "$FG_MATCH3" "15 - 17"

csrf="$(extract_csrf "$FG_MATCH3")"
FINAL_POST="$TMP_DIR/final.post"
write_form "$FINAL_POST" "csrf_token=$(urlencode "$csrf")"
FINAL_OUT="$TMP_DIR/final.out"
FINAL_HDR="$TMP_DIR/final.hdr"
code="$(http_post_auth "$BASE_URL/tournaments/$FG_TID/matches/$fg_mid/rounds/finalize" "$FINAL_POST" "$FINAL_OUT" "$FINAL_HDR")"
assert_code_any "$code" "302" "303"

FG_MATCH4="$TMP_DIR/fg_match4.html"
code="$(http_get_auth "$BASE_URL/tournaments/$FG_TID/matches/$fg_mid" "$FG_MATCH4")"
assert_code "$code" "200"
assert_contains "$FG_MATCH4" "confirmed"

info "All tests passed."
