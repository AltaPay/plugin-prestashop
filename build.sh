#!/usr/bin/env bash

if command -v dpkg-query -l zip
then
  set -e

  if ! command -v zip
  then
    echo "Zip package is not currently installed"
    exit
  fi

  if ! command -v php7.0
  then
    echo "PHP 7.0 package is not currently installed"
    exit
  fi

  if ! command -v composer
  then
    echo "Composer package is not currently installed"
    exit
  fi

  php7.0 $(command -v composer) install --no-dev -o
  composer isolate
  composer dump -a
  find . -type d -exec cp index.php {} \;
  mkdir -p dist/altapay
  zip dist/altapay.zip -r * -x "dist/*" build.sh guide.md .gitignore phpunit.xml.dist phpstan.neon.dist phpstan.neon composer.json composer.lock @
else
  echo "Zip package is not currently installed"
fi
