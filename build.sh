#!/bin/bash

if command -v dpkg-query -l zip
then
  set -e

  if ! command -v zip
  then
    echo "Zip package is not currently installed"
    exit
  fi

  if ! command -v php7.4
  then
    echo "PHP 7.4 package is not currently installed"
    exit
  fi

  if ! command -v composer
  then
    echo "Composer package is not currently installed"
    exit
  fi

  php7.4 $(command -v composer) install --no-dev --no-interaction
  mkdir -p package && php7.4 vendor/bin/php-scoper add-prefix --output-dir ./package
  rm -rf vendor/* && mv package/vendor/* vendor/
  find . -type d -exec cp index.php {} \;
  mkdir -p dist/altapay && rsync -av --exclude={'package','dist','docker','Docs','build.sh','guide.md','.gitignore','phpstan.neon'} * dist/altapay
  cd dist/altapay/ && php7.4 $(command -v composer) dump-autoload --working-dir ./ --classmap-authoritative
  cd ../ && zip altapay.zip -r *
else
  echo "Zip package is not currently installed"
fi
