<?php
// if the form was submitted we should
// 1. sanity check inputs
// 2. generate an ini file
// 3. start prediction running
// 4. start ajax server poller
// 5. display the prediction data when run is complete
// 6. make the ini and the csv/kml available for download
//
// get the time for populating the form
$time = time() + 3600;
$form_submitted = 0;
$software_available = array("gfs", "gfs_hd");

$pred_model = array();

if ( isset($_POST['submit'])) { // form was submitted, let's run a pred!

    $form_submitted = 1;

    // first, populate the prediction model
    $pred_model['hour'] = $_POST['hour'];
    $pred_model['min'] = $_POST['min'];
    $pred_model['sec'] = $_POST['sec'];

    $pred_model['month'] = $_POST['month'];
    $pred_model['day'] = $_POST['day'];
    $pred_model['year'] = $_POST['year'];

    $pred_model['lat'] = $_POST['lat'];
    $pred_model['lon'] = $_POST['lon'];
    $pred_model['asc'] = (float)$_POST['ascent'];
    $pred_model['alt'] = $_POST['initial_alt'];
    $pred_model['des'] = $_POST['drag'];
    $pred_model['burst'] = $_POST['burst'];
    $pred_model['float'] = $_POST['float_time'];

    $pred_model['wind_error'] = 0;
    
    $pred_model['software'] = $_POST['software'];

    // verify the model here
    if ( !verifyModel($pred_model, $software_available) ) die ("The model verification failed");

    $pred_model['uuid'] = makesha1hash($pred_model); // make a sha1 hash of the model for uuid

    // make a timestamp of the form data
    $pred_model['timestamp'] = mktime($_pred_model['hour'], $_pred_model['min'], $_pred_model['sec'], (int)$_pred_model['month'] + 1, $_pred_model['day'], (int)$_pred_model['year'] - 2000);
        // and check that it's within range
    if ($pred_model['timestamp'] > (time() + 180*3600)) {
            die("The time was too far in the future, 180 days max");
    }
    // now we have a populated model, run the predictor
    runPred($pred_model);
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


function runGRIB($pred_model) { // runs the grib predictor
    $lockfile = fopen("lock", "w");
    $shellcmd = "./one_off_prediction " . $pred_model['lat'] . " " . $pred_model['lon'] . " " . $pred_model['alt'] ." " . (float)$pred_model['asc'] . " " . $pred_model['des']*1.1045 . " "  . $pred_model['burst'] . " " . $pred_model['timestamp']  . " " . $pred_model['float'] . " &";
    echo $shellcmd;
    //shell_exec($shellcmd);
    if (!file_exists("flight_path.csv")) {
        unlink("lock");
        die("The predictor didn't write a file");
    }
    shell_exec("mv flight_path.* preds/".$pred_model['uuid']."/");
    unlink("lock");
}

?>

<html>
    <head>
        <title>CUSF Landing Prediction 2 - GUI test</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<link href="css/pred.css" type="text/css" rel="stylesheet">
<script src="js/jquery.js" type="text/javascript"></script>
<script src="js/jquery.form.js" type="text/javascript"></script>
<script src="js/pred.js" type="text/javascript"></script>
<script type="text/javascript">

var form_submitted = <?php echo $form_submitted; ?>;
var running_uuid = '<?php echo $pred_model['uuid']; ?>';
var ajaxEventHandle;


function predSub() {
    appendDebug(null, 1); // clear debug window
    appendDebug("Sending data to server");
    appendDebug("Downloading GRIB data for tile, this could take some time...");
    appendDebug("Do NOT stop or refresh your browser.");
}

function handlePred(pred_uuid) {
    // Clear the debug window
    appendDebug(null, 1);
    appendDebug("Prediction running with uuid: " + running_uuid);
    appendDebug("Attempting to download GFS data for prediction");
    // ajax to poll for progress

    ajaxEventHandle = setInterval("getJSONProgress('"+pred_uuid+"')", 3000);
    appendDebug("Getting flight path from server....");
    //getCSV(pred_uuid);
}

function getCSV(pred_uuid) {
    $.get("ajax.php", { "action":"getCSV", "uuid":pred_uuid }, function(data) {
        appendDebug("Got JSON response from server for flight path, parsing...");
        if (parseCSV(data) ) {
            appendDebug("Parsing function returned all OK - DONE");
        } else {
            appendDebug("The parsing function failed");
        }
    }, 'json');
}

function getJSONProgress(pred_uuid) {
    $.ajax({
        url:"preds/"+pred_uuid+"/progress.json",
        dataType:'json',
        timeout: 500,
        // complete: function(data, httpstatus) {
        //     appendDebug(httpstatus);
        // },
        success: processProgress
    });
}

function processProgress(progress) {
    if ( progress['error'] ) {
        clearInterval(ajaxEventHandle);
        appendDebug("There was an error in running the prediction: "+progress['error']);
    } else {
        // get the progress of the wind data
        if ( progress['gfs_complete'] == true ) {
            if ( progress['pred_complete'] == true ) { // pred has finished
                appendDebug("The predictor finished running.");
                clearInterval(ajaxEventHandle); // clear calling this function
                getCSV(running_uuid);
            } else if ( progress['pred_running'] != true ) {
                appendDebug("Predictor not yet running...");
            } else if ( progress['pred_running'] == true ) {
                appendDebug("Predictor currently running");
            }
        } else {
            appendDebug("Downloaded " + progress['gfs_percent'] + "%");
        }
    }
    return true;
}

var map;
var launch_img = "images/marker-sm-red.png";
var land_img = "images/marker-sm-red.png";
var burst_img = "images/pop-marker.png";

function initialize() {
    // make the map and set center
    var latlng = new google.maps.LatLng(52, 0);
    var myOptions = {
      zoom: 8,
      center: latlng,
      mapTypeId: google.maps.MapTypeId.ROADMAP
    };
    map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
    // submit the form here
    $("#modelForm").ajaxForm({
        url: 'ajax.php?action=submitForm',
        type: 'POST',
        success: function(data) {
            alert("Success: " + data);
        }
    });
    if ( form_submitted ) handlePred(running_uuid);
}


function parseCSV(lines) {
    if(lines.length <= 0) {
        appendDebug("The server returned an empty CSV file");
        return false;
    }
    var path = [];
    var max_height = -10; //just any -ve number
    var max_point = null;
    var launch_pt;
    var land_pt;
        $.each(lines, function(idx, line) {
            entry = line.split(',');
            if(entry.length >= 4) { // check valid entry length
                var point = new google.maps.LatLng( parseFloat(entry[1]), parseFloat(entry[2]) );
                if ( idx == 0 ) { // get the launch lat/long for marker
                    var launch_lat = entry[1];
                    var launch_lon = entry[2];
                    launch_pt = point;
                }

                // set on every iteration, last valid entry
                // gives landing position
                var land_lat = entry[1];
                var land_lon = entry[2];
                land_pt = point;
                
                if(parseFloat(entry[3]) > max_height) {
                        max_height = parseFloat(entry[3]);
                        max_point = point;
                }
                path.push(point);
            }
        });

    appendDebug("Flight data parsed, creating map plot...");

    // make some nice icons
    var launch_icon = new google.maps.MarkerImage(launch_img,
        new google.maps.Size(16,16),
        new google.maps.Point(0, 0),
        new google.maps.Point(8, 8)
    );
    
    var land_icon = new google.maps.MarkerImage(land_img,
        new google.maps.Size(16,16),
        new google.maps.Point(0, 0),
        new google.maps.Point(8, 8)
    );
      
    var launch_marker = new google.maps.Marker({
        position: launch_pt,
        map: map,
        icon: launch_icon,
        title: 'Balloon launch'
    });

    var land_marker = new google.maps.Marker({
        position: land_pt,
        map:map,
        icon: land_icon,
        title: 'Predicted Landing'
    });

    // now add the launch/land markers to map
    launch_marker.setMap(map);
    land_marker.setMap(map);

    var path_polyline = new google.maps.Polyline({
        path:path,
        strokeColor: '#000000',
        strokeWeight: 3,
        strokeOpacity: 0.75
    });

    path_polyline.setMap(map);
    
    var pop_marker = new google.maps.Marker({
            position: max_point,
            map: map,
            icon: burst_img,
            title: 'Balloon burst (max. altitude: ' + max_height + 'm)',
    });

    return true;

}

</script>
</head>
<body onload="initialize()">
  <div id="map_canvas" style="width:100%; height:100%"></div>
<div id="scenario_template" class="box">
<h1>Debug Window</h1>
<span id="debuginfo">No Messages</span>
</div>

<div id="input_form" class="box"> 
<form action="" id="modelForm" name="modelForm">
<table>
	<tr>
		<td>Launch Site:</td>
		<td>
			<select id="site" name="launchsite" onchange="UpdateLaunchSite(this.selectedIndex)">
				<option value="Churchill">Churchill</option>
				<option value="EARS">EARS</option>
				<option value="Glenrothes">Glenrothes</option>
				<option value="Bujaraloz, Monegros">Bujaraloz, Monegros</option>
				<option value="Adelaide Airport">Adelaide Airport</option>
				<option id="other" value="other">Other</option>
			</select>
		</td>
	<tr>
		<td>Latitude:</td>
		<td><input id="lat" type="text" name="lat" value="52.2135" onKeyDown="SetSiteOther()"></td>
	</tr>
    <tr>
        <td>Longitude:</td>
        <td><input id="lon" type="text" name="lon" value="0.0964" onKeyDown="SetSiteOther()"></td>
    </tr>
    <tr>
        <td>Launch altitude (m):</td>
        <td><input id="initial_alt" type="text" name="initial_alt" value="0"></td>
    </tr>
	<tr>
		<td>Launch Time:</td>
		<td>
			<input id="hour" type="text" name="hour" value="<?php echo date("H", $time); ?>" maxlength="2" size="2"> :
			<input id="min" type="text" name="min" value="<?php echo date("i", $time); ?>" maxlength="2" size="2">
			<input id="sec" type="hidden" name="sec" value="0"></td></tr>
			<tr><td>Launch Date:</td><td>
			<input id="day" type="text" name="day" value="<?php echo date("d", $time); ?>" maxlength="2" size="2">
			<select id="month" name="month">
				<option value="1"<?php if (date("n", $time) == 1) echo " selected"; ?>>Jan</option>
				<option value="2"<?php if (date("n", $time) == 2) echo " selected"; ?>>Feb</option>
				<option value="3"<?php if (date("n", $time) == 3) echo " selected"; ?>>Mar</option>
				<option value="4"<?php if (date("n", $time) == 4) echo " selected"; ?>>Apr</option>
				<option value="5"<?php if (date("n", $time) == 5) echo " selected"; ?>>May</option>
				<option value="6"<?php if (date("n", $time) == 6) echo " selected"; ?>>Jun</option>
				<option value="7"<?php if (date("n", $time) == 7) echo " selected"; ?>>Jul</option>
				<option value="8"<?php if (date("n", $time) == 8) echo " selected"; ?>>Aug</option>
				<option value="9"<?php if (date("n", $time) == 9) echo " selected"; ?>>Sep</option>
				<option value="10"<?php if (date("n", $time) == 10) echo " selected"; ?>>Oct</option>
				<option value="11"<?php if (date("n", $time) == 11) echo " selected"; ?>>Nov</option>
				<option value="12"<?php if (date("n", $time) == 12) echo " selected"; ?>>Dec</option>
			</select>
			<input id="year" type="text" name="year" value="<?php echo date("Y", $time); ?>" maxlength="4" size="4">
		</td>
    <tr>
        <td>Ascent Rate (m/s):</td>
        <td><input id="ascent" type="text" name="ascent" value="3"></td>
    </tr>
    <tr>
        <td>Descent Rate (sea level m/s):</td>
        <td><input id="drag" type="text" name="drag" value="5"></td>
    </tr>
    <tr>
        <td>Burst Altitude (m):</td>
        <td><input id="burst" type="text" name="burst" value="30000"></td>
    </tr>
    <tr>
        <td>Float time at apogee (s):</td>
        <td><input id="float" type="text" name="float_time" value="0"></td>
    </tr>
    <tr>
        <td>Landing prediction software: <td>
        <select id="software" name="software">
            <option value="gfs" selected="selected">GFS (faster, less accurate)</option>
            <option value="gfs_hd">GFS HD (slower, more accurate)</option>
        </select>
	<tr>
                <td>
                </td>
		<td><input type="submit" name="submit" id="run_pred_btn" value="Run Prediction!"></td>
	</tr>
</table>
</form>
</div>


</body>
</html>
