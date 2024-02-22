#!/bin/sh

VER="3.1.0"
MAGE_V="1.6-1.9"

mkdir mobbex
cp -R app mobbex/app
cp -R skin mobbex/skin

composer install --no-dev -d ./mobbex/app/code/local/Mobbex/Mobbex

if type 7z > /dev/null; then
    7z a -tzip "mobbex.$VER.mage-$MAGE_V.zip" mobbex
elif type zip > /dev/null; then
    zip mobbex.$VER.mage-$MAGE_V.zip -r mobbex
fi

rm -Rf mobbex