/*
 * CUSF Landing Prediction Version 2
 * Jon Sowman 2010
 * jon@hexoc.com
 * http://www.hexoc.com
 *
 * http://github.com/jonsowman/cusf-standalone-predictor
 *
 */

$(document).ready(function() {

    // Are we trying to display an old prediction?
    if( window.location.hash != "" ) {
        var ln = window.location.hash.split("=");
        var posteq = ln[1];
        if ( posteq.length != 40 ) {
            throwError("The supplied hashstring was not a valid UUID.");
            appendDebug("The hashstring was not the expected length");
        } else {
            current_uuid = posteq;
            appendDebug("Got an old UUID to plot:<br>" + current_uuid);
            appendDebug("Trying to populate form with scenario data...");
            populateFormByUUID(current_uuid);
            appendDebug("Trying to get progress JSON");
            $.getJSON("preds/"+current_uuid+"/progress.json", 
                function(progress) {
                    appendDebug("Got progress JSON from server for UUID");
                    if ( progress['error'] || !progress['pred_complete'] ) {
                        appendDebug("The prediction was not completed"
                            + " correctly, quitting");
                    } else {
                        appendDebug("JSON said the prediction completed "
                            + "without errors");
                        writePredictionInfo(current_uuid, 
                            progress['run_time'], 
                            progress['gfs_timestamp']);
                        getCSV(current_uuid);
                    }
                });
        }
    }

    initMap(52, 0, 8);
    populateLaunchSite();
    setupEventHandlers();
    initUI();

    // see if we want an old prediction displayed
    if ( current_uuid != '0' ) {

    }

    // plot the initial launch location
    plotClick();

    // Initialise the burst calculator
    calc_init();
});

function predSub() {
    appendDebug(null, 1); // clear debug window
    appendDebug("Sending data to server...");
    // initialise progress bar
    $("#prediction_progress").progressbar({ value: 0 });
    $("#prediction_status").html("Sending data to server...");
    $("#status_message").fadeIn(250);
}

function populateFormByUUID(pred_uuid) {
    $.get("ajax.php", { "action":"getModelByUUID", "uuid":pred_uuid }, function(data) {
        if ( !data.valid ) {
            appendDebug("Populating form by UUID failed");
            appendDebug("The server said the model it made was invalid");
        } else {
            // we're good to go, populate the form
            $("#lat").val(data.latitude);
            $("#lon").val(data.longitude);
            $("#initial_alt").val(data.altitude);
            $("#hour").val(data.hour);
            // we need to make minutes be "04" instead of "4"
            var scenario_minute = data.minute;
            if ( scenario_minute < 10 ) scenario_minute = "0" + scenario_minute;
            $("#min").val(scenario_minute);
            $("#second").val(data.second);
            $("#day").val(data.day);
            $("#month").attr("selectedIndex", data.month-1);
            $("#year").val(data.year);
            // we have to use [] notation for
            // values that have -s in them
            $("#ascent").val(data['ascent-rate']);
            $("#drag").val(data['descent-rate']);
            $("#burst").val(data['burst-altitude']);
            $("#software").val(data.software);
            $("#delta_lat").val(data['lat-delta']);
            $("#delta_lon").val(data['lon-delta']);
            // now sort the map out
            SetSiteOther();
            plotClick();
        }
    }, 'json');
}

function addHashLink(link) {
   var ln = "#!/" + link;
   window.location = ln;
}

function showMousePos(GLatLng) {
    var curr_lat = GLatLng.lat().toFixed(4);
    var curr_lon = GLatLng.lng().toFixed(4);
    $("#cursor_lat").html(curr_lat);
    $("#cursor_lon").html(curr_lon);
    // if we have a prediction displayed
    // show range from launch and land:
    if ( current_uuid != 0 && map_items['launch_marker'] != null ) {
        var launch_pt = map_items['launch_marker'].position;
        var land_pt = map_items['land_marker'].position;
        var range_launch = distHaversine(launch_pt, GLatLng, 1);
        var range_land = distHaversine(land_pt, GLatLng, 1);
        $("#cursor_pred_launchrange").html(range_launch);
        $("#cursor_pred_landrange").html(range_land);
    }
    
}

