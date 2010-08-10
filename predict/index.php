<?php

/*
 * CUSF Landing Prediction Version 2
 * http://www.cuspaceflight.co.uk
 *
 * Jon Sowman 2010
 * jon@hexoc.com
 * http://www.hexoc.com
 *
 * http://github.com/jonsowman/cusf-standalone-predictor
 *
 */

require_once("includes/config.inc.php");
require_once("includes/functions.inc.php");
// get the time for pre-populating the form
$time = time() + 3600;
?>

<html>
<head>
<title>CUSF Landing Predictor 2.0</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<script type="text/javascript" src="http://www.google.com/jsapi?key=<?php echo GMAPS_API_KEY; ?>">
</script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<link href="css/pred.css" type="text/css" rel="stylesheet">
<link href="css/cupertino/jquery-ui-1.8.1.custom.css" type="text/css" rel="stylesheet">
<script type="text/javascript">
// load jquery and jqueryui before loading jquery.form.js later
google.load("jquery", "1.4.2");
google.load("jqueryui", "1.8.1");
</script>
<script src="js/jquery.form.js" type="text/javascript"></script>
<script src="js/jquery.jookie.js" type="text/javascript"></script>
<script src="js/date.jsport.js" type="text/javascript"></script>
<script src="js/pred.js" type="text/javascript"></script>
<script type="text/javascript">

var ajaxEventHandle;
var current_uuid = '0';

var map;
var map_items = [];
var launch_img = "images/target-1-sm.png";
var land_img = "images/target-8-sm.png";
var burst_img = "images/pop-marker.png";
var clickListener;
var clickMarker;

// polling progress parameters
var ajaxTimeout = 500;
var maxAjaxTimeout = 2000;
var deltaAjaxTimeout = 500;
var stdPeriod = 2000; // standard
var hlPeriod = 10000; // high latency
var hlTimeout = 5000; // high latency

</script>
</head>
<body>

<!-- map canvas -->
<div id="map_canvas"></div>

<!-- debug window -->
<div id="scenario_template" class="box ui-corner-all">
<h1>Debug Window</h1>
<span id="debuginfo">No Messages</span>
</div>

<!-- prediction progress window -->
<div id="status_message" class="box ui-corner-all">
    <div id="prediction_progress"></div>
    <div id="prediction_percent"></div>
    <br>
    <span id="prediction_status"></span><br>
    <a><span id="showHideDebug_status">Toggle Debug</span></a>
</div>

<!-- error window -->
<div id="error_window" class="box ui-corner-all">
    <span id="error_message">Nothing here!</span>
    <br /><br />
    <a id="closeErrorWindow">Close</a>
</div>

<!-- scenario info -->
<div id="scenario_info" class="box ui-corner-all">
    <img src="images/drag_handle.png" class="handle" />
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
        <br />
        Last run at <span id="run_time">?</span> UTC using model <span id="gfs_timestamp">?</span>
        <br />
        <span class="ui-corner-all control_buttons">
            <a class="control_button" id="panto">Pan To</a> | 
            <a class="control_button" id="dlcsv">CSV</a> | 
            <a class="control_button" id="dlkml">KML</a>
        </span>
    </span>
    <br />
    <span class="ui-corner-all control_buttons">
        <a class="control_button" id="showHideDebug">Show Debug</a> | 
        <a class="control_button" id="showHideForm">Hide Launch Card</a> |
        <a class="control_button" id="about_window_show">About</a>
    </span>
</div>

<!-- save location -->
<div id="location_save" class="box ui-corner-all">
    <h1>Save Launch Location</h1><br />
    <form name="location_save_form" id="location_save_form">
    <table name="req_table" id="req_table">
    <tr>
    <td>Latitude: </td><td><input type="text" name="req_lat" id="req_lat" size="10"></td>
    </tr><tr>
    <td>Longitude: </td><td><input type="text" name="req_lon" id="req_lon" size="10"></td>
    </tr><tr>
    <td>Altitude: </td><td><input type="text" name="req_alt" id="req_alt" size="10"></td>
    </tr><tr>
    <td>Site Name: </td><td><input type="text" name="req_name" id="req_name" size="10"></td>
    </tr><tr>
    <td></td><td><input type="button" value="Save" name="submit" id="req_sub_btn"></td>
    </tr>
    </table>
    </form><br />
    <a id="req_close">Close this window</a>
