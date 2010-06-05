<?php
require_once("includes/functions.inc.php");
// get the time for pre-populating the form
$time = time() + 3600;
$current_uuid = ( isset($_GET['uuid'])? $_GET['uuid'] : "0" );
// if we were given a UUID, try and construct its model
if ( $current_uuid != 0 ) {
    if ( $pred_model = getModelByUUID($current_uuid) ) {
    } else {
        // assume we weren't given a UUID (bad FIXME)
        $current_uuid = 0;
    }
}
?>

<html>
<head>
<title>CUSF Landing Prediction - Version 2</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<script type="text/javascript" src="http://www.google.com/jsapi?key=ABQIAAAAzpAeP4iTRyyvc3_y95bQZBSnyWegg1iFIOtWV3Ha3Qw-fH3UlBTg9lMAipYdJi6ac4b5hWAzBkkXgg"></script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<link href="css/pred.css" type="text/css" rel="stylesheet">
<link href="css/cupertino/jquery-ui-1.8.1.custom.css" type="text/css" rel="stylesheet">
<script type="text/javascript">
// load jquery and jqueryui before loading jquery.form.js later
google.load("jquery", "1.4.2");
google.load("jqueryui", "1.8.1");
</script>
<script src="js/jquery.form.js" type="text/javascript"></script>
<script src="js/pred.js" type="text/javascript"></script>
<script type="text/javascript">

var ajaxEventHandle;
var current_uuid = '<?php echo $current_uuid ?>';

var map;
var map_items = [];
var launch_img = "images/target-1-sm.png";
var land_img = "images/target-8-sm.png";
var burst_img = "images/pop-marker.png";
var clickListener;
var clickMarker;

function initialize() {
    // make the map and set center
    var latlng = new google.maps.LatLng(52, 0);
    var myOptions = {
      zoom: 8,
      scaleControl: true,
      scaleControlOptions: { position: google.maps.ControlPosition.BOTTOM_LEFT } ,
      mapTypeId: google.maps.MapTypeId.TERRAIN,
      center: latlng,
    };
    map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
    // attach form submit event handler to launch card
    $("#modelForm").ajaxForm({
        url: 'ajax.php?action=submitForm',
        type: 'POST',
        success: function(data) {
            var data_split = data.split("|");
            if ( data_split[0] == 0 ) {
                appendDebug("The server rejected the submitted form data");
                throwError("The server rejected the submitted form data");
                resetGUI();
            } else {
                predSub();
                appendDebug("The server accepted the form data");
                // update the global current_uuid variable
                current_uuid = data_split[1];
                appendDebug("The server gave us uuid:<br>" + current_uuid);
                appendDebug("Starting to poll for progress JSON");
                handlePred(current_uuid);
            }
        }
    });
    // activate the "Set with Map" link
    $("#setWithClick").click(function() {
        setLatLonByClick(true);
    });
    // attach onchange handlers to the lat/long boxes
    $("#lat").change(function() {
        plotClick();
    });
    $("#lon").change(function() {
        plotClick();
    });
    $("#site").change(function() {
        plotClick();
    });
    $("#input_form").draggable({containment: '#map_canvas'});
    if ( current_uuid != '0' ) {
        appendDebug("Got an old UUID to plot:<br>" + current_uuid);
        appendDebug("Trying to populate form with scenario data");
        populateFormByUUID(current_uuid);
        appendDebug("Trying to get flight path from server...");
        getCSV(current_uuid);
    }
    $("#scenario_template").hide();
    $("#showHideDebug").click(function() {
        toggleWindow("scenario_template", "showHideDebug", "Show Debug", "Hide Debug");
    });
    $("#showHideDebug_status").click(function() {
        toggleWindow("scenario_template", "showHideDebug", "Show Debug", "Hide Debug");
    });
    $("#showHideForm").click(function() {
        toggleWindow("input_form", "showHideForm", "Show Launch Card",
            "Hide Launch Card");
    });
    $("#closeErrorWindow").click(function() {
        $("#error_window").fadeOut();
    });
    // plot the initial launch location
    plotClick();
    google.maps.event.addListener(map, 'mousemove', function(event) {
        showMousePos(event.latLng);
    });
}



</script>
</head>
<body onload="initialize()" bgcolor="#000000">

<div id="map_canvas" style="width:100%; height:100%"></div>

<div id="scenario_template" class="box">
<h1>Debug Window</h1>
<span id="debuginfo">No Messages</span>
</div>

<div id="status_message" class="box">
<div id="prediction_progress"></div>
<div id="prediction_percent"></div>
<br>
<span id="prediction_status"></span><br>
<a><span id="showHideDebug_status">Toggle Debug</span></a></span>
</div>

