# CUSF standalone predictor

Working on improving the Cambridge University Spaceflight landing predictor, used for predicting the flight path and landing location of latex balloons.  

## Install

The source for the prediction software itself is in pred_src and instructions for building it can be found there.  

The `./preds` directory needs to have rwx access by the PHP interpreter and the `get_wind_data.py` python script.  

## Information

A cronjob should be run to delete directories in the `preds` directory after a given number of days, perhaps 7 or 10.  

The directory names are UUIDs comprised of an SHA1 hash of the launch parameters, and re-running predictions will overwrite data in the existing directory, rather than create a new one.  

## Credits

Credit as detailed in individual files, but notably:  
* Rich Wareham - The new predictor and the hourly predictor system  
* Fergus Noble, Ed Moore and many others  

Jon Sowman 2010  
[http://www.hexoc.com](http://www.hexoc.com)  
[jon@hexoc.com](mailto:jon@hexoc.com)  
