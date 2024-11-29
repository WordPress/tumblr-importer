#!/bin/bash

set -e  # Exit immediately if a command exits with a non-zero status.

# Variables
RELEASE_TAG=$1
PHP_VERSION=8.3  # Adjust to your preferred PHP version.
ZIP_FILE="tumblr-importer.zip"

# Check if a release tag was provided
if [ -z "$RELEASE_TAG" ]; then
  echo "Error: No release tag provided."
  echo "Usage: $0 <release-tag>"
  exit 1
fi

# Step 1: Checkout the repository
echo "Checking out the repository..."
git checkout .

# Step 2: Set up the proper PHP version
echo "Setting up PHP version $PHP_VERSION..."
# Assumes you have a way to manage PHP versions (e.g., `phpenv`, `update-alternatives`, or Docker).

# Step 3: Install dependencies
echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Step 4: Create the zip file
echo "Creating zip file $ZIP_FILE..."
zip -r $ZIP_FILE . -x ".*" -x "tests/*" -x "bin/*"

# Step 5: Upload the zip file as a release asset
echo "Uploading $ZIP_FILE as a release asset..."
gh release upload "$RELEASE_TAG" "$ZIP_FILE"

echo "Release process completed successfully!"