function populateLaunchSite() {
    $("#site > option").remove();
    $.getJSON("sites.json", function(sites) {
        $.each(sites, function(sitename, site) {
            $("<option>").attr("value", sitename).text(sitename).appendTo("#site");
        });
        $("<option>").attr("value", "Other").text("Other").appendTo("#site");
        return true;
    });
    return true;
}

function changeLaunchSite() {
    var selectedName = $("#site").val();
    $.getJSON("sites.json", function(sites) {
        $.each(sites, function(sitename, site) {
           if ( selectedName == sitename ) {
                $("#lat").val(site.latitude);
                $("#lon").val(site.longitude);
                $("#initial_alt").val(site.altitude);
           }
        });
        plotClick();
    });
}

function writePredictionInfo(current_uuid, run_time, gfs_timestamp) {
    // populate the download links
    $("#dlcsv").attr("href", "preds/"+current_uuid+"/flight_path.csv");
    $("#dlkml").attr("href", "kml.php?uuid="+current_uuid);
    $("#panto").click(function() {
            map.panTo(map_items['launch_marker'].position);
            //map.setZoom(7);
    });
    $("#run_time").html(POSIXtoHM(run_time, "H:i d/m/Y"));
    $("#gfs_timestamp").html(gfs_timestamp);
}

function handlePred(pred_uuid) {
    $("#prediction_status").html("Searching for wind data...");
    $("#input_form").hide("slide", { direction: "down" }, 500);
    $("#scenario_info").hide("slide", { direction: "up" }, 500);
    // disable user control of the map canvas
    $("#map_canvas").fadeTo(1000, 0.2);
    // ajax to poll for progress
    ajaxEventHandle = setInterval("getJSONProgress('"+pred_uuid+"')", stdPeriod);
}

function getCSV(pred_uuid) {
    $.get("ajax.php", { "action":"getCSV", "uuid":pred_uuid }, function(data) {
            if(data != null) {
                appendDebug("Got JSON response from server for flight path, parsing...");
                if (parseCSV(data) ) {
                    appendDebug("Parsing function returned successfully.");
                    appendDebug("Done, AJAX functions quitting.");
                } else {
                    appendDebug("The parsing function failed.");
                }
            } else {
                appendDebug("Server couldn't find a CSV for that UUID");
                throwError("Sorry, we couldn't find the data for that UUID. "+
                    "Please run another prediction.");
            }
    }, 'json');
}

function getJSONProgress(pred_uuid) {
    $.ajax({
        url:"preds/"+pred_uuid+"/progress.json",
        dataType:'json',
        timeout: ajaxTimeout,
        error: function(xhr, status, error) {
            if ( status == "timeout" ) {
                appendDebug("Polling for progress JSON timed out");
                // check that we haven't reached maximum allowed timeout
                if ( ajaxTimeout < maxAjaxTimeout ) {
                    // if not, add the delta to the timeout value
                    newTimeout = ajaxTimeout + deltaAjaxTimeout;
                    appendDebug("Increasing AJAX timeout from " + ajaxTimeout
                        + "ms to " + newTimeout + "ms");
                    ajaxTimeout = newTimeout;
                } else if ( ajaxTimeout != hlTimeout ) {
                    // otherwise, increase poll delay and timeout
                    appendDebug("Reached maximum ajaxTimeout value of " + maxAjaxTimeout);
                    clearInterval(ajaxEventHandle);
                    appendDebug("Switching to high latency mode");
                    appendDebug("Setting polling interval to "+hlPeriod+"ms");
                    appendDebug("Setting progress JSON timeout to "+hlTimeout+"ms");
                    ajaxTimeout = hlTimeout;
                    ajaxEventHandle = setInterval("getJSONProgress('"+pred_uuid+"')", hlPeriod);
                }
            }
        },
        success: processProgress
    });
}