<div id="error_window" class="box">
<span id="error_message">Nothing here!</span>
<br /><br />
<a id="closeErrorWindow">Close</a>
</div>

<!-- scenario info -->
<div id="scenario_info" class="box">
<h1>Scenario Information</h1>
<span id="cursor_info">Current mouse position: 
Lat: <span id="cursor_lat">?</span> 
Lon: <span id="cursor_lon">?</span>
</span><br />
<span id="cursor_pred" style="display:none">
Range: <span id="cursor_pred_range"></span>km, 
Flight Time: <span id="cursor_pred_time"></span><br />
Cursor range from launch: <span id="cursor_pred_launchrange">?</span>km, 
land: <span id="cursor_pred_landrange">?</span>km
</span>
<br />
<a><span id="showHideDebug">Show Debug</span></a></span> | 
<a><span id="showHideForm">Hide Launch Card</span></a></span>
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
                <td><input id="lat" type="text" name="lat" value="<?php
                    echo ($current_uuid!=0 ? $pred_model['latitude'] : "52.2135");
                    ?>" onKeyDown="SetSiteOther()"></td>
	</tr>
    <tr>
        <td>Longitude:</td>
        <td><input id="lon" type="text" name="lon" value="<?php
            echo ($current_uuid!=0 ? $pred_model['longitude'] : "0.0964");
            ?>" onKeyDown="SetSiteOther()"></td>
    </tr>
    <tr>
    <td><a id="setWithClick">Set with map</a></td>
    <td><a id="requestLocationSave">Request to save</a></td>
    </tr>
    <tr>
        <td>Launch altitude (m):</td>
        <td><input id="initial_alt" type="text" name="initial_alt" value="<?php
                echo ($current_uuid!=0 ? $pred_model['altitude'] : "0");?>"></td>
    </tr>
	<tr>
		<td>Launch Time:</td>
		<td>
                        <input id="hour" type="text" name="hour" value="<?php
                    echo ($current_uuid!=0 ? $pred_model['hour'] : date("H", $time));
                    ?>" maxlength="2" size="2"> :
                        <input id="min" type="text" name="min" value="<?php
                    echo ($current_uuid!=0 ? $pred_model['minute'] : date("i", $time));
                    ?>" maxlength="2" size="2">
			<input id="sec" type="hidden" name="second" value="0"></td></tr>
			<tr><td>Launch Date:</td><td>
                        <input id="day" type="text" name="day" value="<?php
                    echo ($current_uuid!=0 ? $pred_model['day'] : date("d", $time));
                    ?>" maxlength="2" size="2">
                        <select id="month" name="month"><?php
                    // php enumeration
                    for($i=1;$i<=12;$i++) {
                        echo "<option value=\"" . $i . "\"";
                        if ($i == date("n", $time) && $current_uuid==0 ) {
                            echo " selected=\"selected\"";
                        } else if ($current_uuid != 0 && $i == $pred_model['month']) {
                            echo " selected=\"selected\"";
                        }
                        echo ">".date("M", mktime(0,0,0,$i,11,1978))."</option>\n";
                    }

                    ?></select>
                        <input id="year" type="text" name="year" value="<?php
                    echo ($current_uuid!=0 ? $pred_model['year'] : date("Y", $time));
                    ?>" maxlength="4" size="4">
		</td>
    <tr>
        <td>Ascent Rate (m/s):</td>
        <td><input id="ascent" type="text" name="ascent" value="<?php
                    echo ($current_uuid!=0 ? $pred_model['ascent-rate'] : "5");
                    ?>"></td>
    </tr>
    <tr>
        <td>Descent Rate (sea level m/s):</td>
        <td><input id="drag" type="text" name="drag" value="<?php
                    echo ($current_uuid!=0 ? $pred_model['descent-rate'] : "5");
                    ?>"></td>
    </tr>
    <tr>
        <td>Burst Altitude (m):</td>
        <td><input id="burst" type="text" name="burst" value="<?php
                    echo ($current_uuid!=0 ? $pred_model['burst-altitude'] : "30000");
                    ?>"></td>
    </tr>
    <tr>
        <td>Landing prediction software: </td><td>
        <select id="software" name="software">
            <option value="gfs" selected="selected">GFS</option>
            <option value="gfs_hd">GFS HD</option>
        </select></td></tr>
        <tr><td>Lat/Lon Deltas: </td>
        <td>Lat: 
        <select id="delta_lat" name="delta_lat">
            <option value="3" selected="selected">3</option>
            <option value="5">5</option>
            <option value="10">10</option>
        </select>&nbsp;Lon: 
        <select id="delta_lon" name="delta_lon">
            <option value="3" selected="selected">3</option>
            <option value="5">5</option>
            <option value="10">10</option>
        </select>
        </td>
        </tr>
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
