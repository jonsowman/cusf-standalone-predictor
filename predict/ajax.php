<?php

$action = $_GET['action'];

switch($action) {
case "getCSV":
    $uuid = $_GET['uuid'];
    $fh = fopen("preds/".$uuid."/flight_path.csv", "r");
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
    if(file_exists("preds/$uuid/progress.json")) {
        echo true;
    } else {
        echo false;
    }
    break;

}

?>
