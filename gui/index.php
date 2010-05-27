<?php
$time = localtime(time(), true);
?>

<html>
    <head>
        <title>GUI test</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<link href="css/pred.css" type="text/css" rel="stylesheet">
<script src="js/jquery.js" type="text/javascript"></script>
<script type="text/javascript">

// launch site dropdown switcher
function UpdateLaunchSite(id) {
        txtLat = document.getElementById("lat");
        txtLon = document.getElementById("lon");
        switch (id) {
                case 0: // Churchill
                        txtLat.value = "52.2135";
                        txtLon.value = "0.0964";
                        break;
                case 1: // EARS
                        txtLat.value = "52.2511";
                        txtLon.value = "-0.0927";
                        break;
                case 2: // Glenrothes (SpeedEvil)
                        txtLat.value = "56.13";
                        txtLon.value = "-3.06";
                        break;
                case 3: // Bujaraloz, Monegros (gerard)
                        txtLat.value = "41.495773";
                        txtLon.value = "-0.157968";
                        break;
                case 4: // Adelaide (Juxta)
                        txtLat.value = "-34.9499";
                        txtLon.value = "138.5194";

        }
}

function SetSiteOther() {
        optOther = document.getElementById("other");
        //cmbSite = document.getElementById("site");
        //cmbSite.selectedIndex = 1;
        optOther.selected = true;
}

var map;
var launch_img = "images/marker-sm-red.png";
var land_img = "images/marker-sm-red.png";
var burst_img = "images/pop-marker.png";

function initialize() {
var latlng = new google.maps.LatLng(52, 0);
var myOptions = {
  zoom: 8,
  center: latlng,
  mapTypeId: google.maps.MapTypeId.ROADMAP
};
map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
parseCSV("new.csv");
}

function parseCSV(csv_name) {
    $.get(csv_name, null, function(data, textStatus) {
        var lines = data.split('\n');
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

    });
}

</script>
</head>
<body onload="initialize()">
  <div id="map_canvas" style="width:100%; height:100%"></div>
<div id="scenario_template" class="box">
<h1>Scenario Template</h1>
hello world  :)
<br>
this is a happy box
</div>

<div id="input_form" class="box"> 
<form action="index.php" method="POST">
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
        <td><input type="text" name="initial_alt" value="0"></td>
    </tr>
	<tr>
		<td>Launch Time:</td>
		<td>
			<input type="text" name="hour" value="<?php printf("%02d", $time['tm_hour']+1); ?>" maxlength="2" size="2"> :
			<input type="text" name="min" value="<?php printf("%02d", $time['tm_min']); ?>" maxlength="2" size="2">
			<input type="hidden" name="sec" value="0"></td></tr>
			<tr><td>Launch Date:</td><td>
			<input type="text" name="day" value="<?php echo $time['tm_mday']; ?>" maxlength="2" size="2">
			<select name="month">
				<option value="0"<?php if ($time['tm_mon'] == 0) echo " selected"; ?>>Jan</option>
				<option value="1"<?php if ($time['tm_mon'] == 1) echo " selected"; ?>>Feb</option>
				<option value="2"<?php if ($time['tm_mon'] == 2) echo " selected"; ?>>Mar</option>
				<option value="3"<?php if ($time['tm_mon'] == 3) echo " selected"; ?>>Apr</option>
				<option value="4"<?php if ($time['tm_mon'] == 4) echo " selected"; ?>>May</option>
				<option value="5"<?php if ($time['tm_mon'] == 5) echo " selected"; ?>>Jun</option>
				<option value="6"<?php if ($time['tm_mon'] == 6) echo " selected"; ?>>Jul</option>
				<option value="7"<?php if ($time['tm_mon'] == 7) echo " selected"; ?>>Aug</option>
				<option value="8"<?php if ($time['tm_mon'] == 8) echo " selected"; ?>>Sep</option>
				<option value="9"<?php if ($time['tm_mon'] == 9) echo " selected"; ?>>Oct</option>
				<option value="10"<?php if ($time['tm_mon'] == 10) echo " selected"; ?>>Nov</option>
				<option value="11"<?php if ($time['tm_mon'] == 11) echo " selected"; ?>>Dec</option>
			</select>
			<input type="text" name="year" value="<?php echo $time['tm_year']+1900; ?>" maxlength="4" size="4">
		</td>
    <tr>
        <td>Ascent Rate (m/s):</td>
        <td><input type="text" name="ascent" value="3"></td>
    </tr>
    <tr>
        <td>Descent Rate (sea level m/s):</td>
        <td><input type="text" name="drag" value="5"></td>
    </tr>
    <tr>
        <td>Burst Altitude (m):</td>
        <td><input type="text" name="burst" value="30000"></td>
    </tr>
    <tr>
        <td>Float time at apogee (s):</td>
        <td><input type="text" name="float_time" value="0"></td>
    </tr>
    <tr>
        <td>Landing prediction software: <td>
        <select name="software">
            <option value="grib" selected="selected">GRIB (fast, less accurate)</option>
            <option value="dap">GFS/DAP (slow, more accurate)</option>
        </select>
	<tr>
		<td></td>
		<td><input type="submit" name="submit" value="Run Prediction!"></td>
	</tr>
</table>
</form>
</div>


</body>
</html>
