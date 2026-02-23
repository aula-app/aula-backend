#!/bin/bash

set -e

echo "[*] Preparing local environment configuration..."

echo "[*] Copying example config files... (generated files are NOT PRODUCTION READY)"
[ -f ./config/base_config.php ] && \
  echo "  [*] You already have ./config/base_config.php. Delete it to regenerate it." || \
  cp -n ./config/base_config.php-example ./config/base_config.php
[ -f ./config/instances_config.php ] && \
  echo "  [*] You already have ./config/instances_config.php. Delete it to regenerate it." || \
  cp -n ./config/instances_config.php-example ./config/instances_config.php

echo "[✓] Local environment prepared."
