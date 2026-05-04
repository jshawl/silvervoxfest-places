#!/bin/bash

wp-env run cli wp db import /var/www/html/db/latest.sql
