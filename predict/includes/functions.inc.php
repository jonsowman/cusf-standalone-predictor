<?php

/*
 * Functions for the CUSF landing predictor software
 * Jon Sowman 2010
 */

function createModel($post_array) {
    $pred_model = array();

    // first, populate the prediction model
    $pred_model['hour'] = $post_array['hour'];
    $pred_model['min'] = $post_array['min'];
    $pred_model['sec'] = $post_array['sec'];

    $pred_model['month'] = $post_array['month'];
    $pred_model['day'] = $post_array['day'];
    $pred_model['year'] = $post_array['year'];

    $pred_model['lat'] = $post_array['lat'];
    $pred_model['lon'] = $post_array['lon'];
    $pred_model['asc'] = (float)$post_array['ascent'];
    $pred_model['alt'] = $post_array['initial_alt'];
    $pred_model['des'] = $post_array['drag'];
    $pred_model['burst'] = $post_array['burst'];
    $pred_model['float'] = $post_array['float_time'];

    $pred_model['wind_error'] = 0;

    $pred_model['software'] = $post_array['software'];

    return $pred_model;
}

function makesha1hash($pred_model) {
    $sha1str;
    foreach ( $pred_model as $idx => $value ){
        $sha1str .= $idx . "=" . $value . ",";
    }
    $uuid = sha1($sha1str);
    return $uuid;
}

function verifyModel($pred_model, $software_available) {
    if(!isset($pred_model)) return false;
    foreach($pred_model as $idx => $value) {
        if ($idx == "software") {
            if (!in_array($value, $software_available)) return false;
        } else {
            if (!is_numeric($value)) {
                return false;
            }
        }
    }
    return true;
}

function runPred($pred_model) {
    // make in INI file
    makePredDir($pred_model);
    makeINI($pred_model);

    // if we're using --hd, then append it to the exec string
    if ( $pred_model['software'] == "gfs_hd" ) $use_hd ="--hd ";

    // use `at` to automatically background the task
    $ph = popen("at now", "w");
    fwrite($ph, "cd /var/www/hab/predict/ && ./predict.py -v --latdelta=3 --londelta=3 --lat=52 --lon=0 " . $use_hd . $pred_model['uuid']);
    fclose($ph);

}

function makePredDir($pred_model) {
    shell_exec("mkdir preds/" . $pred_model['uuid']); //make sure we use the uuid from model
}

function makeINI($pred_model) { // makes an ini file
    $fh = fopen("preds/" . $pred_model['uuid'] . "/scenario.ini", "a"); //append

    $w_string = "[launch-site]\nlatitude = " . $pred_model['lat'] . "\naltitude = " . $pred_model['alt'] . "\n";
    $w_string .= "longitude = " . $pred_model['lon'] . "\n[atmosphere]\nwind-error = ";
    $w_string .= $pred_model['wind_error'] . "\n[altitude-model]\nascent-rate = " . $pred_model['asc'] . "\n";
    $w_string .= "descent-rate  = " . $pred_model['des'] . "\nburst-altitude = ";
    $w_string .= $pred_model['burst'] . "\n[launch-time]\nhour = " . $pred_model['hour'] . "\n";
    $w_string .= "month = " . $pred_model['month'] . "\nsecond = " . $pred_model['sec'] . "\n";
    $w_string .= "year = " . $pred_model['year'] . "\nday = " . $pred_model['day'] . "\nminute = ";
    $w_string .= $pred_model['min'] . "\n";

    fwrite($fh, $w_string);
    fclose($fh);
}


?>
