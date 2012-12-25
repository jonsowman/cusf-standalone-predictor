/*
 * CUSF Landing Prediction Version 2
 * Jon Sowman 2010
 * jon@hexoc.com
 * http://www.hexoc.com
 *
 * http://github.com/jonsowman/cusf-standalone-predictor
 *
 * This file contains javascript functions related to the handling
 * of the user interface for the predictor.
 *
 */

// Initialise the UI - this must be called on document ready
function initUI() {
    // Make UI elements such as windows draggable
    $("#input_form").draggable({containment: '#map_canvas', handle:
        'img.handle', snap: '#map_canvas'});
    $("#scenario_info").draggable({containment: '#map_canvas', handle:
        'img.handle', snap: '#map_canvas'});
    $("#location_save").draggable({containment: '#map_canvas', handle:
        'img.handle', snap: '#map_canvas'});
    $("#location_save_local").draggable({containment: '#map_canvas', handle:
            'img.handle', snap: '#map_canvas'});
    $("#burst-calc-wrapper").draggable({containment: '#map_canvas', handle:
            'img.handle', snap: '#map_canvas'}); 
    
    // Activate buttons to jqueryui styling
    $("#run_pred_btn").button();
    $("#req_sub_btn").button();
    $("#burst-calc-use").button();
    $("#burst-calc-close").button();
    $("#burst-calc-advanced-show").button();
    $("#burst-calc-advanced-hide").button();
}

// Throw an error window containing <data> and a 'close' link
function throwError(data) {
    $("#error_message").html(data);
    $("#error_window").fadeIn();
}

// Reset the GUI to a onLoad state ready for a new prediction to be shown
function resetGUI() {
    $("#status_message").fadeOut(500);
    $("#error_window").fadeOut(500);
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

// Append a line to the debug window and scroll the window to the bottom
// Optional boolean second argument will clear the debug window if TRUE
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

// A function to scroll a scrollable <div> all the way to the bottom
function scrollToBottom(div_id) {
    $("#"+div_id).animate({scrollTop: $("#"+div_id)[0].scrollHeight});
}

// Show or hide GUI windows, can either "toggle", or force hide/show
// Takes the window name, the linker ID, the event handlers for
// 'onhide' and 'onshow', and a boolean 'force' parameter
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

// Set the selected item to "Other" in the launch locations selector
function SetSiteOther() {
    $("#site").val("Other");
}

