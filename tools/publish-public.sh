#!/usr/bin/env bash
# Run as the deployment user after a reviewed fast-forward pull.
set -euo pipefail
app=$(cd -- "$(dirname -- "$0")/.." && pwd -P)
domain=$(dirname -- "$(dirname -- "$app")")
public=$(realpath -e -- "${1:?Pass the verified domain public_html path}")
if [[ "$app" != "$domain/private/app" || "$public" != "$domain/public_html" || ! -d "$app/.git" ]]; then
    echo 'Refusing an unexpected source/public path' >&2
    exit 1
fi
for entry in index.php api.php public.php privacy.php mcp.php; do
    printf "<?php\nrequire dirname(__DIR__) . '/private/app/%s';\n" "$entry" > "$public/.$entry.deploy"
    chmod 0644 "$public/.$entry.deploy"
    mv -- "$public/.$entry.deploy" "$public/$entry"
done
install -m 0644 "$app/favicon.svg" "$public/favicon.svg"
install -m 0644 "$app/favicon.ico" "$public/favicon.ico"
install -m 0644 "$app/.htaccess" "$public/.htaccess"
mkdir -p -- "$public/assets"
rsync -a --chmod=D755,F644 --include='*/' --include='*.js' --include='*.css' \
    --include='*.jpg' --include='*.jpeg' --include='*.png' --include='*.svg' \
    --include='*.webp' --exclude='*' "$app/assets/" "$public/assets/"
echo 'Published reviewed entry points and runtime assets; private code/data were not exposed.'
