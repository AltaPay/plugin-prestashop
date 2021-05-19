#!/usr/bin/env bash

if command -v dpkg-query -l zip
then
  set -e

  if ! command -v zip
  then
    echo "Zip package is not currently installed"
    exit
  fi

  if ! command -v php5.6
  then
    echo "PHP 5.6 package is not currently installed"
    exit
  fi

  if ! command -v composer
  then
    echo "Composer package is not currently installed"
    exit
  fi

  php5.6 $(command -v composer) install --no-dev
  php5.6 $(command -v composer) isolate
  php5.6 $(command -v composer) dump -o
  find . -type d -exec cp index.php {} \;
  mkdir -p dist/altapay && rsync -av --exclude={'dist','docker','build.sh','guide.md','.gitignore','phpstan.neon','composer.json','composer.lock'} * dist/altapay
  cd dist/ && zip altapay.zip -r *
else
  echo "Zip package is not currently installed"
fi
