#!/bin/bash
# Set up a fresh local WordPress instance with the plugin activated.
# Prerequisites: Docker must be running.
# Usage: ./scripts/setup-local.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/.."

echo "Starting WordPress + MySQL..."
docker compose up -d

echo "Waiting for WordPress to initialize..."
sleep 10

echo "Installing WP-CLI..."
docker compose exec -T wordpress bash -c \
  "curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp"

echo "Installing WordPress..."
docker compose exec -T wordpress wp core install \
  --url="http://localhost:8888" \
  --title="VA AA Districts Map — Dev" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.com \
  --allow-root

echo "Activating plugin..."
docker compose exec -T wordpress wp plugin activate va-aa-districts-map --allow-root

echo "Creating test page with shortcode..."
docker compose exec -T wordpress wp post create \
  --post_title="Map" \
  --post_content='[va_aa_map]' \
  --post_status=publish \
  --post_type=page \
  --allow-root

echo ""
echo "========================================="
echo "  Local WordPress is ready!"
echo "========================================="
echo ""
echo "  Map page:     http://localhost:8888/?page_id=4"
echo "  WP Admin:     http://localhost:8888/wp-admin/"
echo "  Plugin admin:  http://localhost:8888/wp-admin/admin.php?page=vaaa-districts"
echo "  Login:         admin / admin"
echo ""
echo "  Stop:          docker compose down"
echo "  Stop + delete:  docker compose down -v"
echo ""
