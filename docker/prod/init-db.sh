#!/bin/sh
# Запускається як Fly release_command перед стартом нової версії:
# міграції — завжди; фікстури + демо-меню — лише в порожню базу.
# Довідник USDA у демо свідомо НЕ вантажимо (make usda — локально за бажанням).
set -eu

cd /var/www/app

echo "==> doctrine:migrations:migrate"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

members="$(psql "${DATABASE_URL%%\?*}" -tAc 'SELECT COUNT(*) FROM family_member')"

if [ "${members:-0}" = "0" ]; then
    echo "==> порожня база: fixtures + демо-меню"
    php bin/console doctrine:fixtures:load --no-interaction
    php bin/console app:seed-demo --no-interaction
else
    echo "==> база вже заповнена (${members} членів сім'ї) — сід пропущено"
fi

echo "==> готово"
