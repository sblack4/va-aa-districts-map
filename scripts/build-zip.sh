#!/bin/bash
# Build a clean distribution ZIP of the plugin.
# Usage: ./scripts/build-zip.sh
# Output: va-aa-districts-map.zip in the repo root

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$SCRIPT_DIR/.."
ZIP_NAME="va-aa-districts-map.zip"

cd "$REPO_DIR"
rm -f "$ZIP_NAME"

zip -r "$ZIP_NAME" va-aa-districts-map/ \
  -x "*.DS_Store" \
  -x "*/__MACOSX/*"

echo ""
echo "Built: $REPO_DIR/$ZIP_NAME"
echo "Size: $(du -h "$ZIP_NAME" | cut -f1)"
echo ""
echo "Contents:"
unzip -l "$ZIP_NAME" | grep "va-aa-districts-map/"
