#!/bin/bash

wp-env run cli wp db export - > ./dump.sql