function processProgress(progress) {
    if ( progress['error'] ) {
        clearInterval(ajaxEventHandle);
        appendDebug("There was an error in running the prediction: "+progress['error']);
    } else {
        // get the progress of the wind data
        if ( progress['gfs_complete'] == true ) {
            if ( progress['pred_complete'] == true ) { // pred has finished
                $("#prediction_status").html("Prediction finished.");
                appendDebug("Server says: the predictor finished running.");
                appendDebug("Attempting to retrieve flight path from server");
                // reset the GUI
                resetGUI();
                // stop polling for JSON
                clearInterval(ajaxEventHandle);
                // parse the data
                getCSV(current_uuid);
                appendDebug("Server gave a prediction run timestamp of "+progress['run_time']);
                appendDebug("Server said it used the " + progress['gfs_timestamp'] + " GFS model");
                writePredictionInfo(current_uuid, progress['run_time'], progress['gfs_timestamp']);
                addHashLink("uuid="+current_uuid);
            } else if ( progress['pred_running'] != true ) {
                $("#prediction_status").html("Waiting for predictor to run...");
                appendDebug("Server says: predictor not yet running...");
            } else if ( progress['pred_running'] == true ) {
                $("#prediction_status").html("Predictor running...");
                appendDebug("Server says: predictor currently running");
            }
        } else {
            $("#prediction_status").html("Downloading wind data");
            $("#prediction_progress").progressbar("option", "value",
                progress['gfs_percent']);
            $("#prediction_percent").html(progress['gfs_percent'] + 
                "% - Estimated time remaining: " + progress['gfs_timeremaining']);
            appendDebug("Server says: downloaded " +
                progress['gfs_percent'] + "% of GFS files");
        }
    }
    return true;
}

function parseCSV(lines) {
    if(lines.length <= 0) {
        appendDebug("The server returned an empty CSV file");
        return false;
    }
    var path = [];
    var max_height = -10; //just any -ve number
    var max_point = null;
    var launch_lat;
    var launch_lon;
    var land_lat;
    var land_lon;
    var launch_pt;
    var land_pt;
    var burst_lat;
    var burst_lon;
    var burst_pt;
    var burst_time;
    var launch_time;
    var land_time;
        $.each(lines, function(idx, line) {
            entry = line.split(',');
            if(entry.length >= 4) { // check valid entry length
                var point = new google.maps.LatLng( parseFloat(entry[1]), parseFloat(entry[2]) );
                if ( idx == 0 ) { // get the launch lat/long for marker
                    launch_lat = entry[1];
                    launch_lon = entry[2];
                    launch_time = entry[0];
                    launch_pt = point;
                }

                // set on every iteration, last valid entry
                // gives landing position
                land_lat = entry[1];
                land_lon = entry[2];
                land_time = entry[0];
                land_pt = point;
                
                if(parseFloat(entry[3]) > max_height) {
                        max_height = parseFloat(entry[3]);
                        burst_pt = point;
                        burst_lat = entry[1];
                        burst_lon = entry[2];
                        burst_time = entry[0];
                }
                path.push(point);
            }
        });

    appendDebug("Flight data parsed, creating map plot...");
    clearMapItems();
    
    // calculate range and time of flight
    var range = distHaversine(launch_pt, land_pt, 1);
    var flighttime = land_time - launch_time;
    var f_hours = Math.floor((flighttime % 86400) / 3600);
    var f_minutes = Math.floor(((flighttime % 86400) % 3600) / 60);
    if ( f_minutes < 10 ) f_minutes = "0"+f_minutes;
    flighttime = f_hours + "hr" + f_minutes;
    $("#cursor_pred_range").html(range);
    $("#cursor_pred_time").html(flighttime);
    $("#cursor_pred").show();
    
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
        title: 'Balloon launch ('+launch_lat+', '+launch_lon+') at ' + POSIXtoHM(launch_time) + "UTC"
    });

    var land_marker = new google.maps.Marker({
        position: land_pt,
        map:map,
        icon: land_icon,
        title: 'Predicted Landing ('+land_lat+', '+land_lon+') at ' + POSIXtoHM(land_time) + "UTC"
    });

    var path_polyline = new google.maps.Polyline({
        path:path,
        map: map,
        strokeColor: '#000000',
        strokeWeight: 3,
        strokeOpacity: 0.75
    });

    var pop_marker = new google.maps.Marker({
            position: burst_pt,
            map: map,
            icon: burst_img,
            title: 'Balloon burst ('+burst_lat+', '+burst_lon+' at altitude ' + max_height + 'm) at ' + POSIXtoHM(burst_time) + "UTC"
    });

    // now add the launch/land markers to map
    // we might need these later, so push them
    // associatively
    map_items['launch_marker'] = launch_marker;
    map_items['land_marker'] = land_marker;
    map_items['pop_marker'] = pop_marker;
    map_items['path_polyline'] = path_polyline;

    // we wiped off the old delta square,
    // and it may have changed anyway, so re-plot
    drawDeltaSquare(map);
    
    // pan to the new position
    map.panTo(launch_pt);
    map.setZoom(8);

    return true;

}

