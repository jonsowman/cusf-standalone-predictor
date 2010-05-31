<?php
// get the time for pre-populating the form
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
                appendDebug("The server rejected the submitted form data");
            } else {
                appendDebug("The server accepted the form data");
                running_uuid = data_split[1];
                appendDebug("The server gave us uuid:<br>" + running_uuid);
                appendDebug("Starting to poll for progress JSON");
                handlePred(running_uuid);
            }
        }
    });
    //$("#input_form").draggable({containment: '#map_canvas'});
    // if ( running_uuid != 0 ) handlePred(running_uuid);
    $("#debuginfo").hide();
    $("#showHideDebug").click(function() {
        // $("#debuginfo").show("slide", { direction: "down" }, 500);
        toggleDebugWindow();
    });
}



</script>
</head>
<body onload="initialize()" bgcolor="#000000">

<div id="map_canvas" style="width:100%; height:100%"></div>

<div id="scenario_template" class="box">
<h1>Debug Window - 
<a href='#'><span id="showHideDebug">Show</span></a></span>
</h1>
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
