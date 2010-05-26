<?php
	$google_maps_key = "ABQIAAAAzpAeP4iTRyyvc3_y95bQZBSKwkMTdHp8HFczqP8NHp8p-gzf6hS3D2nn-v4cH1tY-5dVl1LLI46Lng";
	$map_width = "100%";
	$map_height = "70%";
	
	function footer() {
		echo "</body></html>";
	}
?>
<html>

<head>
	<title>CU Spaceflight - Landing Prediction (Beta)</title> 
	<link rel="stylesheet" href="style.css">
	<script src="http://maps.google.com/maps?file=api&v=2&key=<?php echo $google_maps_key ?>" type="text/javascript"></script>
	<script>
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
	</script>
</head>

<body>
<h1>CU Spaceflight - Landing Prediction</h1><hr>

<?php
	// get time of last GRIB data - this is a bit of a hack
	//$fp = fopen("valid_model_run.txt", "r");
	//echo "<p>Using GRIB data from the " . fread($fp, 4) . "/" . fread($fp, 2) . "/" . fread($fp, 2) . " " . fread($fp, 2) . ":00 GMT model</p>";

	if (!isset($_POST['submit'])) {
		// form not submitted, so display the form
		$time = localtime(time(), true);
?>

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
			<input type="hidden" name="sec" value="0">
			 - 
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
		<td></td>
		<td><input type="submit" name="submit" value="Run Prediction!"></td>
	</tr>
</table>


<p>NOTE: Now updated to work world wide and up to 120 hours in the future, although its still very buggy and gives errors for some launch locations, we will be working to fix this soon.</p>


<?php

	} else {
		// form has been submitted so make and display the prediction
		
		//horrible bodge, get rid of once cron job set up
		if($_POST['lat']==-789)
		{
		echo "<p>Running auto forecast - will take several minutes</p>";
		$output = shell_exec("./auto_prediction");
		echo "<p>" . nl2br($output) . "</p>";
		echo "<p><a href='churchill_forecasts.kml'>KML File</a></p>";

		footer();
		exit(1);
		}
		
		
		// use lock file as a mutex, this has potential for cockups
		if (file_exists("lock")) {
			echo "Server currently running a prediction, please try again in a few minutes or contact us on IRC (#highaltitude irc.freenode.net).";
			footer();
			exit(1);
		}

		$timestamp = mktime($_POST['hour'], $_POST['min'], $_POST['sec'], (int)$_POST['month'] + 1, $_POST['day'], (int)$_POST['year'] - 2000);
		//if ($timestamp < time() || $timestamp > (time() + 120*3600)) {
		if ($timestamp > (time() + 180*3600)) {
			echo "<p>Invalid launch time specified, time must be within 180 hours of the current time.</p>";
			echo "<p>Launch time: " . gmstrftime("%b %d %Y %H:%M", $timestamp) . "<br />Current time: " . gmstrftime("%b %d %Y %H:%M", time()) . "</p>";
			echo "<p>" . nl2br(print_r($_POST, true)) . "</p>";
			footer();
			exit(1);
		}
		
		if($_POST['initial_alt'] < -1000)
		{
      echo "<p>Invalid initial altitude specified, must be greater than -1000m.</p>";
      footer();
			exit(1);
			}
		

		// create lock file to prevent other users running the predictor at the same time
		$lockfile = fopen("lock", "w");
		fclose($lockfile);
		
		$initial_zoom = "7";
		$initial_lat = $_POST['lat'];
		$initial_lon = $_POST['lon'];
		
		if($_POST['float_time']>24*60*60)
		{
      echo "<p>Max float time allowed is 24 hours</p>";
      footer();
      exit(1);
      }

		$output = shell_exec("./one_off_prediction " . $_POST['lat'] . " " . $_POST['lon'] . " " . $_POST['initial_alt'] ." " . (float)$_POST['ascent'] . " " . $_POST['drag']*1.1045 . " "  . $_POST['burst'] . " " . $timestamp  . " " . $_POST['float_time']);
    
		if (substr_count($output, "ok") != 1) {
			echo "<h2>Landing prediction returned with a (possibly cryptic) error:</h2>";
			echo "<p>" . nl2br($output) . "</p>";
			footer();
			unlink("lock");
			exit(1);
		}
		
		$fp = fopen("valid_model_run", "r");
		$gribyear = fread($fp, 4);
		$gribmonth = fread($fp, 2);
		$gribday = fread($fp, 2);
		$gribhour = fread($fp, 2);
	  	echo "
		<table><tr><td>
		<p>Using GRIB data from the " . $gribyear . "/" . $gribmonth . "/" . $gribday . " " . $gribhour . ":00 GMT model</p>";
		
		$file = fopen("flight_path.csv", "r");
		while (($data = fgetcsv($file)) != FALSE) {
			//print_r($data);
			$gmapsdata .= ",new GLatLng(" . $data[1] . "," . $data[2] . ")\n";
			//print $data[1] . " " . $data[2] . " " . $data[3] . "<br />";
			$land_timestamp = (float)$data[0];
			$land_lat = $data[1];
			$land_lon = $data[2];
			if ((int)$data[3] > $maxalt) {
				$maxalt = (int)$data[3];
				$apogee_lat = $data[1];
				$apogee_lon = $data[2];
				$burst_timestamp = (float)$data[0];
			}
		}
		fclose($file);
		unlink("lock");

		$lat1 = deg2rad($initial_lat);
		$lat2 = deg2rad($land_lat);
		$lon1 = deg2rad($initial_lon);
		$lon2 = deg2rad($land_lon);
		//$dist = 2*asin(sqrt((sin(($lat1-$lat2)/2))^2 + cos($lat1)*cos($lat2)*(sin(($lon1-$lon2)/2))^2));
		$distkm = 6366.71 * acos(sin($lat1)*sin($lat2)+cos($lat1)*cos($lat2)*cos($lon1-$lon2));

		$map_lat = ((float)$_POST['lat'] + (float)$land_lat)/2;
		$map_lon = ((float)$_POST['lon'] + (float)$land_lon)/2;

		$launchdate = gmstrftime("%b %d %Y %H:%M", $timestamp);
		$launchtime = gmstrftime("%H:%M", $timestamp);
		$landdate = gmstrftime("%b %d %Y %H:%M", $land_timestamp);
		$landtime = gmstrftime("%H:%M", $land_timestamp);
		$bursttime = gmstrftime("%H:%M", $burst_timestamp);
		$duration = gmstrftime("%H:%M", $land_timestamp - $timestamp);
		$time_into_model = (int)(($burst_timestamp - mktime($gribhour, 0, 0, $gribmonth, $gribday, $gribyear)) / 3600);
		$time_into_model = $time_into_model - ($time_into_model % 3);
?>
<p>Launch: <?php if ($_POST["launchsite"] != "other") echo $_POST["launchsite"]; ?> <?php echo "<b>" . $initial_lat . ", " . $initial_lon . "</b> - " . $launchdate; ?> GMT<br />
Landing: <?php echo  "<b>" . $land_lat . ", " . $land_lon . "</b> - " . $landdate; ?> GMT<br />
Duration: <?php echo $duration; ?><br />
Distance: <?php echo (int)$distkm . " km (" . (int)($distkm * 0.62137) . " miles)"; ?></p>
<p><a href="flight_path.kml">KML File</a></p>
</td>
<td>
	<div id="coords"></div>
	<form name="wind_overlay">
	<p>
		GFS wind speed data overlay (knots): <input type="checkbox" name="wind_overlay_enabled" onChange="update_wind_overlay()"> 
		<br />mBar (approx. altitude): 
		<select name="mbar" onChange="update_wind_overlay()">
			<option value="200">200 (12km)</option>
			<option value="300" selected>300 (9km)</option>
			<option value="500">500 (5.5km)</option>
			<option value="700">700 (3km)</option>
			<option value="850">850 (1.5km)</option>			
		</select>
	</p></form>
</td></tr></table>

<div id="map" style="width: <?php echo $map_width ?>; height: <?php echo $map_height ?>">
</div>

<script type="text/javascript">
//<![CDATA[
var map = new GMap2(document.getElementById("map"));
map.addControl(new GLargeMapControl());
map.addControl(new GMapTypeControl());
map.addControl(new GScaleControl());
//map.setUIToDefault();

map.setCenter(new GLatLng(<?php echo $map_lat ?>, <?php echo $map_lon ?>), <?php echo $initial_zoom ?>);

var baseIcon = new GIcon();
baseIcon.iconSize = new GSize(20,20);
baseIcon.iconAnchor = new GPoint(10,10);
baseIcon.infoWindowAnchor = new GPoint(10,10);

var launchIcon = new GIcon(baseIcon);
launchIcon.image = "icons/arrow.png";
markerOptions = { icon:launchIcon };
var launchMarker = new GMarker(new GLatLng(<?php echo $initial_lat . "," . $initial_lon ?>), markerOptions);
GEvent.addListener(launchMarker, "click", function() {
	launchMarker.openInfoWindowHtml("<b>Launch Site</b><br /><?php if ($_POST["launchsite"] != "other") echo $_POST["launchsite"] . "<br />"; ?><?php echo $initial_lat . ", " . $initial_lon . " - " . $launchtime ?>");
});
map.addOverlay(launchMarker)

var landIcon = new GIcon(baseIcon);
landIcon.image = "icons/target-red.png";
markerOptions = { icon:landIcon };
var landMarker = new GMarker(new GLatLng(<?php echo $land_lat . "," . $land_lon ?>), markerOptions);
GEvent.addListener(landMarker, "click", function() {
	landMarker.openInfoWindowHtml("<b>Predicted Landing</b><br /><?php echo $land_lat . ", " . $land_lon . " - " . $landtime ?>");
});
map.addOverlay(landMarker)

function drawCircle(center, radius, color, width, complexity) {
    var points = [];
    var radians = Math.PI / 180;
    var longitudeOffset = radius / (Math.cos(center.y * radians) *
111325);
    var latitudeOffset = radius / 111325;
    for (var i = 0; i < 360; i += complexity) {
        var point = new GPoint(center.x + (longitudeOffset * Math.cos(i
* radians)), center.y + (latitudeOffset * Math.sin(i * radians)));
        points.push(point);
    }
    points.push(points[0]);// close the circle
    var polygon = new GPolygon(points, true, color,0.25, true);

    map.addOverlay(polygon);
} 

drawCircle(new GLatLng(<?php echo $land_lat . "," . $land_lon ?>),15000,"#ffff00",3,10);
drawCircle(new GLatLng(<?php echo $land_lat . "," . $land_lon ?>),10000,"#ff0000",3,10);
drawCircle(new GLatLng(<?php echo $land_lat . "," . $land_lon ?>),5000,"#ff0000",3,10);

GEvent.addDomListener(map,'mousemove', 
function(point){
	document.getElementById("coords").innerHTML = 'Coords: ' + point.lat().toFixed(4) + ', ' + point.lng().toFixed(4) + '<br />Range from launch site ' + (point.distanceFrom(new GLatLng(<?php echo $initial_lat . ',' . $initial_lon ?>))/1000).toFixed(2) + 'km, landing site ' + (point.distanceFrom(new GLatLng(<?php echo $land_lat . ',' . $land_lon ?>))/1000).toFixed(2) + 'km';
	//window.status='Wgs84 - '+point.toUrlValue();
});

var apogeeIcon = new GIcon(baseIcon);
apogeeIcon.image = "icons/balloon.png";
markerOptions = { icon:apogeeIcon };
var apogeeMarker = new GMarker(new GLatLng(<?php echo $apogee_lat . "," . $apogee_lon ?>), markerOptions);
GEvent.addListener(apogeeMarker, "click", function() {
	apogeeMarker.openInfoWindowHtml("<b>Balloon Burst</b><br /><?php echo $_POST["burst"] . " km<br />" . $apogee_lat . ", " . $apogee_lon . " - " . $bursttime ?>");
});
map.addOverlay(apogeeMarker)

//var pointSW = new GLatLng(48,-4.5);
//var pointNE = new GLatLng(57,4.5);
//var groundOverlay = new GGroundOverlay(
//   "http://www.srcf.ucam.org/~cuspaceflight/predict/graph-out.png", 
//   new GLatLngBounds(pointSW, pointNE));

var windOverlay = new GGroundOverlay("", map.getBounds());

var hour = <?php echo $time_into_model; ?>;
var modelrun = "<?php echo $gribyear . $gribmonth . $gribday . $gribhour; ?>";

function update_wind_overlay() {
	var bounds = map.getBounds();
	var size = map.getSize();

	var imgurl = "http://modelmaps.wunderground.com/php/run.php?model=GFS&script=" 	
		+ document.wind_overlay.mbar.options[document.wind_overlay.mbar.selectedIndex].value +
		"&hour="+hour+"&modelrun="+modelrun+"&maxlat=" 
		+ bounds.getNorthEast().lat() + 
		"&maxlon=" + bounds.getNorthEast().lng() + 
		"&minlat=" + bounds.getSouthWest().lat() + 
		"&minlon=" + bounds.getSouthWest().lng() + 
		"&width=" + size.width + "&height=" + size.height;
		
	window.status = imgurl;

	map.removeOverlay(windOverlay);
	if (document.wind_overlay.wind_overlay_enabled.checked) {
		windOverlay = new GGroundOverlay(imgurl, bounds);
		map.addOverlay(windOverlay);
	}
}

GEvent.addListener(map, "moveend", update_wind_overlay );

update_wind_overlay();

var polyline = new GPolyline([
<?php echo $gmapsdata; ?>
],"#ff0000", 2, 1);
map.addOverlay(polyline);

<?php
		if (isset($_POST['vectors'])) {
			$windfile = fopen("output1.csv", "r");
			$data = fgetcsv($windfile);
			$startlat = $data[3];
			$startlon = $data[4];
			$endlat = $data[5];
			$endlon = $data[6];
			$data = fgetcsv($windfile);
			$xs = fgetcsv($windfile);
			$ys = fgetcsv($windfile);
			for ($x = 0; $x < ($endlat - $startlat)*2; $x++) {
				for ($y = 0; $y < ($endlon - $startlon)*2; $y++) {
					echo "var windline = new GPolyline([new GLatLng(";
					echo ($startlat + $x*0.5) . "," . ($startlon + $y*0.5) . "), new GLatLng(";
					echo $xs[$x + ($endlat - $startlat)*2*$y + 2]*0.01 + ($startlat + $x*0.5) . ",";
					echo $ys[$x + ($endlat - $startlat)*2*$y + 2]*0.01 + ($startlon + $y*0.5). ")";
					echo "],\"#000000\",2,1);\nmap.addOverlay(windline);\n";
				}
			}
		}
?>

//]]>
</script>

<?php
	}
?>
</body>
</html>

