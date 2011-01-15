/*
 * CUSF Landing Prediction Version 2
 * Jon Sowman 2010
 * jon@hexoc.com
 * http://www.hexoc.com
 *
 * http://github.com/jonsowman/cusf-standalone-predictor
 *
 * This file contains all the cookie-related functions for the landing
 * predictor
 *
 */

function saveLocationToCookie() {
    // Get the variables from the form
    var req_lat = $("#req_lat").val();
    var req_lon = $("#req_lon").val();
    var req_alt = $("#req_alt").val();
    var req_name = $("#req_name").val();
    var cookie_name = "cusf_predictor";
    var locations_limit = 5;
    var name_limit = 20;

    // Check the length of the name
    if ( req_name.length > name_limit ) {
        req_name = req_name.substr(0, name_limit);
    }

    // Now let's init the cookie
    $.Jookie.Initialise(cookie_name, 99999999);
    if ( !$.Jookie.Get(cookie_name, "idx") ) { 
        $.Jookie.Set(cookie_name, "idx", 0);
        var idx = 0;
    } else {
        var idx = $.Jookie.Get(cookie_name, "idx");
    }

    if ( $.Jookie.Get(cookie_name, "idx") >= locations_limit ) {
        $("#location_save").fadeOut();
        throwError("You may only save " + locations_limit 
                + " locations - please delete some.");
    } else {
        // Find the next free index we can use
        var i=1;
        while ( $.Jookie.Get(cookie_name, i+"_name") && i<=locations_limit ) {
            i++;
        }

        // We will use this idx for the next location
        $.Jookie.Set(cookie_name, i+"_lat", req_lat);
        $.Jookie.Set(cookie_name, i+"_lon", req_lon);
        $.Jookie.Set(cookie_name, i+"_alt", req_alt);
        $.Jookie.Set(cookie_name, i+"_name", req_name);

        // Increase the index
        idx++;
        $.Jookie.Set(cookie_name, "idx", idx);

        // Close dialog and let the user know it worked
        $("#location_save").hide();
        appendDebug("Successfully saved the location to cookie " + cookie_name);
    }

}

// For when the user clicks the "Custom" link for Launch Site
// Construct and display a table of their custom saved locations stored
// in a cookie, and display it
function constructCookieLocationsTable(cookie_name) {
var t = "";
t += "<table border='0'>";

$.Jookie.Initialise(cookie_name, 99999999);
if ( !$.Jookie.Get(cookie_name, "idx") || $.Jookie.Get(cookie_name, "idx") == 0 ) {
    throwError("You haven't saved any locations yet. Please click Save Location to do so.");
    return false;
} else {
    idx = $.Jookie.Get(cookie_name, "idx");
    t += "<tr style='font-weight:bold'><td>Name</td><td>Use</td><td>Delete</td></tr>";
    var i=1;
    var j=0;
    while ( j<idx ) {
        if ( $.Jookie.Get(cookie_name, i+"_name") ) {
            t += "<tr>";
            t += "<td>"+$.Jookie.Get(cookie_name, i+"_name")+"</td><td>";
            t += "<a id='"+i+"_usethis' onClick='setCookieLatLng(\""+cookie_name+"\", \""+i+"\")'>Use</a>";
            t += "</td><td>";
            t += "<a id='"+i+"_usethis' onClick='deleteCookieLocation(\""+cookie_name+"\", \""+i+"\")'>Delete</a>";
            t += "</td>";
            t += "</tr>";
            j++;
        }
        i++;
    }
    t += "</table>";
    $("#locations_table").html(t);
    return true;
}
}

// Given a cookie name and an location index, fill the launch card fields
// before hiding the Custom locations window and plotting the new launch
// site
function setCookieLatLng(cookie_name, idx) {
    var req_lat = $.Jookie.Get(cookie_name, idx+"_lat");
    var req_lon= $.Jookie.Get(cookie_name, idx+"_lon");
    var req_alt = $.Jookie.Get(cookie_name, idx+"_alt");
    $("#lat").val(req_lat);
    $("#lon").val(req_lon);
    $("#initial_alt").val(req_alt);
    // Now hide the window
    $("#location_save_local").fadeOut();
    SetSiteOther();
    plotClick();
}

// Given a cookie name and a location index, delete the custom location that
// is in the cookie
function deleteCookieLocation(cookie_name, idx) {
    // Delete the location in the cookie
    $.Jookie.Unset(cookie_name, idx+"_lat");
    $.Jookie.Unset(cookie_name, idx+"_lon");
    $.Jookie.Unset(cookie_name, idx+"_alt");
    $.Jookie.Unset(cookie_name, idx+"_name");
    // Decrease quantity in cookie by one
    var qty = $.Jookie.Get(cookie_name, "idx");
    qty -= 1;
    $.Jookie.Set(cookie_name, "idx", qty);
    // Now hide the window
    $("#location_save_local").fadeOut();
}

