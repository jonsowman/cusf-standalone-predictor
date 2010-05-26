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
</head>

<body>
<h1>CU Spaceflight - Landing Prediction</h1><hr>

<?php

		
		$file = fopen("new.csv", "r");
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

                $initial_zoom = "7";
                $initial_lat = $_POST['lat'];
		$initial_lon = $_POST['lon'];

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

//]]>
</script>

</body>
</html>

