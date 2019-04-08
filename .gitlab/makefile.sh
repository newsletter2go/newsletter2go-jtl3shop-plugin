#!/bin/sh

fullName="test";
intVersion="test123";


echo "build plugin.."
PLUGIN_NAME=$fullName"_nl2go_"$intVersion".zip"
cd ./.gitlab/release
if [ -f "makefile" ]; then
    echo "start makefile"
    make version=$intVersion
     if [ -f $PLUGIN_NAME ]; then
        PLUGIN_NAME=$fullName"_nl2go_"$intVersion".zip"
     else
        export PLUGIN_NAME="$(find . -type f -cmin 1)"
        PLUGIN_NAME=$(basename $PLUGIN_NAME)
    fi
fi
