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

$stats = new StatsD();
$stats->counting('habhub.predictor.php.hits');

// Get the time for pre-populating the form
$time = time() + 3600;
?>
<html>
<head>
<title>CUSF Landing Predictor 2.0</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<script type="text/javascript" src="http://www.google.com/jsapi?key=<?php echo GMAPS_API_KEY; ?>">
</script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<link href="css/pred.css" type="text/css" rel="stylesheet" />
<link href="css/calc.css" type="text/css" rel="stylesheet" />
<link rel="stylesheet" href="css/tipsy.css" type="text/css" />
<link href="css/cupertino/jquery-ui-1.8.1.custom.css" type="text/css" rel="stylesheet">
<script type="text/javascript">
// Load jquery and jqueryui before loading jquery.form.js later
google.load("jquery", "1.4.2");
google.load("jqueryui", "1.8.1");
</script>
<script src="js/jquery/jquery.form.js" type="text/javascript"></script>
<script src="js/jquery/jquery.jookie.js" type="text/javascript"></script>
<script src="js/jquery/jquery.tipsy.js" type="text/javascript"></script>
<script src="js/utils/date.jsport.js" type="text/javascript"></script>

<script src="js/pred/pred-config.js" type="text/javascript"></script>
<script src="js/pred/pred-ui.js" type="text/javascript"></script>
<script src="js/pred/pred-cookie.js" type="text/javascript"></script>
<script src="js/pred/pred-map.js" type="text/javascript"></script>
<script src="js/pred/pred-event.js" type="text/javascript"></script>
<script src="js/pred/pred.js" type="text/javascript"></script>
<script src="js/calc/calc.js" type="text/javascript"></script>

</head>
<body>

<!-- Map canvas -->
<div id="map_canvas"></div>

<!-- Debug window -->
<div id="scenario_template" class="box ui-corner-all">
<h1>Debug Window</h1>
<span id="debuginfo">No Messages</span>
</div>

<!-- Prediction progress window -->
<div id="status_message" class="box ui-corner-all">
    <div id="prediction_progress"></div>
    <div id="prediction_percent"></div>
    <br>
    <span id="prediction_status"></span><br>
    <a><span id="showHideDebug_status">Toggle Debug</span></a>
</div>

<!-- Error window -->
<div id="error_window" class="box ui-corner-all">
    <span id="error_message">Nothing here!</span>
    <br /><br />
    <a id="closeErrorWindow">Close</a>
</div>

<!-- Scenario information -->
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

<!-- Save location to cookie -->
<div id="location_save" class="box ui-corner-all">
    <img src="images/drag_handle.png" class="handle" />
    <h1>Save Launch Location</h1><br />
    <form name="location_save_form" id="location_save_form">
    <table name="req_table" id="req_table">
    <tr>
    <td>Latitude: </td><td><input type="text" name="req_lat" id="req_lat" size="15"></td>
    </tr><tr>
    <td>Longitude: </td><td><input type="text" name="req_lon" id="req_lon" size="15"></td>
    </tr><tr>
    <td>Altitude: </td><td><input type="text" name="req_alt" id="req_alt" size="15"></td>
    </tr><tr>
    <td>Site Name: </td><td><input type="text" name="req_name" id="req_name" size="15"></td>
    </tr><tr>
    <td></td><td><input type="button" value="Save" name="submit" id="req_sub_btn"></td>
    </tr>
    </table>
    </form><br />
    <a id="req_close">Close this window</a>
</div>

<!-- View saved locations -->
<div id="location_save_local" class="box ui-corner-all">
    <img src="images/drag_handle.png" class="handle" />
    <b>Saved Locations</b><br />
    <span id="locations_table">?</span>
    <br />
    <a id="locations_close">Close this window</a>
</div>

