#!/bin/bash

set -e

echo "[*] Preparing local environment configuration..."

echo "[*] Copying example config files... (generated files are NOT PRODUCTION READY)"

[ -f ./config/base_config.php ] && \
  echo "  [*] You already have ./config/base_config.php. Delete it to regenerate it." || \
  cp -n ./config/base_config.php-example ./config/base_config.php

[ -f ./config/oauth-public.key ] && \
  echo "  [*] You already have ./config/oauth-public.key. Delete it to regenerate it." || \
  sudo cp -n ../storage/oauth-public.key ./config/oauth-public.key

echo "[✓] Local environment prepared."
