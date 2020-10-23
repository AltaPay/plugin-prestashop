#!/bin/bash
if command -v dpkg-query -l zip
then
  mkdir -p dist/altapay
  rsync -rv --exclude=build.sh --exclude=.gitignore --exclude=guide.md * dist/altapay
  cd ./dist
  zip altapay.zip -r altapay
  rm -r altapay
else
  echo "Zip package is not currently installed"
fi