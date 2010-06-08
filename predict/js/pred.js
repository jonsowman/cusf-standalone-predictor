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
        // do nothing
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
            $("#min").val(data.minute);
            $("#second").val(data.second);
            $("#day").val(data.day);
            $("#month").attr("selectedIndex", data.month-1);
            $("#year").val(data.year);
            // we have to use [] notation for
            // values that have -s in them
            $("#ascent").val(data['ascent-rate']);
            $("#drag").val(data['descent-rate']);
            $("#burst").val(data.burst);
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

function throwError(data) {
    $("#error_message").html(data);
    $("#error_window").fadeIn();
}

function handlePred(pred_uuid) {
    $("#prediction_status").html("Searching for wind data...");
    $("#input_form").hide("slide", { direction: "down" }, 500);
    $("#scenario_info").hide("slide", { direction: "up" }, 500);
    // disable user control of the map canvas
    $("#map_canvas").fadeTo(1000, 0.2);
    // ajax to poll for progress
    ajaxEventHandle = setInterval("getJSONProgress('"+pred_uuid+"')", 2000);
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
        timeout: 500,
        // complete: function(data, httpstatus) {
        //     appendDebug(httpstatus);
        // },
        success: processProgress
    });
}

function resetGUI() {
    $("#status_message").fadeOut(500);
    // now clear the status window
    $("#prediction_status").html("");
    $("#prediction_progress").progressbar("options", "value", 0);
    $("#prediction_percent").html("");
    $("#cursor_pred").hide();
    // bring the input form back up
    toggleWindow("input_form", null, null, null, "show");
    toggleWindow("scenario_info", null, null, null, "show");
    // un-fade the map canvas
    $("#map_canvas").fadeTo(1500, 1);
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
                appendDebug("Attemping to retrieve flight path from server");
                // reset the GUI
                resetGUI();
                // stop polling for JSON
                clearInterval(ajaxEventHandle);
                // parse the data
                getCSV(current_uuid);
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
        title: 'Balloon launch ('+launch_lat+', '+launch_lon+') at ' + POSIXtoHM(launch_time)
    });

    var land_marker = new google.maps.Marker({
        position: land_pt,
        map:map,
        icon: land_icon,
        title: 'Predicted Landing ('+land_lat+', '+land_lon+') at ' + POSIXtoHM(land_time)
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
            title: 'Balloon burst ('+burst_lat+', '+burst_lon+' at altitude ' + max_height + 'm) at ' + POSIXtoHM(burst_time)
    });

    // now add the launch/land markers to map
    // we might need these later, so push them
    // associatively
    map_items['launch_marker'] = launch_marker;
    map_items['land_marker'] = land_marker;
    map_items['pop_marker'] = pop_marker;
    map_items['path_polyline'] = path_polyline;

    // pan to the new position
    map.panTo(launch_pt);
    map.setZoom(8);

    return true;

}

function plotClick() {
    // clear the old marker
    clearMapItems();
    // get the new values from the form
    click_lat = parseFloat($("#lat").val());
    click_lon = parseFloat($("#lon").val());
    var click_pt = new google.maps.LatLng(click_lat, click_lon);
    clickMarker = new google.maps.Marker({
        position: click_pt,
        map: map,
        icon: 'images/target-1-sm.png',
        title: 'Currently selected launch location ('+click_lat+', '+click_lon+')'
    });
    map_items['clickMarker'] = clickMarker;
    map.panTo(click_pt);
    map.setZoom(8);
}
    

function setFormLatLon(GLatLng) {
    $("#lat").val(GLatLng.lat().toFixed(4));
    $("#lon").val(GLatLng.lng().toFixed(4));
    // remove the event handler so another click doesn't register
    setLatLonByClick(false);
    // change the dropdown to read "other"
    SetSiteOther();
    // plot the new marker for launch location
    plotClick();
}

function setLatLonByClick(state) {
    if ( state == true ) {
        // check this listener doesn't already exist
        if (!clickListener) {
            clickListener = google.maps.event.addListener(map,
                    'click', function(event) {
                $("#error_window").fadeOut();
                setFormLatLon(event.latLng);
            });
        }
        // tell the user what to do next
        throwError("Now click your desired launch location on the map");
    } else if ( state == false ) {
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

function appendDebug(appendage, clear) {
    if ( clear == null ){
        var curr = $("#debuginfo").html();
        curr += "<br>" + appendage;
        $("#debuginfo").html(curr);
    } else {
        $("#debuginfo").html("");
    }
    // keep the debug window scrolled to bottom
    scrollToBottom("scenario_template");
    }

function scrollToBottom(div_id) {
    $("#"+div_id).animate({scrollTop: $("#"+div_id)[0].scrollHeight});
}

function toggleWindow(window_name, linker, onhide, onshow, force) {
    if ( force == null ) {
        if( $("#"+window_name).css('display') != "none" ){
            $("#"+window_name+"").hide("slide", { direction: "down" }, 500);
            $("#"+linker).html(onhide);
        } else {
            $("#"+window_name).show("slide", { direction: "down" }, 500);
            $("#"+linker).html(onshow);
        }
    } else if ( force == "hide" ) {
        if( $("#"+window_name).css('display') != "none" ){
            $("#"+window_name+"").hide("slide", { direction: "down" }, 500);
            $("#"+linker).html(onhide);
        }
    } else if ( force == "show") {
        if( $("#"+window_name).css('display') == "none" ){
            $("#"+window_name).show("slide", { direction: "down" }, 500);
            $("#"+linker).html(onshow);
        }
    } else {
        appendDebug("toggleWindow force parameter unrecognised");
    }
}

function POSIXtoHM(timestamp) {
    // using JS port of PHP's date()
    var ts = new Date();
    ts.setTime(timestamp*1000);
    // account for DST
    if ( ts.format("I") ==  1 ) {
        ts.setTime((timestamp-3600)*1000);
    }
    var str = ts.format("H:i");
    return str;
}

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
        optOther.selected = true;
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

