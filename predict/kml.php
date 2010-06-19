<?
require_once("includes/config.inc.php");
require_once("includes/functions.inc.php");
if ( !isset($_GET['uuid']) || $_GET['uuid'] == "0" ) die("Invalid UUID");
$uuid = $_GET['uuid'];
$flight_csv = $c_preds_path . $uuid . "/" . $c_flight_csv;
if ( !file_exists( $flight_csv ) ) die("No prediction data for UUID");

$kml = array('<?xml version="1.0" encoding="UTF-8"?>');
$kml[] = '<kml xmlns="http://www.opengis.net/kml/2.2">';
$kml[] = '<Document>';
$kml[] = '<name>Flight Path</name>';
$kml[] = '<description>xxxx</description>';
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
$kml[] = '<description>xxx</description>';
$kml[] = '<styleUrl>#yellowPoly</styleUrl>';
$kml[] = '<LineString>';
$kml[] = '<extrude>1</extrude>';
$kml[] = '<tesselate>1</tesselate>';
$kml[] = '<altitudeMode>absolute</altitudeMode>';
$kml[] = '<coordinates>';

// put stuff here

$fh = fopen($flight_csv, "r") or die("Could not open file");
while (($data = fgetcsv($fh)) !== FALSE) {
    $num = count($data);
    if ( $num < 4 ) die("Invalid XML");
    $kml[] = $data[2] . "," . $data[1] . "," . $data[3];
}

$kml[] = '</coordinates>';
$kml[] = '</LineString></Placemark></Document></kml>';

$kmlOut = join("\n", $kml);
header("Content-type: application/vnd.google-earth.kml+xml");
header("Content-Disposition: attachment; filename=".$uuid.".kml");
echo $kmlOut;

?>
