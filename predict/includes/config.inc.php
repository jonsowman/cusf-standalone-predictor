<?php

$c_admin_email = "jon@hexoc.com";

define("LOCATION_SAVE_ENABLE", true);
$c_location_save_enable = true;

define("DEBUG", true);
define("AT_LOG", "/tmp/pred_log");

// Path to the root of the git repo inc. trailing /
define("ROOT", "/var/www/hab/predict/");

// Path to prediction data dir from predict/
define("PREDS_PATH", "preds/");
$c_preds_path = "preds/";

define("SCENARIO_FILE", "scenario.ini");
$c_scenario_file = "scenario.ini";

define("FLIGHT_CSV", "flight_path.csv");
$c_flight_csv = "flight_path.csv";

define("PROGRESS_JSON", "progress.json");
$c_progress_json = "progress.json";

?>
