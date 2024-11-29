#!/bin/bash

set -e  # Exit immediately if a command exits with a non-zero status.

# Variables
ZIP_FILE="tumblr-importer.zip"

# Step 1: Install dependencies
echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Step 2: Create the zip file
echo "Creating zip file $ZIP_FILE..."
zip -r $ZIP_FILE . -x ".*" -x "scripts/*"

echo "Release process completed successfully!"
