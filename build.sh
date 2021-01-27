#!/usr/bin/env bash

if command -v dpkg-query -l zip
then
  composer install -a -o --no-dev
  composer isolate
  find . -type d -exec cp index.php {} \;
  mkdir -p dist/altapay
  rsync -rv --exclude=build.sh --exclude=.gitignore --exclude=guide.md * dist/altapay
  cd ./dist
  zip altapay.zip -r altapay
  rm -r altapay ../vendor
else
  echo "Zip package is not currently installed"
fi
