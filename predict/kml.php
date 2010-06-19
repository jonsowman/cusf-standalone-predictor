<?
require_once("includes/config.inc.php");
require_once("includes/functions.inc.php");
if ( !isset($_GET['uuid']) || $_GET['uuid'] == "0" ) die("Invalid UUID");
$uuid = $_GET['uuid'];
$flight_csv = $c_preds_path . $uuid . "/" . $c_flight_csv;
$scenario_file = $c_preds_path . $uuid . "/" . $c_scenario_file;
if ( !file_exists( $flight_csv ) || !file_exists( $scenario_file ) ) die("No prediction data for UUID");

// make the prediction moel
$scenario = parse_ini_file($scenario_file);

$kml = array('<?xml version="1.0" encoding="UTF-8"?>');
$kml[] = '<kml xmlns="http://www.opengis.net/kml/2.2">';
$kml[] = '<Document>';
$kml[] = '<name>Flight Path</name>';
$kml[] = '<description><![CDATA[Flight data for flight ' . $uuid . ' <br>Site: ' . $scenario['latitude'] . ', '
    . $scenario['longitude'] . ' at ' . $scenario['hour'] . ':' . $scenario['minute']
    . ' on ' . $scenario['day'] . '/' . $scenario['month'] . '/' . $scenario['year']
    . ']]></description>';
$kml[] = '<Style id="yellowPoly">';
$kml[] = '<LineStyle>';
$kml[] = '<color>7f00ffff</color>';
$kml[] = '<width>4</width>';
$kml[] = '</LineStyle>';
$kml[] = '<PolyStyle>';
$kml[] = '<color>7f00ff00</color>';
$kml[] = '</PolyStyle>';
$kml[] = '</Style>';

$kml[] = '<Placemark>';
$kml[] = '<name>Flight path</name>';
$kml[] = '<description>Ascent rate: ' . $scenario['ascent-rate'] . 'm/s, descent rate: '
    . $scenario['descent-rate'] . 'm/s with burst at ' . $scenario['burst-altitude']
    . 'm.' . '</description>';
$kml[] = '<styleUrl>#yellowPoly</styleUrl>';
$kml[] = '<LineString>';
$kml[] = '<extrude>1</extrude>';
$kml[] = '<tesselate>1</tesselate>';
$kml[] = '<altitudeMode>absolute</altitudeMode>';
$kml[] = '<coordinates>';

// now print the lat/long/alt from the CSV
// GE is retarded and wants its KML in the format LON,LAT,ALT - wtf

$launch_lat;
$launch_lon;
$launch_time;

$burst_lat;
$burst_lon;
$burst_time;

$land_lat;
$land_lon;
$land_time;

$max_alt = -10;

$fh = fopen($flight_csv, "r") or die("Could not open file");
$i=0;
while (($data = fgetcsv($fh)) !== FALSE) {
    $num = count($data);
    if ( $num < 4 ) die("Invalid XML");
    $time = $data[0];
    $lat = $data[1];
    $lon = $data[2];
    $alt = $data[3];

    if ( $i == 0 ) {
        $launch_lat = $lat;
        $launch_lon = $lon;
        $launch_time = $time;
    }

    // see if the current point is above the last
    if ( $alt > $max_alt ) {
        $max_alt = $alt;
        $burst_lat = $lat;
        $burst_lon = $lon;
        $burst_time = $time;
    }

    // update this on every interation
    $land_lat = $lat;
    $land_lon = $lon;
    $land_time = $time;

    // add the formatted line to the KML array
    $kml[] = $lon . "," . $lat . "," . $alt;
    $i++;
}

$kml[] = '</coordinates>';
$kml[] = '</LineString></Placemark>';

// Make the launch marker
$kml[] = '<Placemark>';
$kml[] = '<name>Balloon Launch</name>';
$kml[] = '<description>Balloon launch at ' . $launch_lat . ', ' . $launch_lon . ' at '
    . date("H:i d/m/Y", $launch_time) . '.</description>';
$kml[] = '<Point><coordinates>' . $launch_lon . ',' . $launch_lat . ',0' . '</coordinates></Point>';
$kml[] = '</Placemark>';

// Make the burst marker
$kml[] = '<Placemark>';
$kml[] = '<name>Balloon Burst</name>';
$kml[] = '<description>Balloon burst at ' . $burst_lat . ', ' . $burst_lon . ' at '
    . date("H:i d/m/Y", $burst_time) . ' with altitude ' . $max_alt . 'm.</description>';
$kml[] = '<Point><coordinates>' . $burst_lon . ',' . $burst_lat . ',' . $max_alt . '</coordinates></Point>';
$kml[] = '</Placemark>';

// Make the land marker
$kml[] = '<Placemark>';
$kml[] = '<name>Predicted Balloon Landing</name>';
$kml[] = '<description>Balloon landing at ' . $land_lat . ', ' . $land_lon . ' at '
    . date("H:i d/m/Y", $land_time) . '.</description>';
$kml[] = '<Point><coordinates>' . $land_lon . ',' . $land_lat . ',0' . '</coordinates></Point>';
$kml[] = '</Placemark>';

$kml[] = '</Document></kml>';

$kmlOut = join("\n", $kml);
header("Content-type: application/vnd.google-earth.kml+xml");
header("Content-Disposition: attachment; filename=".$uuid.".kml");
echo $kmlOut;

?>
