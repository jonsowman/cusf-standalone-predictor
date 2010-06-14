# CUSF Standalone Predictor - Version 2

Working on improving the Cambridge University Spaceflight landing predictor, a web-based tool for predicting the flight path and landing location of latex balloons.  

## Install

The source for the predictor itself is in `pred_src` and instructions for building it can be found there.  

The `predict/preds/` and `gfs` directories needs to have rwx access by the PHP interpreter and the `predict.py` python script. You will need to install the following python packages: pydap, numpy, json, simple-json. We use `at` to automatically background the predictor, so you will need that installed.  

Other than that, just clone this repo to a non web-accessible folder and create symlinks to the `predict/` and `hourly-predictions/` directories in the repo.  

There are useful configuration options in `predict/includes/config.inc.php`.  

## Information

A cronjob should be run to delete directories in the `preds/` directory after a given number of days, probably 7. Predictions older than 7 days are useless anyway.  

The directory names are UUIDs comprised of an SHA1 hash of the launch parameters, and re-running predictions will overwrite data in the existing directory, rather than create a new one.  

## Credits

Credit as detailed in individual files, but notably:  
* Rich Wareham - The new predictor and the hourly predictor system  
* Fergus Noble, Ed Moore and many others  

Jon Sowman 2010  
[http://www.hexoc.com](http://www.hexoc.com)  
[jon@hexoc.com](mailto:jon@hexoc.com)  
