#!/bin/bash
set -euo pipefail

# Copy all repo files into a dedicated test/secondbrain-copy folder, excluding the test folder to avoid recursion.
ROOT_DIR=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
DEST_DIR="$ROOT_DIR/test/secondbrain-copy-$(date +%Y%m%d-%H%M%S)"

echo "Copying repository contents to: $DEST_DIR"
mkdir -p "$DEST_DIR"
rsync -a --exclude='test/**' "$ROOT_DIR/" "$DEST_DIR/"
echo "Done. You can inspect the copied content here."
