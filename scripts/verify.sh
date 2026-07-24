#!/usr/bin/env bash
set -e

echo "==> Pint (formato)"
./vendor/bin/sail php ./vendor/bin/pint --test

echo "==> Tests"
./vendor/bin/sail artisan test

echo "==> Build de assets"
./vendor/bin/sail npm run build

echo "==> Gate verde"
