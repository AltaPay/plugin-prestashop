#!/bin/bash

if command -v dpkg-query -l zip
then
  set -e

  if ! command -v zip
  then
    echo "Zip package is not currently installed"
    exit
  fi

  if ! command -v php7.2
  then
    echo "PHP 7.2 package is not currently installed"
    exit
  fi

  if ! command -v composer
  then
    echo "Composer package is not currently installed"
    exit
  fi

  php7.2 $(command -v composer) install --no-dev --no-interaction
  mkdir -p package && yes | php7.2 vendor/bin/php-scoper add-prefix
  rsync -a build/vendor/* vendor/ && rm -rf build/
  find . -type d -exec cp index.php {} \;
  mkdir -p dist/altapay && rsync -av --exclude={'build','dist','docker','Docs','build.sh','guide.md','.gitignore','phpstan.neon'} * dist/altapay
  cd dist/altapay/ && php7.2 $(command -v composer) dump-autoload --working-dir ./ --classmap-authoritative
  cd ../ && zip altapay.zip -r *
else
  echo "Zip package is not currently installed"
fi
