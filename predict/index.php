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
?>

<html>
    <head>
        <title>CUSF Landing Prediction 2 - GUI test</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<script type="text/javascript" src="http://www.google.com/jsapi?key=ABQIAAAAzpAeP4iTRyyvc3_y95bQZBSnyWegg1iFIOtWV3Ha3Qw-fH3UlBTg9lMAipYdJi6ac4b5hWAzBkkXgg"></script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<link href="css/pred.css" type="text/css" rel="stylesheet">
<link href="css/cupertino/jquery-ui-1.8.1.custom.css" type="text/css" rel="stylesheet">
<script type="text/javascript">
google.load("jquery", "1.4.2");
google.load("jqueryui", "1.8.1");
</script>
<script src="js/jquery.form.js" type="text/javascript"></script>
<script src="js/pred.js" type="text/javascript"></script>
<script type="text/javascript">

var ajaxEventHandle;
var running_uuid = '<?php
if ( isset($_GET['uuid']) ) {
    echo $_GET['uuid'];
} else {
    echo "0";
}
?>';

function predSub() {
    appendDebug(null, 1); // clear debug window
    appendDebug("Sending data to server");
    appendDebug("Attempting to start the predictor...");
    // initialise progress bar
    $("#prediction_progress").progressbar({ value: 0 });
    $("#prediction_status").html("Sending data to server...");
    $("#status_message").fadeIn(250);
}

function handlePred(pred_uuid) {
    $("#prediction_status").html("Downloading wind data...");
    appendDebug("Prediction running with uuid: " + pred_uuid);
    appendDebug("Attempting to download GFS data for prediction");
    // ajax to poll for progress

    ajaxEventHandle = setInterval("getJSONProgress('"+pred_uuid+"')", 2000);
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
                $("#prediction_status").html("Prediction finished.");
                $("#status_message").fadeOut(500);
                // now clear the status window
                $("#prediction_status").html("");
                $("#prediction_progress").progressbar("options", "value", 0);
                $("#prediction_percent").html("");
                appendDebug("The predictor finished running.");
                // stop polling for JSON
                clearInterval(ajaxEventHandle);
                // parse the data
                getCSV(running_uuid);
            } else if ( progress['pred_running'] != true ) {
                $("#prediction_status").html("Waiting for predictor to run...");
                appendDebug("Predictor not yet running...");
            } else if ( progress['pred_running'] == true ) {
                $("#prediction_status").html("Predictor running...");
                appendDebug("Predictor currently running");
            }
        } else {
            $("#prediction_progress").progressbar("option", "value",
                progress['gfs_percent']);
            $("#prediction_percent").html(progress['gfs_percent'] + 
                "% - Estimated time remaining: " + progress['gfs_timeremaining']);
            appendDebug("Downloaded " + progress['gfs_percent'] + "%");
        }
    }
    return true;
}

var map;
var map_items = [];
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
            predSub();
            var data_split = data.split("|");
            if ( data_split[0] == 0 ) {
                alert("Server error");
            } else {
                running_uuid = data_split[1];
                handlePred(running_uuid);
            }
        }
    });
    // if ( running_uuid != 0 ) handlePred(running_uuid);
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
    clearMapItems();
    
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

    var path_polyline = new google.maps.Polyline({
        path:path,
        map: map,
        strokeColor: '#000000',
        strokeWeight: 3,
        strokeOpacity: 0.75
    });

    var pop_marker = new google.maps.Marker({
            position: max_point,
            map: map,
            icon: burst_img,
            title: 'Balloon burst (max. altitude: ' + max_height + 'm)',
    });

    // now add the launch/land markers to map
    map_items.push(launch_marker);
    map_items.push(land_marker);
    map_items.push(pop_marker);
    map_items.push(path_polyline);

    return true;

}

function clearMapItems() {
    appendDebug("Clearing previous map trace");
    if(map_items) {
        for(i in map_items) {
            map_items[i].setMap(null);
        }
    }
    map_items = [];
}

</script>
</head>
<body onload="initialize()">

<div id="map_canvas" style="width:100%; height:100%"></div>

<div id="scenario_template" class="box">
<h1>Debug Window</h1>
<span id="debuginfo">No Messages</span>
</div>

<div id="status_message" class="box">
<div id="prediction_progress"></div>
<div id="prediction_percent"></div>
<br>
<div id="prediction_status"></div>
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
        <td><input id="ascent" type="text" name="ascent" value="5"></td>
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
