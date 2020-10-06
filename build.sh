#!/bin/bash
if command -v dpkg-query -l zip
then
  mkdir dist
  zip dist/altapay.zip -r * -x build.sh .gitignore guide.md @
else
  echo "Zip package is not currently installed"
fi