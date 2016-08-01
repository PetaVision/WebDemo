#!/bin/bash
DocumentRoot=/var/www/html

mkdir $DocumentRoot/archive
mkdir $DocumentRoot/uploads
cp -r stylesheets $DocumentRoot
cp -r images $DocumentRoot
cp -r montage $DocumentRoot
mkdir $DocumentRoot/montage/pngOutput
cp favicon.ico $DocumentRoot
cp index.html $DocumentRoot
cp monitor.php $DocumentRoot
cp upload.php $DocumentRoot
