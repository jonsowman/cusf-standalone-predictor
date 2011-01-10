# CUSF Standalone Predictor - Version 2

Cambridge University Spaceflight landing predictor - a web-based tool for predicting the flight path and landing location of latex meteorological sounding balloons.  

## Install

The source for the predictor itself is in `pred_src/` and instructions for building it can be found there.  

The following items need to be executable (`chmod +x ./predict.py`) by the user under which the predictor runs:  

*   `predict.py`
*   `pred_src/pred` (once compiled)
*   `cron/clear-pydap-cache-cronjob.sh`
*   `cron/purge-predictions-cronjob.sh`

The `predict/preds/` and `gfs/` directories need to have rwx access by the PHP interpreter and the `predict.py` python script. You will need to install the following python packages: pydap, numpy, json, simple-json. We use `at` to automatically background the predictor, so you will need that installed.  

Other than that, just clone this repo to a non web-accessible folder and create symlinks to the `predict/` directory in the repo.  

There are useful configuration options in `predict/includes/config.inc.php`.  

## Information

The two shell scripts in the `cron/` directory should both be run daily. `clear-pydap-cache-cronjob.sh` clears the cache used by pydap so that old data does not build up. `purge-predictions-cronjob.sh` deletes scenarios and predictions not accessed or modified within the last 7 days. Re-running a prediction for a scenario will therefore reset its time to live to 7 more days.   

The directory names are UUIDs comprised of an SHA1 hash of the launch parameters, and re-running predictions will overwrite data in the existing directory, rather than create a new one.  

We use GFS data provided by the NOAA, accessed via NDAP and their [NOMADS](http://nomads.ncep.noaa.gov) distribution system. The [1.0x1.0 degree data](http://nomads.ncep.noaa.gov/txt_descriptions/GFS_high_resolution_doc.shtml) (26 vertical pressure levels) is used for standard predictions, and the [0.5x0.5 degree data](http://nomads.ncep.noaa.gov/txt_descriptions/GFS_half_degree_doc.shtml) (47 vertical pressure levels) is used for the high definition (HD) predictions.  

## License

This work is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or any later version. This work is distributed in the hope that it will be useful, but without any warranty; without even the implied warranty of merchantability or fitness for a particular purpose.  

## Credits & Acknowledgments

Credit as detailed in individual files, but notably:  

* Rich Wareham - The new predictor and the hourly predictor system  
* Fergus Noble, Ed Moore and many others  

Adam Greig - [http://www.randomskk.net](http://www.randomskk.net) - [random@randomskk.net](mailto:random@randomskk.net)  
Jon Sowman - [http://www.hexoc.com](http://www.hexoc.com) - [jon@hexoc.com](mailto:jon@hexoc.com)  

Copyright Cambridge University Spaceflight 2009-2011 - All Rights Reserved