<!-- About window -->
<div id="about_window">
    <h3>Cambridge University Spaceflight Landing Predictor (<a href="http://github.com/jonsowman/cusf-standalone-predictor" target="_blank">github</a>)</h3>
    <p>A tool to predict the flight path and landing location of latex sounding balloons.</p>
    <p>Written by <a href="http://github.com/jonsowman" target="_blank">Jon Sowman</a> and <a href="http://github.com/adamgreig" target="_blank">Adam Greig</a> for <a href="http://www.cuspaceflight.co.uk" target="_blank">CUSF</a>.
    Credit also to <a href="http://github.com/rjw57" target="_blank">Rich Wareham</a> for work on the predictor. Some parts of code taken from old landing prediction software, credit to Rob Anderson, Fergus Noble and Ed Moore.</p>
    <p>Minor credit to Robert Harrison for integrating the NOTAM and airspace KML from <a href="http://www.notaminfo.com/" >www.notaminfo.com</a> with kind permission from author David Massie.</p>
    <p>No guarantee is given for the accuracy, precision or reliability of the data produced by this software, and you use it entirely at your own risk. For more information, see #highaltitude on irc.freenode.net.</p>
    <h3>Burst Calculator</h3>
        <p><a href="http://cusf.co.uk/calc">Burst Calculator Web Page</a></p>
    <h4>Usage</h4>
        <p>
            This calculator is designed to help you find how much gas to put into a high altitude balloon
            to achieve a desired burst altitude or ascent rate. You have to know the payload mass (including
            parachute) and balloon mass.
        </p>
        <p>
            Usage is very straightforward: enter the payload mass into the first box, select the balloon
            mass from the second box, and type in either a desired burst altitude in meters or ascent rate
            in meters per second in the relevent box. Pressing enter or clicking away from the box you are
            typing into will run the calculations, displaying the results in the box at the bottom.
        </p>
        <p>
            The 'Constants' box can be used to adjust constants used by the calculator. These include
            selecting a type of gas (or entering a custom gas density), adjusting modelling parameters
            or entering a custom burst diameter or drag coefficient for your balloon. Click the tick box
            next to the burst diameter or drag coefficient boxes to use a custom value, otherwise the
            appropriate value for your selected balloon will be used.
        </p>
    <h4>About</h4>
        <p>
            Written by Adam Greig for CUSF in March 2010. Maths derived from `burst1a` spreadsheet by
            Steve Randall. Balloon information from Kaymont Totex sounding balloon data.
        </p>
        <p>
            No guarantee is given for the accuracy of any data included or produced by this program, use
            at your own risk.
        </p>
        <p>
            Copyright 2010 Cambridge University Space Flight
        </p>

</div>

