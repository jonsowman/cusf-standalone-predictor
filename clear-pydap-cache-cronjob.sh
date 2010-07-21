#!/bin/bash
cd /tmp/pydap-cache
ls /tmp/pydap-cache -1 | grep -v `date +"%Y%m%d"` | xargs rm -f
echo "DONE"
