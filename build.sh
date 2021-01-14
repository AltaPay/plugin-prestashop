#!/usr/bin/env bash

if command -v dpkg-query -l zip
then
  git clone --branch 3.0.1 https://github.com/AltaPay/sdk-php.git
  cp -rf sdk-php/lib/* lib/altapay/altapay-php-sdk/lib/
  rm -rf sdk-php/
  composer install -a -o --no-dev
  find . -type d -exec cp index.php {} \;
  mkdir -p dist/altapay
  rsync -rv --exclude=build.sh --exclude=.gitignore --exclude=guide.md * dist/altapay
  cd ./dist
  zip altapay.zip -r altapay
  rm -r altapay
  cd ../lib/altapay/altapay-php-sdk/lib/
  find . \! -name 'helpers.php' -delete
else
  echo "Zip package is not currently installed"
fi