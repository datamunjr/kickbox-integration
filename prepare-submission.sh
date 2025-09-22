#!/bin/bash

# Script to prepare the plugin for WooCommerce submission
# This creates a clean copy for submission while preserving the original development files

echo "Preparing WooCommerce Kickbox Integration for submission..."

# Get the current directory name
CURRENT_DIR=$(basename "$PWD")
SUBMISSION_DIR="../wckb-submission"

# Remove existing submission directory if it exists
if [ -d "$SUBMISSION_DIR" ]; then
    echo "Removing existing submission directory..."
    rm -rf "$SUBMISSION_DIR"
fi

# Create submission directory
echo "Creating submission directory: $SUBMISSION_DIR"
mkdir -p "$SUBMISSION_DIR"

# Copy all files to submission directory
echo "Copying plugin files to submission directory..."
cp -r . "$SUBMISSION_DIR/"

# Change to submission directory
cd "$SUBMISSION_DIR"

echo "Cleaning up submission copy..."

# Remove development directories
echo "Removing development directories..."
rm -rf node_modules/
rm -rf src/

# Remove development files
echo "Removing development files..."
rm -f webpack.config.js
rm -f package.json
rm -f package-lock.json
rm -f install.sh
rm -f prepare-submission.sh

# Remove build artifacts
echo "Removing build artifacts..."
rm -f assets/css/*.map
rm -f assets/js/*.map
rm -f assets/js/*.LICENSE.txt

# Remove empty directories
echo "Cleaning up empty directories..."
rmdir wckb/ 2>/dev/null || true

# Go back to original directory
cd "../$CURRENT_DIR"

echo ""
echo "✅ Plugin prepared for submission!"
echo ""
echo "📁 Submission directory created: $SUBMISSION_DIR"
echo ""
echo "🗑️  Files removed from submission copy:"
echo "   - node_modules/ (development dependencies)"
echo "   - src/ (source files)"
echo "   - webpack.config.js (build configuration)"
echo "   - package.json (npm configuration)"
echo "   - package-lock.json (npm lock file)"
echo "   - install.sh (installation script)"
echo "   - Source maps and license files"
echo ""
echo "💾 Original development files preserved in: $CURRENT_DIR"
echo ""
echo "📦 Next steps:"
echo "   1. Navigate to: $SUBMISSION_DIR"
echo "   2. Create a ZIP file of the contents"
echo "   3. Submit to WooCommerce"
echo ""
echo "The plugin is now ready for WooCommerce submission!"