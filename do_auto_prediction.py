#!/usr/bin/python

import os

f = os.popen("do_auto_prediction.py")
for line in f.readlines():
	if line[:2] == 'ok':
		print line