function drawPolygon(points, gmap_object) {
    var newPoly = new google.maps.Polyline({
        path: points,
        strokeColor: "#FF0000",
        strokeOpacity: 0.4,
        //fillColor: "#FFFFFF",
        //fillOpacity: 0,
        strokeWeight: 2
    });
    map_items['delta_square'] = newPoly;
    newPoly.setMap(gmap_object);
}

function plotClick() {
    // clear the old marker
    clearMapItems();
    // get the new values from the form
    click_lat = parseFloat($("#lat").val());
    click_lon = parseFloat($("#lon").val());
    // Make sure the data is valid before we try and do anything with it
    if ( isNaN(click_lat) || isNaN(click_lon) ) return;
    var click_pt = new google.maps.LatLng(click_lat, click_lon);
    clickMarker = new google.maps.Marker({
        position: click_pt,
        map: map,
        icon: 'images/target-1-sm.png',
        title: 'Currently selected launch location ('+click_lat+', '+click_lon+')'
    });
    map_items['clickMarker'] = clickMarker;
    // redraw the delta square
    drawDeltaSquare(map);
    map.panTo(click_pt);
    map.setZoom(8);
}
    
function drawDeltaSquare(map) {
    // clear any old squares
    if ( map_items['delta_square'] ) map_items['delta_square'].setMap(null);
    // get the values from the form
    var lat = parseFloat($("#lat").val());
    var lon = parseFloat($("#lon").val());
    var dlat = parseFloat($("#delta_lat").val());
    var dlon = parseFloat($("#delta_lon").val());
    // make a rectange of points
    var points = [
    new google.maps.LatLng(lat+dlat, lon+dlon),
    new google.maps.LatLng(lat-dlat, lon+dlon),
    new google.maps.LatLng(lat-dlat, lon-dlon),
    new google.maps.LatLng(lat+dlat, lon-dlon),
    new google.maps.LatLng(lat+dlat, lon+dlon)
    ]
    // write the poly to the map
    drawPolygon(points, map);
}

function setFormLatLon(GLatLng) {
    appendDebug("Trying to set the form lat long");
    $("#lat").val(GLatLng.lat().toFixed(4));
    $("#lon").val(GLatLng.lng().toFixed(4));
    // remove the event handler so another click doesn't register
    setLatLonByClick(false);
    // change the dropdown to read "other"
    SetSiteOther();
    // plot the new marker for launch location
    appendDebug("Plotting the new launch location marker");
    plotClick();
}

