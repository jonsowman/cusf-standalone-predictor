<?php

// Enter the Google Maps API for your site
define("GMAPS_API_KEY", "ABQIAAAAzpAeP4iTRyyvc3_y95bQZBSnyWegg1iFIOtWV3Ha3Qw-fH3UlBTg9lMAipYdJi6ac4b5hWAzBkkXgg");

// Who should we email about errors etc?
define("ADMIN_EMAIL", "jon@hexoc.com");

define("LOCATION_SAVE_ENABLE", true);

define("DEBUG", true);
define("AT_LOG", "/tmp/pred_log");

// Path to the root of the git repo inc. trailing /
define("ROOT", "/var/www/hab/predict/");

// Path to prediction data dir from predict/
define("PREDS_PATH", "preds/");

// Filenames used by the predictor
define("SCENARIO_FILE", "scenario.ini");
define("FLIGHT_CSV", "flight_path.csv");
define("PROGRESS_JSON", "progress.json");

?>
