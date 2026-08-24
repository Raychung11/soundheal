#!/usr/bin/env bash
#
# deploy.sh — pull the latest code, fix permissions, list any pending
# database migrations. Runs on the Hostinger server, invoked over SSH:
#
#   cd ~/domains/jaemiesoundbath.com/public_html
#   ./deploy.sh                # fetch + reset + chmod + list migrations
#   ./deploy.sh --migrate      # same, then also apply pending migrations
#   ./deploy.sh --baseline     # record every existing migration file as
#                              #   already applied — run ONCE on a live
#                              #   DB that already has the historical
#                              #   schema, otherwise --migrate would
#                              #   try to re-run every historical file.
#
# The script is deliberately loud on migrations: without --migrate it
# only PRINTS which files are new, so schema changes never happen
# behind your back. Pass --migrate when you're ready to run them.
#
# Safe to re-run any time — every step is idempotent.

set -euo pipefail

# ---- pretty output -------------------------------------------------
c_reset=$'\033[0m'
c_gold=$'\033[38;5;179m'
c_dim=$'\033[38;5;245m'
c_ok=$'\033[38;5;114m'
c_warn=$'\033[38;5;209m'
step() { printf '\n%s→ %s%s\n' "$c_gold" "$1" "$c_reset"; }
ok()   { printf '  %s✓%s %s\n' "$c_ok"   "$c_reset" "$1"; }
warn() { printf '  %s!%s %s\n' "$c_warn" "$c_reset" "$1"; }
info() { printf '  %s%s%s\n'   "$c_dim"  "$1" "$c_reset"; }

# ---- args ----------------------------------------------------------
RUN_MIGRATIONS=false
BASELINE=false
for arg in "$@"; do
    case "$arg" in
        --migrate)  RUN_MIGRATIONS=true ;;
        --baseline) BASELINE=true ;;
        -h|--help)
            sed -n '2,20p' "$0"
            exit 0 ;;
        *) warn "Unknown flag: $arg (ignored)"; ;;
    esac
done

# Always run from the script's own directory so it doesn't matter
# where the caller was standing.
cd "$(dirname "$0")"

# ---- 1. pull ------------------------------------------------------
step "Fetching latest from origin"
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
info "current branch: $BRANCH"

git fetch --prune origin
before_sha="$(git rev-parse HEAD)"
git reset --hard "origin/$BRANCH"
after_sha="$(git rev-parse HEAD)"

if [ "$before_sha" = "$after_sha" ]; then
    ok "already at $after_sha — nothing new to deploy"
else
    ok "moved $before_sha → $after_sha"
    info "changelog:"
    git --no-pager log --oneline "$before_sha..$after_sha" | sed 's/^/      /'

    # Self-modifying-script guard: if this deploy pulled in a new copy
    # of deploy.sh itself, bash is still reading the OLD content from
    # its current file-offset (it tracks byte position, not lines).
    # Re-exec so the rest of the run uses the new file cleanly.
    if git --no-pager diff --name-only "$before_sha" "$after_sha" | grep -Fxq "deploy.sh"; then
        info "deploy.sh itself was updated — re-executing"
        exec "$0" "$@"
    fi
fi

# ---- 2. permissions ------------------------------------------------
step "Fixing writable-directory permissions"
for dir in logs uploads qr; do
    if [ -d "$dir" ]; then
        chmod -R u+rwX,g+rwX "$dir" 2>/dev/null || true
        ok "$dir/"
    fi
done

# .env must never be world-readable.
if [ -f .env ]; then
    chmod 600 .env
    ok ".env locked to 600"
fi

# ---- 3. migrations -------------------------------------------------
step "Checking for new database migrations"

# Read DB creds out of .env without sourcing (safer — no code exec).
get_env() {
    local key="$1"
    grep -E "^${key}=" .env 2>/dev/null | head -n1 | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/'
}
DB_HOST="$(get_env DB_HOST)"
DB_NAME="$(get_env DB_NAME)"
DB_USER="$(get_env DB_USER)"
DB_PASS="$(get_env DB_PASS)"

MIG_DIR="database/migrations"
if [ ! -d "$MIG_DIR" ]; then
    warn "no $MIG_DIR/ directory — skipping"
else
    # Track applied migrations in a tiny table on the DB itself.
    # First run creates the table.
    mysql_cmd() {
        MYSQL_PWD="$DB_PASS" mysql \
            -h "${DB_HOST:-localhost}" \
            -u "$DB_USER" "$DB_NAME" "$@"
    }
    mysql_cmd -e "CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;" 2>/dev/null

    applied="$(mysql_cmd -N -e "SELECT filename FROM schema_migrations" 2>/dev/null || true)"

    # Build the pending list with a plain glob + `printf | grep` — no
    # process substitution / here-strings, since Hostinger's Bash does
    # not always expose /dev/fd/N and both idioms silently break there.
    pending=()
    shopt -s nullglob
    for file in "$MIG_DIR"/*.sql; do
        base="$(basename "$file")"
        if ! printf '%s\n' "$applied" | grep -Fxq "$base"; then
            pending+=("$file")
        fi
    done
    shopt -u nullglob

    # --baseline: record every migration file as "applied" without
    # actually running any of them. Use this once, right after this
    # deploy.sh lands on an EXISTING database that already carries
    # the historical schema — otherwise --migrate would try to re-run
    # every historical file.
    if [ "$BASELINE" = true ]; then
        step "Recording all existing migrations as applied (--baseline)"
        for f in "${pending[@]}"; do
            base="$(basename "$f")"
            mysql_cmd -e "INSERT IGNORE INTO schema_migrations (filename) VALUES ('$base')"
            ok "  baselined $base"
        done
        info ""
        info "Baseline complete. Future ./deploy.sh runs will only apply new files."
        step "Deploy finished"
        ok "site is at $after_sha on branch $BRANCH"
        exit 0
    fi

    if [ "${#pending[@]}" -eq 0 ]; then
        ok "no pending migrations"
    else
        warn "${#pending[@]} pending migration(s):"
        for f in "${pending[@]}"; do info "  $(basename "$f")"; done

        if [ "$RUN_MIGRATIONS" = true ]; then
            step "Applying migrations (--migrate was passed)"
            for f in "${pending[@]}"; do
                base="$(basename "$f")"
                info "  applying $base …"
                if mysql_cmd < "$f"; then
                    mysql_cmd -e "INSERT IGNORE INTO schema_migrations (filename) VALUES ('$base')"
                    ok "  $base applied"
                else
                    warn "  $base FAILED — halting"
                    exit 1
                fi
            done
        else
            info ""
            info "First time here? If this DB already carries the historical"
            info "schema, run:  ./deploy.sh --baseline"
            info "…to mark every existing migration as already applied."
            info ""
            info "Otherwise apply them all with:  ./deploy.sh --migrate"
        fi
    fi
fi

# ---- 4. done -------------------------------------------------------
step "Deploy finished"
ok "site is at $after_sha on branch $BRANCH"