function setLatLonByClick(state) {
    if ( state == true ) {
        // check this listener doesn't already exist
        if (!clickListener) {
            appendDebug("Enabling the set with click listener");
            clickListener = google.maps.event.addListener(map, 'click', function(event) {
                appendDebug("Got a click from user, setting values into form");
                $("#error_window").fadeOut();
                setFormLatLon(event.latLng);
            });
        }
        // tell the user what to do next
        throwError("Now click your desired launch location on the map");
    } else if ( state == false ) {
        appendDebug("Removing the set with click listener");
        google.maps.event.removeListener(clickListener);
        clickListener = null;
    } else {
        appendDebug("Unrecognised state for setLatLonByClick");
    }

}

function enableMap(map, state) {
    if ( state != false && state != true) {
        appendDebug("Unrecognised map state");
    } else if (state == false) {
        map.draggable = false;
        map.disableDoubleClickZoom = true;
        map.scrollwheel = false;
        map.navigationControl = false;
    } else if (state == true ) {
        map.draggable = true;
        map.disableDoubleClickZoom = false;
        map.scrollwheel = false;
        map.navigationControl = true;
    }
}

function clearMapItems() {
    $("#cursor_pred").hide();
    if(getAssocSize(map_items) > 0) {
    appendDebug("Clearing previous map trace");
        for(i in map_items) {
            map_items[i].setMap(null);
        }
    }
    map_items = [];
}

function getAssocSize(arr) {
    var i = 0;
    for ( j in arr ) {
        i++;
    }
    return i;
}



function initMap(centre_lat, centre_lon, zoom_level) {
    // make the map and set center
    var latlng = new google.maps.LatLng(centre_lat, centre_lon);
    var myOptions = {
      zoom: zoom_level,
      scaleControl: true,
      scaleControlOptions: { position: google.maps.ControlPosition.BOTTOM_LEFT } ,
      mapTypeId: google.maps.MapTypeId.TERRAIN,
      center: latlng
    };
    map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
}

