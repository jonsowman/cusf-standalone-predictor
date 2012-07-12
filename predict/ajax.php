<?php
require_once("includes/functions.inc.php");
require_once("includes/config.inc.php");

$stats = new StatsD();

$action = $_GET['action'];

$software_available = array("gfs", "gfs_hd");

switch($action) {
case "getCSV":
    $uuid = $_GET['uuid'];
    $tryfile = PREDS_PATH . $uuid . "/" . FLIGHT_CSV;
    if(!file_exists($tryfile)) return false;
    $fh = fopen($tryfile, "r");
    $data = array();
    while (!feof($fh)) {
        $line = trim(fgets($fh));
        array_push($data, $line);
    }
    $returned = json_encode($data);
    echo $returned;
    $stats->counting('habhub.predictor.php.get_csv');
    break;

case "JSONexists":
    $uuid = $_GET['uuid'];
    if(file_exists(PREDS_PATH . $uuid . "/" . PROGRESS_JSON)) {
        echo true;
    } else {
        echo false;
    }
    break;

case "locationSave":
    $lat = $_POST['req_lat'];
    $lon = $_POST['req_lon'];
    $alt = $_POST['req_alt'];
    $locname = $_POST['req_name'];
    if ( $locname == '' || !LOCATION_SAVE_ENABLE ) {
        echo "false";
        return;
    }
    $str = "Latitude: " . $lat . "\n" .
        "Longitude: " . $lon . "\n" .
        "Altitude: " . $alt . "\n" .
        "Name: " . $locname . "\n";
    $headers = "From: ". ADMIN_EMAIL ."\r\nReply-To:blackhole@hexoc.com\r\nX-Mailer: PHP/".phpversion();
    if ( mail(ADMIN_EMAIL, "Location Request Save", $str, $headers) ) {
        echo "true";
    } else {
        echo "false";
    }
    break;

case "getModelByUUID":
    $uuid = ( isset($_GET['uuid']) ? $_GET['uuid'] : false );
    if( !$uuid ) die ("No uuid given to getModelByUUID");
    // make a new model
    $pred_model = array();
    if ( !file_exists(PREDS_PATH . $uuid . "/" . SCENARIO_FILE ) ) {
        $pred_model['valid'] = false;
        $stats->counting('habhub.predictor.php.couldnt_get_by_uuid');
    } else {
        // populate the array, JSON encode it and return
        $pred_model = parse_ini_file(PREDS_PATH . $uuid . "/" . SCENARIO_FILE);
        if ( verifyModel($pred_model, $software_available) ){
            $pred_model['valid'] = true;
        } else {
            $pred_model['valid'] = false;
        }
        $pred_model['uuid'] = $uuid;
        $stats->counting('habhub.predictor.php.got_by_uuid');
    }
    echo json_encode($pred_model);
    break;

case "submitForm":
    $pred_model = array();
    $json_return = array();
    $json_return['valid'] = "false";

    // Make sure we have a submitted form
    if ( isset($_POST['submit'])) {
        // First, make a model from the form data
        if ( !$pred_model = createModel($_POST)) {
            $json_return['error'] = "Server couldn't make a model from the form 
                data";
            echo json_encode($json_return);
            $stats->counter('habhub.predictor.php.form_error');
            break;
        }

        // If that worked, make sure the model is valid
        $verify_dump = verifyModel($pred_model, $software_available);
        if ( !$verify_dump['valid'] ) {
            $json_return['error'] = $verify_dump['msg'];
            echo json_encode($json_return);
            $stats->counter('habhub.predictor.php.invalid_model')
            break;
        }

        // If we have a valid model, try and make a UUID
        if ( !$pred_model['uuid'] = makesha1hash($pred_model) ) {
            $json_return['error'] = "Couldn't make the SHA1 hash";
            echo json_encode($json_return);
            $stats->counter('habhub.predictor.php.unhashable');
            break;
        }

        // If all of the above worked, let's run the prediction
        runPred($pred_model);
        $json_return['valid'] = "true";
        $json_return['uuid'] = $pred_model['uuid'];
        $json_return['timestamp'] = $pred_model['timestamp'];
        $stats->counting('habhub.predictor.php.prediction_run');

    } else {
        $json_return['error'] = "The form submit function was called without 
            any data";
        $stats->counting('habhub.predictor.php.no_form_data');
    }

    echo json_encode($json_return);
    break;

default:
    echo "Couldn't interpret 'action' variable";
    break;

}

?>
