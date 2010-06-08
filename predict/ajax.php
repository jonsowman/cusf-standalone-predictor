<?php
require_once("includes/functions.inc.php");
require_once("includes/config.inc.php");

$action = $_GET['action'];

$software_available = array("gfs", "gfs_hd");

switch($action) {
case "getCSV":
    $uuid = $_GET['uuid'];
    $tryfile = $c_preds_path.$uuid."/".$c_flight_csv;
    if(!file_exists($tryfile)) return false;
    $fh = fopen($tryfile, "r");
    $data = array();
    while (!feof($fh)) {
        $line = trim(fgets($fh));
        array_push($data, $line);
    }
    $returned = json_encode($data);
    echo $returned;
    break;

case "JSONexists":
    $uuid = $_GET['uuid'];
    if(file_exists($c_preds_path.$uuid."/".$c_progress_json)) {
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
    if ( $locname == '' ) {
        echo "false  ".$locname;
        return;
    }
    $str = "Latitude: " . $lat . "\n" .
        "Longitude: " . $lon . "\n" .
        "Altitude: " . $alt . "\n" .
        "Name: " . $locname . "\n";
    if ( mail($c_admin_email, "Location Request Save", $str) ) {
        echo "true";
    } else {
        echo "false";
    }
    break;

case "getModelByUUID":
    $uuid = ( isset($_GET['uuid']) ? $_GET['uuid'] : false );
    if( !uuid ) die ("No uuid given to getModelByUUID");
    // make a new model
    $pred_model = array();
    if ( !file_exists($c_preds_path.$uuid."/".$c_scenario_file) ) {
        $pred_model['valid'] = false;
    } else {
        // populate the array, JSON encode it and return
        $pred_model = parse_ini_file($c_preds_path.$uuid."/".$c_scenario_file);
        if ( verifyModel($pred_model, $software_available) ){
            $pred_model['valid'] = true;
        } else {
            $pred_model['valid'] = false;
        }
        $pred_model['uuid'] = $uuid;
    }
    echo json_encode($pred_model);
    break;

case "submitForm":
    $pred_model = array();

    if ( isset($_POST['submit'])) {
        // form was submitted, let's run a pred!
        // first, make a model from the form data
        if ( !$pred_model = createModel($_POST)) {
            echo false;
            break;
        }

        // verify the model
        if ( !verifyModel($pred_model, $software_available) ) {
            echo false;
            break;
        }

        // make a sha1 hash of the model for uuid
        $pred_model['uuid'] = makesha1hash($pred_model); 

        // now we have a populated model, run the predictor
        runPred($pred_model);
        echo true . "|" . $pred_model['uuid'] . "|" . $pred_model['timestamp'];
    } else {
        echo "The form submit function was called without any data";
        echo false;
    }
    break;

default:
    echo "Couldn't interpret 'action' variable";
    break;

}

?>
