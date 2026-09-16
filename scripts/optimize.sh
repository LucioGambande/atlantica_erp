#!/usr/bin/env bash
set -e

echo "==> Cacheando config, rutas y componentes de Filament"
./vendor/bin/sail artisan config:cache
./vendor/bin/sail artisan route:cache
./vendor/bin/sail artisan filament:optimize

echo "==> Optimización aplicada"
