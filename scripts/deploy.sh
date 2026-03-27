#!/bin/bash
# Deploy the plugin to a WP Engine site via SSH/rsync.
#
# Usage:
#   ./scripts/deploy.sh staging    # deploy to aavirginiastag
#   ./scripts/deploy.sh prod       # deploy to aavirginia (requires confirmation)
#   ./scripts/deploy.sh staging --reseed  # deploy and reset data to defaults

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR/../va-aa-districts-map"
ENV="${1:-staging}"
RESEED="${2:-}"

case "$ENV" in
  staging|stag)
    SSH_HOST="aastag"
    WP_PATH="~/sites/aavirginiastag"
    SITE_URL="https://aavirginiastag.wpengine.com"
    ;;
  prod|production)
    SSH_HOST="aaprod"
    WP_PATH="~/sites/aavirginia"
    SITE_URL="https://aavirginia.org"
    echo ""
    echo "  ⚠  You are deploying to PRODUCTION: $SITE_URL"
    echo ""
    read -p "  Type 'yes' to continue: " confirm
    if [ "$confirm" != "yes" ]; then
      echo "Aborted."
      exit 1
    fi
    ;;
  *)
    echo "Usage: $0 [staging|prod] [--reseed]"
    exit 1
    ;;
esac

PLUGIN_PATH="$WP_PATH/wp-content/plugins/va-aa-districts-map"
VERSION=$(grep "VAAA_VERSION" "$PLUGIN_DIR/va-aa-districts-map.php" | head -1 | grep -o "'[^']*'" | tr -d "'")

echo ""
echo "Deploying v$VERSION to $ENV ($SITE_URL)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo "→ Uploading plugin files..."
rsync -avz --delete -e ssh \
  --exclude='.DS_Store' \
  "$PLUGIN_DIR/" "$SSH_HOST:$PLUGIN_PATH/"

if [ "$RESEED" = "--reseed" ]; then
  echo "→ Reseeding data from defaults..."
  ssh "$SSH_HOST" "cd $WP_PATH && wp option delete vaaa_districts vaaa_fips_map vaaa_shared_counties 2>/dev/null; wp plugin deactivate va-aa-districts-map; wp plugin activate va-aa-districts-map"
fi

echo "→ Verifying..."
ssh "$SSH_HOST" "cd $WP_PATH && wp plugin get va-aa-districts-map --fields=name,status,version"

echo ""
echo "Done! Check: $SITE_URL/member-services/virginia-area-districts/"
echo ""
