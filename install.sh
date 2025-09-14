#!/bin/bash

# WooCommerce Kickbox Integration - Installation Script

echo "🚀 Setting up WooCommerce Kickbox Integration..."

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js first."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install npm first."
    exit 1
fi

echo "📦 Installing dependencies..."
npm install

if [ $? -ne 0 ]; then
    echo "❌ Failed to install dependencies."
    exit 1
fi

echo "🔨 Building assets..."
npm run build

if [ $? -ne 0 ]; then
    echo "❌ Failed to build assets."
    exit 1
fi

echo "✅ Installation complete!"
echo ""
echo "Next steps:"
echo "1. Upload this plugin to your WordPress site"
echo "2. Activate the plugin"
echo "3. Go to WooCommerce > Kickbox Integration"
echo "4. Enter your Kickbox API key"
echo "5. Configure your verification settings"
echo ""
echo "For development, run 'npm run dev' to watch for changes."
