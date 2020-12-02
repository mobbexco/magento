#!/bin/sh

VER="1.2.0"

# Create 1.6 version
MAGE_V="1.6-1.9"

mkdir mobbex
cp -R app mobbex/app
cp Mobbex_Mobbex.xml mobbex/
zip mobbex.$VER.mage-$MAGE_V.zip -r mobbex
rm -Rf mobbex