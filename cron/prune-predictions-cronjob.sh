#!/bin/bash
find /var/www/hab/predict/predict/preds/* -type d -mtime +7 -exec rm -rf {} \;
echo "DONE"

