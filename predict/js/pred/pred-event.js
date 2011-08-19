/*
 * CUSF Landing Prediction Version 2
 * Jon Sowman 2010
 * jon@hexoc.com
 * http://www.hexoc.com
 *
 * http://github.com/jonsowman/cusf-standalone-predictor
 *
 * This file contains the event handlers used in the predictor, which are
 * numerous. They are divided into functions that setup handlers for each
 * part of the predictor, and a calling function
 *
 */

function setupEventHandlers() {
    EH_LaunchCard();
    EH_BurstCalc();
    EH_NOTAMSettings();
    EH_ScenarioInfo();
    EH_LocationSave();

    // Tipsylink tooltip class activation
    $(".tipsyLink").tipsy({fade: true});

    // Add the onmove event handler to the map canvas
    google.maps.event.addListener(map, 'mousemove', function(event) {
        showMousePos(event.latLng);
    });
}

function EH_BurstCalc() {
    // Activate the "use burst calc" links
    $("#burst-calc-show").click(function() {
        $("#burst-calc-wrapper").show();
    });
    $("#burst-calc-show").hover(
        function() {
            $("#ascent,#burst").css("background-color", "#AACCFF");
        },
        function() {
            $("#ascent,#burst").css("background-color", "#FFFFFF");
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
        // We use a callback function to fade in the new content to make
        // sure the old content has gone, in order to create a smooth effect
        $("#burst-calc").fadeOut('fast', function() {
            $("#burst-calc-constants").fadeIn();
        });
    });
    $("#burst-calc-advanced-hide").click(function() {
        // Show the burst calculator constants
        $("#burst-calc-constants").fadeOut('fast', function() {
            $("#burst-calc").fadeIn();
        });
    });
}

function EH_NOTAMSettings() {
    // Activate the "use burst calc" links
    $("#notam-settings-show").click(function() {
	alert("RJH");
        $("#notam-settings-wrapper").show();
    });
    $("#burst-calc-show").hover(
        function() {
            $("#ascent,#burst").css("background-color", "#AACCFF");
        },
        function() {
            $("#ascent,#burst").css("background-color", "#FFFFFF");
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
        // We use a callback function to fade in the new content to make
        // sure the old content has gone, in order to create a smooth effect
        $("#burst-calc").fadeOut('fast', function() {
            $("#burst-calc-constants").fadeIn();
        });
    });
    $("#burst-calc-advanced-hide").click(function() {
        // Show the burst calculator constants
        $("#burst-calc-constants").fadeOut('fast', function() {
            $("#burst-calc").fadeIn();
        });
    });
}

function EH_LaunchCard() {
    // Attach form submit event handler to Run Prediction button
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
                // Update the global current_uuid variable
                current_uuid = data.uuid;
                appendDebug("The server gave us uuid:<br>" + current_uuid);
                appendDebug("Starting to poll for progress JSON");
                handlePred(current_uuid);
            } else {
                appendDebug("data.valid was not a recognised state: " 
                        + data.valid);
            }
        }
    });
    // Activate the "Set with Map" link
    $("#setWithClick").click(function() {
        setLatLonByClick(true);
    });
    $("#setWithClick,#req_open").hover(
        function() {
            $("#lat,#lon").css("background-color", "#AACCFF");
        },
        function() {
            $("#lat,#lon").css("background-color", "#FFFFFF");
        });
    // Launch card parameter onchange event handlers
    $("#lat").change(function() {
        plotClick();
    });
    $("#lon").change(function() {
        plotClick();
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
}

function EH_ScenarioInfo() {
    // Controls in the Scenario Information window
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
}

function EH_LocationSave() {
    // Location saving to cookies event handlers
    $("#req_sub_btn").click(function() {
        saveLocationToCookie();
    });
    $("#cookieLocations").click(function() {
        appendDebug("User requested locally saved launch sites");
        if ( constructCookieLocationsTable("cusf_predictor") ) {
            $("#location_save_local").fadeIn();
        }
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
    })
    $("#req_close").click(function() {
            $("#location_save").fadeOut();
    });
    $("#locations_close").click(function() {
            $("#location_save_local").fadeOut();
    });
}
