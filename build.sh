#!/usr/bin/env bash

if command -v dpkg-query -l zip
then
  git clone https://github.com/AltaPay/sdk-php.git
  cp -rf sdk-php/lib/* lib/
  rm -rf sdk-php/
  find . -type d -exec cp index.php {} \;
  mkdir -p dist/altapay
  rsync -rv --exclude=build.sh --exclude=.gitignore --exclude=guide.md * dist/altapay
  cd ./dist
  zip altapay.zip -r altapay
  rm -r altapay
  cd ../lib/
  find . \! -name 'helpers.php' -delete
else
  echo "Zip package is not currently installed"
fi