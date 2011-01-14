/*
 * CUSF Landing Prediction Version 2
 * Jon Sowman 2010
 * jon@hexoc.com
 * http://www.hexoc.com
 *
 * http://github.com/jonsowman/cusf-standalone-predictor
 *
 * This file contains all of the prediction javascript functions
 * that are explicitly related to Google Map manipulation
 *
 */

// Initialise the map canvas with (lat, long, zoom)
function initMap(centre_lat, centre_lon, zoom_level) {
    // Make the map and set center
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

// Enable or disable user control of the map canvas, including scrolling,
// zooming and clicking
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

// This should be called on a "mousemove" event handler on the map canvas
// and will update scenario information display
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

// Takes an array of points and the name of the gmap object and plots
// the polygon onto the map
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

// Read the latitude and longitude currently in the launch card and plot
// a marker there with hover information
function plotClick() {
    // Clear the old marker
    clearMapItems();
    // Get the new values from the form
    click_lat = parseFloat($("#lat").val());
    click_lon = parseFloat($("#lon").val());
    // Make sure the data is valid before we try and do anything with it
    if ( isNaN(click_lat) || isNaN(click_lon) ) return;
    var click_pt = new google.maps.LatLng(click_lat, click_lon);
    clickMarker = new google.maps.Marker({
        position: click_pt,
        map: map,
        icon: 'images/target-1-sm.png',
        title: 'Currently selected launch location (' + click_lat + ', ' 
            + click_lon+')'
    });
    map_items['clickMarker'] = clickMarker;
    // Redraw the delta square
    drawDeltaSquare(map);
    map.panTo(click_pt);
    map.setZoom(8);
}

// Uses the currently selected lat, lon and delta values in the launch
// card to draw a square of the GFS data to be downloaded for the prediction
function drawDeltaSquare(map) {
    // Clear the old delta square if it exists
    if ( map_items['delta_square'] ) map_items['delta_square'].setMap(null);
    // Get the values from the form
    var lat = parseFloat($("#lat").val());
    var lon = parseFloat($("#lon").val());
    var dlat = parseFloat($("#delta_lat").val());
    var dlon = parseFloat($("#delta_lon").val());
    // Construct a rectange of points
    var points = [
        new google.maps.LatLng(lat+dlat, lon+dlon),
        new google.maps.LatLng(lat-dlat, lon+dlon),
        new google.maps.LatLng(lat-dlat, lon-dlon),
        new google.maps.LatLng(lat+dlat, lon-dlon),
        new google.maps.LatLng(lat+dlat, lon+dlon)
    ]
    // Draw this polygon onto the map canvas
    drawPolygon(points, map);
}

// Given a GLatLng object, write the latitude and longitude to the launch card
function setFormLatLon(GLatLng) {
    appendDebug("Trying to set the form lat long");
    $("#lat").val(GLatLng.lat().toFixed(4));
    $("#lon").val(GLatLng.lng().toFixed(4));
    // Remove the event handler so another click doesn't register
    setLatLonByClick(false);
    // Change the dropdown to read "other"
    SetSiteOther();
    // Plot the new marker for launch location
    appendDebug("Plotting the new launch location marker");
    plotClick();
}

// Enable or disable an event handler which, when a mouse click is detected
// on the map canvas, will write the coordinates of the clicked place to the
// launch card
function setLatLonByClick(state) {
    if ( state == true ) {
        // Check this listener doesn't already exist
        if (!clickListener) {
            appendDebug("Enabling the set with click listener");
            clickListener = google.maps.event.addListener(map, 'click', function(event) {
                appendDebug("Got a click from user, setting values into form");
                $("#error_window").fadeOut();
                setFormLatLon(event.latLng);
            });
        }
        // Tell the user what to do next
        throwError("Now click your desired launch location on the map");
    } else if ( state == false ) {
        appendDebug("Removing the set with click listener");
        google.maps.event.removeListener(clickListener);
        clickListener = null;
    } else {
        appendDebug("Unrecognised state for setLatLonByClick");
    }
}

// An associative array exists globally containing all objects we have placed
// onto the map canvas - this function clears all of them
function clearMapItems() {
    $("#cursor_pred").hide();
    if( getAssocSize(map_items) > 0 ) {
        appendDebug("Clearing previous map trace");
        for( i in map_items ) {
            map_items[i].setMap(null);
        }
    }
    map_items = [];
}

// The Haversine formula to calculate the distance across the surface between
// two points on the Earth
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

// Given a latitude, longitude, and a field to write the result to,
// find the name of the place using Google "reverse Geocode" API feature
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
