#!/bin/bash
path=/data/project/article
PHPpath=/www/server/php/72/bin/php
LogPath=/data/project/log.txt
cd $path
files=$(ls $path)
for filename in $files
do
  $PHPpath  "$filename" >>   $LogPath

done