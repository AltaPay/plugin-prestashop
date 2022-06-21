#!/bin/bash

if command -v dpkg-query -l zip
then
  set -e

  if ! command -v zip
  then
    echo "Zip package is not currently installed"
    exit
  fi

  if ! command -v php7.1
  then
    echo "PHP 7.1 package is not currently installed"
    exit
  fi

  if ! command -v composer
  then
    echo "Composer package is not currently installed"
    exit
  fi

  php7.1 $(command -v composer) install --no-dev --no-interaction
  php7.1 $(command -v composer) isolate --no-interaction
  php7.1 $(command -v composer) dump -o --no-interaction
  find . -type d -exec cp index.php {} \;
  mkdir -p dist/altapay && rsync -av --exclude={'dist','docker','Docs','build.sh','guide.md','.gitignore','phpstan.neon','composer.json','composer.lock'} * dist/altapay
  cd dist/ && zip altapay.zip -r *
else
  echo "Zip package is not currently installed"
fi