</div>

<!-- cookie save location -->
<div id="location_save_local" class="box ui-corner-all">
    <img src="images/drag_handle.png" class="handle" />
    <b>Saved Locations</b><br />
    <span id="locations_table">?</span>
    <br />
    <a id="locations_close">Close this window</a>
</div>

<!-- the about window -->
<div id="about_window">
    <b>Cambridge University Spaceflight Landing Predictor (<a href="http://github.com/jonsowman/cusf-standalone-predictor" target="_blank">github</a>)</b>
    <br /><br />
    A tool to predict the flight path and landing location of latex sounding balloons.
    <br /><br />
    Written by <a href="http://github.com/jonsowman" target="_blank">Jon Sowman</a> and <a href="http://github.com/randomskk" target="_blank">Adam Greig</a> for <a href="http://www.cuspaceflight.co.uk" target="_blank">CUSF</a>.
    Credit also to <a href="http://github.com/rjw57" target="_blank">Rich Wareham</a> for work on the predictor. Some parts of code taken from old landing prediction software, credit to Rob Anderson, Fergus Noble and Ed Moore.
    <br /><br />
    No guarantee is given for the accuracy, precision or reliability of the data produced by this software, and you use it entirely at your own risk. For more information, see #highaltitude on irc.freenode.net.
</div>

<!-- launch card form -->
<div id="input_form" class="box ui-corner-all"> 
<img class="handle" src="images/drag_handle.png" />
<form action="" id="modelForm" name="modelForm">
<table>
	<tr>
                <td>
                    Launch Site:
                    <span class="control_buttons ui-corner-all">
                    <a id="cookieLocations" class="control_button">Custom</a>
                    </span>
                </td>
		<td>
			<select id="site" name="launchsite">
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
    <td>
        <span class="control_buttons ui-corner-all">
        <a class="control_button" id="setWithClick">Set With Map</a>
        </span>
    </td>
    <td>
        <span class="control_buttons ui-corner-all">
        <a class="control_button" id="req_open">Save Location</a>
        </span>
    </td>
    </tr>
    <tr>
        <td>Launch altitude (m):</td>
        <td><input id="initial_alt" type="text" name="initial_alt" value="0"></td>
    </tr>
	<tr>
		<td>Launch Time (UTC):</td>
		<td>
                        <input id="hour" type="text" name="hour" value="<?php
                    echo date("H", $time);
                    ?>" maxlength="2" size="2"> :
                        <input id="min" type="text" name="min" value="<?php
                    echo date("i", $time);
                    ?>" maxlength="2" size="2">
			<input id="sec" type="hidden" name="second" value="0"></td></tr>
			<tr><td>Launch Date:</td><td>
                        <input id="day" type="text" name="day" value="<?php
                    echo date("d", $time);
                    ?>" maxlength="2" size="2">
                        <select id="month" name="month"><?php
                    // php enumeration
                    for($i=1;$i<=12;$i++) {
                        echo "<option value=\"" . $i . "\"";
                        if ($i == date("n", $time) ) {
                            echo " selected=\"selected\"";
                        }
                        echo ">".date("M", mktime(0,0,0,$i,11,1978))."</option>\n";
                    }

                    ?></select>
                        <input id="year" type="text" name="year" value="<?php
                    echo date("Y", $time);
                    ?>" maxlength="4" size="4">
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
		<td><input type="submit" name="submit" id="run_pred_btn" value="Run Prediction"></td>
	</tr>
</table>
</form>
</div>

</body>
</html>
