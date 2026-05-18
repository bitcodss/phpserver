#!/usr/bin/env bash
# scripts/new-site.sh — bootstrap a new site from sites/_template/
#
# Use at first deploy of a fresh phpserver host (the admin host has to exist
# before the admin UI can add subsequent sites — chicken-and-egg). For
# subsequent sites on a running deployment, use the admin UI's "+ Add New
# Site" button instead.
#
# The script:
#   1. Copies sites/_template/ → sites/<domain>/ with placeholder substitution
#   2. Generates docker/nginx/conf.d/<domain>.conf from the nginx template
#   3. Prints the docker/.env entries the operator must add
#
# The script does NOT:
#   - Touch docker/.env (those are secrets — operator does this; the script
#     just prints what to set)
#   - Create the database or DB user (admin Database tab handles after the
#     stack is up)
#   - Run docker compose (operator's call)

set -euo pipefail

DOMAIN=""
LABEL=""
DESCRIPTION=""
DB_NAME=""
DB_USER=""
PMA_SUBDOMAIN=""
SHORT_NAME=""
FORCE=0

usage() {
  cat <<'USAGE'
Usage: scripts/new-site.sh --domain <fqdn> [options]

Required:
  --domain <fqdn>          Public hostname (e.g. manage.example.com)

Optional:
  --label "<text>"         Short human name (default: domain)
  --description "<text>"   Long description (default: "PHP site for <domain>")
  --db-name <name>         DB name (default: domain slug, max 32 chars)
  --db-user <user>         DB user (default: <db-name-prefix>_u, max 16 chars)
  --pma-subdomain <fqdn>   phpMyAdmin URL host (default: pma.<domain>)
  --short-name <text>      Big-letter brand on landing page (default: upper of domain prefix)
  --force                  Overwrite an existing site folder / vhost
  --help                   Show this message
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --label) LABEL="${2:-}"; shift 2 ;;
    --description) DESCRIPTION="${2:-}"; shift 2 ;;
    --db-name) DB_NAME="${2:-}"; shift 2 ;;
    --db-user) DB_USER="${2:-}"; shift 2 ;;
    --pma-subdomain) PMA_SUBDOMAIN="${2:-}"; shift 2 ;;
    --short-name) SHORT_NAME="${2:-}"; shift 2 ;;
    --force) FORCE=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

if [[ -z "$DOMAIN" ]]; then
  echo "Error: --domain is required" >&2
  usage >&2
  exit 2
fi

# Same regex add_site.php validates against
if ! [[ "$DOMAIN" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$ ]]; then
  echo "Error: invalid domain format: $DOMAIN" >&2
  exit 2
fi

# Derive defaults
[[ -z "$LABEL"          ]] && LABEL="$DOMAIN"
[[ -z "$DESCRIPTION"    ]] && DESCRIPTION="PHP site for $DOMAIN"
if [[ -z "$DB_NAME" ]]; then
  DB_NAME="$(echo "$DOMAIN" | sed -E 's/[^a-z0-9]+/_/g' | cut -c1-32)"
fi
if [[ -z "$DB_USER" ]]; then
  DB_USER="$(echo "$DB_NAME" | cut -c1-14)_u"
fi
[[ -z "$PMA_SUBDOMAIN"  ]] && PMA_SUBDOMAIN="pma.$DOMAIN"
if [[ -z "$SHORT_NAME" ]]; then
  SHORT_NAME="$(echo "$DOMAIN" | cut -d. -f1 | tr '[:lower:]-' '[:upper:]_')"
fi

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEMPLATE_DIR="$REPO_ROOT/sites/_template"
DST_DIR="$REPO_ROOT/sites/$DOMAIN"
NGINX_TEMPLATE="$REPO_ROOT/docker/nginx/conf.d/_template.conf.example"
NGINX_DST="$REPO_ROOT/docker/nginx/conf.d/$DOMAIN.conf"

if [[ ! -d "$TEMPLATE_DIR" ]]; then
  echo "Error: template not found at $TEMPLATE_DIR" >&2
  exit 1
fi
if [[ ! -f "$NGINX_TEMPLATE" ]]; then
  echo "Error: nginx template not found at $NGINX_TEMPLATE" >&2
  exit 1
