#!/bin/bash
# Yantra Voice Studio — One-time setup script
# Run this from Terminal.app (NOT from Antigravity)

echo "🎙️ Setting up Yantra Voice Studio..."

# 1. Copy the project into htdocs (where Apache serves from)
echo "→ Copying project to htdocs..."
cp -R /Applications/XAMPP/Yantravoice /Applications/XAMPP/xamppfiles/htdocs/Yantravoice

# 2. Create the storage symlink inside the new location
echo "→ Creating storage symlink..."
cd /Applications/XAMPP/xamppfiles/htdocs/Yantravoice
rm -f public/storage 2>/dev/null
ln -sf /Applications/XAMPP/xamppfiles/htdocs/Yantravoice/storage/app/public public/storage

# 3. Ensure audio directory exists
mkdir -p storage/app/public/audio

# 4. Set permissions
chmod -R 775 storage bootstrap/cache

echo ""
echo "✅ Done! Visit: http://localhost/Yantravoice/public/"
echo ""
echo "If you see a blank page, make sure Apache is running in XAMPP Manager."
