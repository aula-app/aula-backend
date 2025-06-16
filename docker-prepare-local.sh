#!/bin/bash

set -e

echo "[*] Preparing environment..."

echo "[*] Copying example config files... (generated files are NOT PRODUCTION READY)"
cp ./config/base_config.php-example ./config/base_config.php
cp ./config/db_config.ini-example ./config/db_config.ini

if [ -f ./docker-compose.override.yml ]; then
  echo "[*] You already have docker-compose.override.yml."
  REPLY=$(read -p "-> Do you want to regenerate it? [y/N] " -r)
  if [[ $REPLY =~ ^[Yy]$ ]]; then
    rm -fv ./docker-compose.override.yml
  fi
fi

if [ ! -f ./docker-compose.override.yml ]; then
  echo "[*] Adapting docker-compose.override.yml to your local environment..."
  cp ./docker-compose.override.yml.template ./docker-compose.override.yml
  {
    echo '# AUTO-GENERATED file, using prepare-local.sh.'
    echo '# Changes to this file will be lost when using "make" command.'
    cat docker-compose.override.yml
  } > docker-compose.override.yml.bak && mv docker-compose.override.yml.bak docker-compose.override.yml

  sed -i.bak "s/UID/$(id -u)/g" docker-compose.override.yml
  sed -i.bak "s/GID/$(id -g)/g" docker-compose.override.yml

  ENCRYPTION_KEY_1=$(LC_ALL=C tr -dc 'A-Za-z0-9#%()+,-.:;<=>@^_~' </dev/urandom | head -c 64; echo)
  ENCRYPTION_KEY_2=$(LC_ALL=C tr -dc 'A-Za-z0-9#%()+,-.:;<=>@^_~' </dev/urandom | head -c 64; echo)
  sed -i.bak "s/JWT_KEY: CHANGE_ME/JWT_KEY: \"$ENCRYPTION_KEY_1\"/g" docker-compose.override.yml
  sed -i.bak "s/SUPERKEY: CHANGE_ME/SUPERKEY: \"$ENCRYPTION_KEY_2\"/g" docker-compose.override.yml
  rm -vf ./docker-compose.override.yml.bak
else
  echo "[*] Reusing existing docker-compose.override.yml... Delete it to regenerate it (you will lose the random keys)."
fi

echo "[✓] Environment prepared."