fi

if [[ -e "$DST_DIR" && $FORCE -eq 0 ]]; then
  echo "Error: $DST_DIR already exists (use --force to overwrite)" >&2
  exit 1
fi
if [[ -e "$NGINX_DST" && $FORCE -eq 0 ]]; then
  echo "Error: $NGINX_DST already exists (use --force to overwrite)" >&2
  exit 1
fi

CREATED="$(date -u +'%Y-%m-%d %H:%M:%S UTC')"

# 1. Copy template tree
[[ -e "$DST_DIR" ]] && rm -rf "$DST_DIR"
mkdir -p "$DST_DIR"
cp -r "$TEMPLATE_DIR/." "$DST_DIR/"
mv "$DST_DIR/site.json.template" "$DST_DIR/site.json"

# 2. Substitute placeholders. Values are passed via env so they are not
#    interpolated by perl — handles characters like &, /, |, " safely.
substitute() {
  local file="$1"
  DOMAIN="$DOMAIN" \
  LABEL="$LABEL" \
  DESCRIPTION="$DESCRIPTION" \
  DB_NAME="$DB_NAME" \
  DB_USER="$DB_USER" \
  SHORT_NAME="$SHORT_NAME" \
  CREATED="$CREATED" \
    perl -i -pe '
      s|\{\{DOMAIN\}\}|$ENV{DOMAIN}|g;
      s|\{\{LABEL\}\}|$ENV{LABEL}|g;
      s|\{\{DESCRIPTION\}\}|$ENV{DESCRIPTION}|g;
      s|\{\{DB_NAME\}\}|$ENV{DB_NAME}|g;
      s|\{\{DB_USER\}\}|$ENV{DB_USER}|g;
      s|\{\{SHORT_NAME\}\}|$ENV{SHORT_NAME}|g;
      s|\{\{CREATED\}\}|$ENV{CREATED}|g;
    ' "$file"
}
substitute "$DST_DIR/site.json"
substitute "$DST_DIR/public/index.php"

# 3. Generate the nginx vhost
cp "$NGINX_TEMPLATE" "$NGINX_DST"
substitute "$NGINX_DST"

# 4. Sanity grep — nothing should look like a placeholder anymore
if grep -rln '{{[A-Z_]\+}}' "$DST_DIR" "$NGINX_DST" >/dev/null 2>&1; then
  echo "Warning: leftover {{PLACEHOLDER}} tokens found:" >&2
  grep -rn '{{[A-Z_]\+}}' "$DST_DIR" "$NGINX_DST" >&2 || true
fi

# 5. Operator instructions
cat <<NEXT_STEPS

----------------------------------------------------------------------
✓ Site folder      : sites/$DOMAIN/
✓ Nginx vhost      : docker/nginx/conf.d/$DOMAIN.conf

Next steps (host-side, not in the repo):

1. Edit docker/.env on this host. Set or update:

     SITE_PUBLIC_IP=<this host's public IP>
     PMA_PUBLIC_URL=https://$PMA_SUBDOMAIN
     NGINX_BIND=127.0.0.1     # cloud / behind host-level Caddy
     PMA_BIND=127.0.0.1       # cloud / behind host-level Caddy

   (Home deployment behind cloudflared: keep NGINX_BIND and PMA_BIND
    at 0.0.0.0 — they default to that.)

   Also ensure MYSQL_ROOT_PASSWORD, ADMIN_PASS_HASH, SFTP_PASSWORD are
   set (see docker/.env.example).

2. Bring the stack up:

     cd docker && docker compose up -d

3. Add a Caddy vhost for $DOMAIN (and $PMA_SUBDOMAIN if separate) that
   reverse-proxies to 127.0.0.1:9080 and 127.0.0.1:9081 respectively.

4. Visit https://$DOMAIN/admin/ and log in with ADMIN_USER +
   the password whose bcrypt hash is in ADMIN_PASS_HASH.

5. Inside admin > Database, create database '$DB_NAME' and user '$DB_USER'
   (or use phpMyAdmin at https://$PMA_SUBDOMAIN/).
----------------------------------------------------------------------
NEXT_STEPS
