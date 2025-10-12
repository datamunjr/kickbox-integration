#!/bin/bash

# Script to prepare the plugin for WooCommerce submission
# This creates a clean zip file for submission while preserving the original development files

echo "Preparing WooCommerce Kickbox Integration for submission..."

# Get the project root directory
PROJECT_ROOT="$(dirname "$(dirname "$(realpath "$0")")")"
CURRENT_DIR=$(basename "$PROJECT_ROOT")
TEMP_DIR="$PROJECT_ROOT/../kickbox-integration-temp"
ZIP_FILE="kickbox-integration.zip"

# Remove existing temp directory and zip file if they exist
if [ -d "$TEMP_DIR" ]; then
    echo "Removing existing temp directory..."
    rm -rf "$TEMP_DIR"
fi

if [ -f "$ZIP_FILE" ]; then
    echo "Removing existing zip file..."
    rm -f "$ZIP_FILE"
fi

# Create temp directory with plugin slug as parent directory
echo "Creating temp directory: $TEMP_DIR"
mkdir -p "$TEMP_DIR/kickbox-integration"

# Build assets before copying (run from project root)
echo "Building assets..."
cd "$PROJECT_ROOT"
npm ci
npm run build

# Copy all files to temp directory under the plugin slug folder
echo "Copying plugin files to temp directory..."
cp -r . "$TEMP_DIR/kickbox-integration/"

# Change to temp directory
cd "$TEMP_DIR/kickbox-integration"

echo "Cleaning up submission copy..."

# Remove development directories
echo "Removing development directories..."
rm -rf node_modules/
rm -rf src/
rm -rf vendor/
rm -rf tests/
rm -rf bin/
rm -rf scripts/
rm -rf .git/
rm -rf .idea/
rm -rf .vscode/
rm -rf .github/

# Remove React test directories
echo "Removing React test directories..."
find . -name "__tests__" -type d -exec rm -rf {} + 2>/dev/null || true

# Remove development files
echo "Removing development files..."
rm -f webpack.config.js
rm -f package.json
rm -f package-lock.json
rm -f composer.json
rm -f composer.lock
rm -f phpunit.xml
rm -f phpunit.xml.dist
rm -f .phpunit.result.cache
rm -f install.sh
rm -f prepare-submission.sh
rm -f .eslintrc.js
rm -f .prettierrc.json
rm -f .gitignore
rm -f babel.config.js
rm -f jest.config.js
rm -f README.md

# Remove build artifacts
echo "Removing build artifacts..."
rm -f assets/css/*.map
rm -f assets/js/*.map
rm -f assets/js/*.LICENSE.txt

# Remove system files
echo "Removing system files..."
find . -name ".DS_Store" -delete
find . -name "Thumbs.db" -delete
find . -name "*.tmp" -delete

# Remove empty directories
echo "Cleaning up empty directories..."
find . -type d -empty -delete 2>/dev/null || true

# Go back to parent directory to create zip
cd ".."

# Create zip file from temp directory (which contains kickbox-integration folder)
echo "Creating zip file: $PROJECT_ROOT/$ZIP_FILE"
cd "$TEMP_DIR"
zip -r "$PROJECT_ROOT/$ZIP_FILE" kickbox-integration -x "*.DS_Store" "*/.*"

# Verify zip contents
echo "Verifying zip file contents..."
echo "Files included in zip:"
unzip -l "$PROJECT_ROOT/$ZIP_FILE" | head -20
echo "Total files in zip:"
unzip -l "$PROJECT_ROOT/$ZIP_FILE" | tail -1

# Remove temp directory
echo "Cleaning up temp directory..."
rm -rf "$TEMP_DIR"

# Go back to original directory (scripts folder)
cd "$(dirname "$(realpath "$0")")"

echo ""
echo "✅ Plugin prepared for submission!"
echo ""
echo "📦 Zip file created: $ZIP_FILE"
echo ""
echo "🗑️  Files removed from submission copy:"
echo "   - node_modules/ (npm development dependencies)"
echo "   - vendor/ (Composer dependencies)"
echo "   - tests/ (test files and directories)"
echo "   - __tests__/ (React test directories)"
echo "   - bin/ (development scripts)"
echo "   - scripts/ (development scripts)"
echo "   - src/ (source files)"
echo "   - .git/ (git repository)"
echo "   - .idea/ (IntelliJ IDEA settings)"
echo "   - .vscode/ (VS Code settings)"
echo "   - webpack.config.js (build configuration)"
echo "   - package.json (npm configuration)"
echo "   - package-lock.json (npm lock file)"
echo "   - composer.json (Composer configuration)"
echo "   - composer.lock (Composer lock file)"
echo "   - phpunit.xml (PHPUnit configuration)"
echo "   - phpunit.xml.dist (PHPUnit distribution config)"
echo "   - .phpunit.result.cache (PHPUnit cache)"
echo "   - babel.config.js (Babel configuration)"
echo "   - jest.config.js (Jest test configuration)"
echo "   - .eslintrc.js (linting configuration)"
echo "   - .prettierrc.json (formatting configuration)"
echo "   - .gitignore (git ignore rules)"
echo "   - install.sh (installation script)"
echo "   - prepare-submission.sh (this script)"
echo "   - Source maps and license files"
echo "   - .DS_Store (macOS system files)"
echo ""
echo "💾 Original development files preserved in: $CURRENT_DIR"
echo ""
echo "📦 Next steps:"
echo "   1. The zip file is ready: $PROJECT_ROOT/$ZIP_FILE"
echo "   2. Submit the zip file to WooCommerce"
echo ""
echo "The plugin is now ready for WooCommerce submission!"