<!-- Burst calculator window -->
<div id="burst-calc-wrapper" class="box ui-corner-all">
    <img src="images/drag_handle.png" class="handle" />
    <div id="burst-calc">
        <b>Burst Calculator</b>
        <br>
        <table id="input_table">
            <tr class="input_row">
                <td class="input_label" colspan="2">Payload Mass (g)</td>
                <td class="input_instruction" rowspan="3">AND</td>

                <td class="input_label" colspan="2">Balloon Mass (g)</td>
            </tr>
            <tr class="input_row">
                <td colspan="2">
                    <input type="text" id="mp" class="input_field" value="1500" 
                        tabindex="1"/>
                </td>
                <td colspan="2">
                    <select class="input_field" id="mb" tabindex="2"> 
                        <option value="k200">Kaymont - 200</option>
                        <option value="k300">Kaymont - 300</option>
                        <option value="k350">Kaymont - 350</option>
                        <option value="k450">Kaymont - 450</option>
                        <option value="k500">Kaymont - 500</option>
                        <option value="k600">Kaymont - 600</option>
                        <option value="k700">Kaymont - 700</option>
                        <option value="k800">Kaymont - 800</option>
                        <option value="k1000" selected="selected">Kaymont - 1000</option>
                        <option value="k1200">Kaymont - 1200</option>
                        <option value="k1500">Kaymont - 1500</option>
                        <option value="k2000">Kaymont - 2000</option>
                        <option value="k3000">Kaymont - 3000</option>
                        <option value="h200">Hwoyee - 200</option>
                        <option value="h300">Hwoyee - 300</option>
                        <option value="h350">Hwoyee - 350</option>
                        <option value="h450">Hwoyee - 400</option>
                        <option value="h500">Hwoyee - 500</option>
                        <option value="h600">Hwoyee - 600</option>
                        <option value="h700">Hwoyee - 750</option>
                        <option value="h800">Hwoyee - 800</option>
                        <option value="h800">Hwoyee - 950</option>
                        <option value="h1000">Hwoyee - 1000</option>
                        <option value="h1200">Hwoyee - 1200</option>
                        <option value="h1500">Hwoyee - 1500</option>
                        <option value="h1600">Hwoyee - 1600</option>
                        <option value="h2000">Hwoyee - 2000</option>
                        <option value="h3000">Hwoyee - 2000</option>
                        <option value="p1200">PAWAN - 1200</option>
                    </select>
                </td>
            </tr>
            <tr class="warning_row">
                <td colspan="2" id="mp_w">&nbsp;</td>
                <td colspan="2" id="mb_w">&nbsp;</td>
            </tr>
            <tr>
                <td class="input_instruction" colspan="5">THEN</td>
            </tr>
            <tr class="input_row">
                <td class="input_label" colspan="2">Target Burst Altitude (m)</td>

                <td class="input_instruction" rowspan="3">OR</td>
                <td class="input_label" colspan="2">Target Ascent Rate (m/s)</td>
            </tr>
            <tr class="input_row">
                <td colspan="2">
                    <input type="text" id="tba" class="input_field" value="33000" tabindex="3"/>
                </td>
                <td colspan="2">

                    <input type="text" id="tar" class="input_field" tabindex="4"/>
                </td>
            </tr>
            <tr class="warning_row">
                <td id="tba_w" colspan="2">&nbsp;</td>
                <td id="tar_w" colspan="2">&nbsp;</td>
            </tr>
            <tr class="output_row">
                <td class="output_label">Burst Altitude:</td>
                <td class="output_data"><span id="ba">33000</span> m</td>
                <td></td>
                <td class="output_label">Ascent Rate:</td>
                <td class="output_data"><span id="ar">2.33</span> m/s</td>

            </tr>
            <tr class="output_row">
                <td class="output_label">Time to Burst:</td>
                <td class="output_data"><span id="ttb">238</span> min</td>
                <td></td>
                <td class="output_label">Neck Lift:</td>
                <td class="output_data"><span id="nl">1733</span> g</td>

            </tr>
            <tr class="output_row">
                <td class="output_label">Launch Volume:</td>
                <td class="output_data"><span id="lv_m3">2.66</span> 
                    m<sup>3</sup></td>
                <td></td>
                <td class="output_data"><span id="lv_l">2660</span> L</td>
                <td class="output_data"><span id="lv_cf">93.9</span>
                     ft<sup>3</sup></td>

            </tr>
        </table>
        <br>
        <input type="button" id="burst-calc-advanced-show"
            name="burst-calc-advanced-show" value="Advanced">
        <input type="button" id="burst-calc-use" name="burst-calc-submit" 
            value="Use Values"/ >
        <input type="button" id="burst-calc-close" name="burst-calc-submit" 
            value="Close"/ >
    </div>

    <!-- these are the burst calc constants -->
    <div id="burst-calc-constants">
        <div class="constants_header">Constants</div><br />
        <div class="constants_warning">
            For advanced use only! You can probably leave these alone.
        </div><br />
        <label class="constant_label" for="gas">Gas</label><br />
        <select id="gas" class="constant_field">
            <option value="he">Helium</option>
            <option value="h">Hydrogen</option>

            <option value="ch4">Methane</option>
            <option value="custom">Custom</option>
        </select><br />
        <label class="constant_label" for="rho_g">Gas Density (kg/m<sup>3</sup>)</label><br />
        <input type="text" id="rho_g" value="0.1786" class="constant_field" size="9" disabled="disabled"/><br />
        <label class="constant_label" for="rho_a">Air Density (kg/m<sup>3</sup>)</label><br />

        <input type="text" id="rho_a" value="1.2050" class="constant_field" size="9"/><br />
        <label class="constant_label" for="adm">Air Density Model</label><br />
        <input type="text" id="adm" value="7238.3" class="constant_field" size="9"/><br />
        <label class="constant_label" for="ga">Gravitational<br />Acceleration (m/s<sup>2</sup>)</label><br />
        <input type="text" id="ga" value="9.80665" class="constant_field" size="9" /><br />
        <label class="constant_label" for="bd">Burst Diameter (m)</label><br />

        <input type="checkbox" id="bd_c" />
        <input type="text" id="bd" class="constant_field" size="9" disabled="disabled" value="7.86"/><br />
        <label class="constant_label" for="cd">Balloon Cd</label><br />
        <input type="checkbox" id="cd_c" />
        <input type="text" id="cd" class="constant_field" size="9" disabled="disabled" value="0.3"/><br />
        <br />
        <input type="button" id="burst-calc-advanced-hide" 
            name="burst-calc-advanced-hide" value="Back">
    </div>
</div>

<!-- Advanced NOTAM & Airspace Settings -->
<div id="notam-settings-wrapper" class="box ui-corner-all">
    <img src="images/drag_handle.png" class="handle" />
    <div id="notam-settings">
        <b>Advanced NOTAM & Airspace Settings</b>
        <p>
	<b>NB This is not yet functional !!!</b>
        <table id="input_table">
            <tr>
	    <td> 
