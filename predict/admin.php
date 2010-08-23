<html>
<head>
<title>CUSF Landing Predictor V2.0 Admin</title>
<link href="css/pred.css" type="text/css" rel="stylesheet" />
<style>
body { background-color: #fff; }
</style>
</head><body>
<h1>Recent Predictions</h1>
<?php

require_once("includes/config.inc.php");
require_once("includes/functions.inc.php");

if( !isset($_GET['limit']) || $_GET['limit'] < 1 || $_GET['limit'] > 14 ) {
    $limit = 1;
} else {
    $limit = $_GET['limit'];
}
$threshold = time() - $limit*60*60*24;

$dirs = scandir(PREDS_PATH);
foreach( $dirs as $dir ) {
    if ( is_dir(PREDS_PATH . $dir) && $dir != '.' && $dir != '..' && filemtime(PREDS_PATH.$dir) > $threshold )
        $uuid_list[] = $dir;
}

echo '<h3>' . $limit . ' days old or newer</h3>';

$i=1;
echo '<table border=1>';
echo '<tr style="font-weight:bold; text-align:center"><td>Index</td><td>UUID</td>'
    . '<td>Lat</td><td>Lon</td><td>Asc</td><td>Desc</td><td>Burst</td><td>View</td></tr>';
foreach( $uuid_list as $uuid ) {
    $scenario = parse_ini_file( PREDS_PATH . $uuid . "/" . SCENARIO_FILE );
    echo '<tr><td>' . $i . '</td><td style="font-family:courier">' . $uuid . '</td>';
    echo '<td>' . $scenario['latitude'] . '</td>';
    echo '<td>' . $scenario['longitude'] . '</td>';
    echo '<td>' . $scenario['ascent-rate'] . '</td>';
    echo '<td>' . $scenario['descent-rate'] . '</td>';
    echo '<td>' . $scenario['burst-altitude'] . '</td>';
    echo '<td><a href="./#!/uuid=' . $uuid . '">View</a>';
    echo '</td></tr>';
    $i++;
}
echo '</table>';

?>
</body></html>
