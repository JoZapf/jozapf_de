#!/bin/bash
set -e

# Copy favicons
mkdir -p assets-deploy/favicon
cp -r public/assets/favicon/* assets-deploy/favicon/ 2>/dev/null || true

# Copy SVGs  
mkdir -p assets-deploy/svg
cp -r public/assets/svg/* assets-deploy/svg/ 2>/dev/null || true

echo "✓ Assets copied to assets-deploy/"
ls -R assets-deploy/