<form name="userselection" action = "" method = "GET"  ><fieldset id="notamsFieldset" name="notams" ><legend>NOTAM details</legend><div id="notamsDiv" style="display: block"><input type="checkbox" name="warnings" id="warnings" onclick="setNOTAMs();" value="yes" checked="checked" />Show NOTAMs<br /></fieldset><fieldset id="airspaceFieldset"><legend>Airspace Details</legend><div id="airspaceDiv" style="display: block"><input type="checkbox" id = "defaultAirspace" name="default" value="yes" onclick="showStdAirspace();"/>Show Default airspace<br><input type="checkbox" id="clA" name="clA" value="yes" onclick="setAirspace();"/>Show class A airspace<br><input type="checkbox" id="clD" name="clD" value="yes" onclick="setAirspace();"/>Show class D airspace<br><input type="checkbox" id="clE" name="clE" value="yes" onclick="setAirspace();"/>Show class E airspace<br><input type="checkbox" id="clG" name="clG" value="yes" onclick="setAirspace();"/>Show class G airspace<br><input type="checkbox" id="clP" name="clP" value="yes" onclick="setAirspace();"/>Show prohibited airspace<br><input type="checkbox" id="clR" name="clR" value="yes" onclick="setAirspace();"/>Show restricted airspace<br><input type="checkbox" id="clM" name="clM" value="yes" onclick="setAirspace();"/>Show MATZs<br><input type="checkbox" id="clQ" name="clQ" value="yes" onclick="setAirspace();"/>Show danger areas<br><input type="checkbox" id="clX" name="clX" value="yes" onclick="setAirspace();"/>Show offshore/NOTAMed danger areas<br><input type="checkbox" id="clH" name="clH" value="yes" onclick="setAirspace();"/>Show hazards (HIRTA/AIAA etc)</fieldset></tr></table></form>
            </td>
            </tr>
        </table>
        <input type="button" id="notam-settings-close" name="notam-settings-submit" 
            value="Close"/ >
    </div>
</div>

<!-- Launch card -->
<div id="input_form" class="box ui-corner-all"> 
<img class="handle" src="images/drag_handle.png" />
<form action="" id="modelForm" name="modelForm">
<table id="launch-card">
    <tr>
        <td>Launch Site:
            <span>
                <a id="cookieLocations" class="tipsyLink" 
                    title="View your saved launch sites">Custom</a>
            </span>
        </td>
        <td>
            <select id="site" name="launchsite">
            </select>
        </td>
    </tr>
    <tr>
        <td>Latitude/Longitude:</td>
        <td>
            <input id="lat" type="text" size="8" name="lat" value="52.2135" 
                onKeyDown="SetSiteOther()">&nbsp;/&nbsp;
            <input id="lon" type="text" size="8" name="lon" value="0.0964" 
                onKeyDown="SetSiteOther()">
        </td>
    </tr>
    <tr>
        <td>
            <span><a id="setWithClick" class="tipsyLink" 
                title="Use the map to set your desired launch site">
                Set With Map</a></span>
        </td>
        <td>
            <span>
                <a id="req_open" class="tipsyLink" 
                    title="Save this location to a browser cookie">
                    Save Location</a>
            </span>
        </td>
    </tr>
    <tr>
        <td>Launch altitude (m):</td>
        <td>
            <input id="initial_alt" type="text" name="initial_alt" 
                value="0">
        </td>
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
        <td>
            Ascent Rate (m/s): 
        </td>
        <td><input id="ascent" type="text" name="ascent" value="5"></td>
    </tr>
    <tr>
        <td>Burst Altitude (m):</td>
        <td><input id="burst" type="text" name="burst" value="30000"></td>
    </tr>
    <tr>
        <td colspan="2">
            <a id="burst-calc-show" class="tipsyLink"
                title="Use a calculator to find highlighted values">
                Use Burst Calculator</a>
        </td>
    </tr>
    <tr>
        <td>
            Descent Rate (<a class="tipsyLink" title="At sea level">m/s</a>):
        </td>
        <td><input id="drag" type="text" name="drag" value="5"></td>
    </tr>
    <tr>
        <td>GFS Definition: </td><td>
        <select id="software" name="software">
            <option value="gfs" selected="selected">GFS</option>
            <option value="gfs_hd">GFS HD</option>
        </select></td>
    </tr>
    <tr><td>Lat/Lon Deltas: </td>
        <td>Lat: 
        <select id="delta_lat" name="delta_lat">
            <option value="3" selected="selected">3</option>
            <option value="5">5</option>
            <option value="10">10</option>
        </select>&nbsp;&nbsp;&nbsp;Lon: 
        <select id="delta_lon" name="delta_lon">
            <option value="3" selected="selected">3</option>
            <option value="5">5</option>
            <option value="10">10</option>
        </select>
        </td>
    </tr>
    <tr><td>Time Delta:</td>
        <td>
        <select id="delta_time" name="delta_time">
            <option value="5">~5 hour prediction</option>
            <option value="10">~10 hour prediction</option>
            <option value="24">~24 hour prediction</option>
        </select>
        </td>
    </tr>
<!--    <tr><td>Display UK NOTAMS &amp; Airspace: </td>
        <td>
	<input id="notam-display" type="checkbox" name="notams" value="notams" />
            <a id="notam-settings-show" class="tipsyLink"
                title="Advanced NOTAM &amp; Airspace Settings">
                Advanced</a>
        </td>
    </tr> -->
    <tr>
        <td></td>
        <td><input type="submit" name="submit" id="run_pred_btn" value="Run Prediction">
        </td>
    </tr>
</table>
</form>

</div>

</body>
</html>