function setupEventHandlers() {
    // attach form submit event handler to launch card
    $("#modelForm").ajaxForm({
        url: 'ajax.php?action=submitForm',
        type: 'POST',
        dataType: 'json',
        success: function(data) {
            if ( data.valid == "false" ) {
                // If something went wrong, write the error messages to
                // the debug window
                appendDebug("The server rejected the submitted form data:");
                appendDebug(data.error);
                // And throw an error window to alert the user of what happened
                throwError("The server rejected the submitted form data: \n"
                    + data.error);
                resetGUI();
            } else if ( data.valid == "true" ) {
                predSub();
                appendDebug("The server accepted the form data");
                // update the global current_uuid variable
                current_uuid = data.uuid;
                appendDebug("The server gave us uuid:<br>" + current_uuid);
                appendDebug("Starting to poll for progress JSON");
                handlePred(current_uuid);
            } else {
                appendDebug("data.valid was not a recognised state: " + data.valid);
            }
        }
    });
    
    // Activate the "Save" button in the "Save Location to Cookie" window
    $("#req_sub_btn").click(function() {
        saveLocationToCookie();
    });
        
    // Activate the "Set with Map" link
    $("#setWithClick").click(function() {
        setLatLonByClick(true);
    });
    // Activate the "use burst calc" links
    $("#burst-calc-show").click(function() {
        $("#burst-calc-wrapper").show();
    });
    $("#burst-calc-use").click(function() {
        // Write the ascent rate and burst altitude to the launch card
        $("#ascent").val($("#ar").html());
        $("#burst").val($("#ba").html());
        $("#burst-calc-wrapper").hide();
    });
    $("#burst-calc-close").click(function() {
        // Close the burst calc without doing anything
        $("#burst-calc-wrapper").hide();
        $("#modelForm").show();
    });
    $("#burst-calc-advanced-show").click(function() {
        // Show the burst calculator constants
        $("#burst-calc").slideUp();
        $("#burst-calc-constants").slideDown();
    });
    $("#burst-calc-advanced-hide").click(function() {
        // Show the burst calculator constants
        $("#burst-calc-constants").slideUp();
        $("#burst-calc").slideDown();
    });
    // attach onchange handlers to the lat/long boxes
    $("#lat").change(function() {
        plotClick();
    });
    $("#lon").change(function() {
        plotClick();
    });
    $("#site").change(function() {
        if ( $("#site").val() == "Other" ) {
            appendDebug("User requested locally saved launch sites");
            if ( constructCookieLocationsTable("cusf_predictor") ) {
                $("#location_save_local").fadeIn();
            }
        } else {
            plotClick();
        }
    });
    $("#showHideDebug").click(function() {
        toggleWindow("scenario_template", "showHideDebug", "Show Debug", "Hide Debug");
    });
    $("#showHideDebug_status").click(function() {
        toggleWindow("scenario_template", "showHideDebug", "Show Debug", "Hide Debug");
    });
    $("#showHideForm").click(function() {
        toggleWindow("input_form", "showHideForm", "Show Launch Card",
            "Hide Launch Card");
    });
    $("#closeErrorWindow").click(function() {
        $("#error_window").fadeOut();
    });
    $("#cookieLocations").click(function() {
        appendDebug("User requested locally saved launch sites");
        if ( constructCookieLocationsTable("cusf_predictor") ) {
            $("#location_save_local").fadeIn();
        }
    });
    $("#about_window_show").click(function() {
        $("#about_window").dialog({
            modal:true,
            width:600,
            buttons: {
                Close: function() {
                        $(this).dialog('close');
                    }
            }
        });
    });
    $("#delta_lat").change(function() {
        drawDeltaSquare(map);
    });
    $("#delta_lon").change(function() {
        drawDeltaSquare(map);
    });
    $("#site").change(function() {
        changeLaunchSite();
    });
    $("#req_close").click(function() {
            $("#location_save").fadeOut();
    });
    $("#locations_close").click(function() {
            $("#location_save_local").fadeOut();
    });
    $("#req_open").click(function() {
            var lat = $("#lat").val();
            var lon = $("#lon").val();
            $("#req_lat").val(lat);
            $("#req_lon").val(lon);
            $("#req_alt").val($("#initial_alt").val());
            appendDebug("Trying to reverse geo-code the launch point");
            rvGeocode(lat, lon, "req_name");
            $("#location_save").fadeIn();
    });
    $(".tipsyLink").tipsy({fade: true});
    google.maps.event.addListener(map, 'mousemove', function(event) {
        showMousePos(event.latLng);
    });
}

function rvGeocode(lat, lon, fillField) {
    var geocoder = new google.maps.Geocoder();
    var latlng = new google.maps.LatLng(parseFloat(lat), parseFloat(lon));
    var coded = "Unnamed";
    geocoder.geocode({'latLng': latlng}, function(results, status) {
        if ( status == google.maps.GeocoderStatus.OK ) {
            // Successfully got rv-geocode information
            appendDebug("Got a good response from the geocode server");
            coded = results[1].address_components[1].short_name;
        } else {
            appendDebug("The rv-geocode failed: " + status);
        }
        // Now write the value to the field
        $("#"+fillField+"").val(coded);
    });
}

function POSIXtoHM(timestamp, format) {
    // using JS port of PHP's date()
    var ts = new Date();
    ts.setTime(timestamp*1000);
    // account for DST
    if ( ts.format("I") ==  1 ) {
        ts.setTime((timestamp-3600)*1000);
    }
    if ( format == null || format == "" ) format = "H:i";
    var str = ts.format(format);
    return str;
}

rad = function(x) {return x*Math.PI/180;}

distHaversine = function(p1, p2, precision) {
  var R = 6371; // earth's mean radius in km
  var dLat  = rad(p2.lat() - p1.lat());
  var dLong = rad(p2.lng() - p1.lng());

  var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
          Math.cos(rad(p1.lat())) * Math.cos(rad(p2.lat())) * Math.sin(dLong/2) * Math.sin(dLong/2);
  var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  var d = R * c;
  if ( precision == null ) {
      return d.toFixed(3);
  } else {
      return d.toFixed(precision);
  }
}

