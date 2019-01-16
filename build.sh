#!/bin/sh
# Default version is unspecified
VERSION="dev"
# Box binary location
BOX="https://github.com/humbug/box/releases/download/3.1.2/box.phar"


## get version number from the docker build
if [ $# -eq 1 ]; then
    VERSION=$1
fi;

if [ ! -f "box.phar" ]; then
    echo "Downloading ${BOX}"
    wget -O box.phar -q $BOX
fi

chmod +x box.phar

# .build.json provides box with application version
cat box.json | sed "s/\"dev\"/\"${VERSION}\"/" > .build.json

composer --no-progress install

./box.phar compile --config .build.json

# .build.json no longer needed
rm -f .build.json
