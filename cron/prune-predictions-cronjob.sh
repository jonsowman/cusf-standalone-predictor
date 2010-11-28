#!/bin/bash

PARAM="mtime"
AGE="7"

REPOROOT="/var/www/hab/predict/"
DATADIR="predict/preds"

echo `ls $REPOROOT$DATADIR/ -l | wc -l` "prediction scenarios found"
echo `find $REPOROOT$DATADIR/* -maxdepth 0 -$PARAM +$AGE | wc -l` "of them had $PARAM of more than $AGE days"
echo "Now deleting..."
find $REPOROOT$DATADIR/* -maxdepth 0 -$PARAM +$AGE -exec rm -rf {} \;
echo "Done deleting."
echo `ls $REPOROOT$DATADIR/ -l | wc -l` "prediction scenarios remaining"
