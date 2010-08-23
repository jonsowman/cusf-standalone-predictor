<html>
<head>
<title>CUSF Landing Predictor V2.0 Admin</title>
<link href="css/pred.css" type="text/css" rel="stylesheet" />
<style>
body { background-color: #fff; }
</style>
</head><body>
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

$i=1;
echo '<table border=1>';
echo '<tr><td>Index</td><td>UUID</td><td>View</td></tr>';
foreach( $uuid_list as $uuid ) {
    echo '<tr><td>' . $i . '</td><td>' . $uuid . '</td><td>';
    echo '<a href="./#!/uuid=' . $uuid . '">View</a>';
    echo '</td></tr>';
    $i++;
}
echo '</table>';

?>
</body></html>
