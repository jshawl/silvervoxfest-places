#!/bin/bash

now=$(date +"%y%m%d-%H%M")
wp-env run cli wp db export - > ./db/$now.sql
rm -f ./db/latest.sql
cp ./db/$now.sql ./db/latest.sql
