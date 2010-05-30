$(document).ready(function() {
        // do nothing
});

function appendDebug(appendage, clear) {
    if ( clear == null ){
        var curr = $("#debuginfo").html();
        curr += "<br>" + appendage;
        $("#debuginfo").html(curr);
    } else {
        $("#debuginfo").html("");
    }
}

function toggleDebugWindow() {
        if( $("#debuginfo").css('display') != "none" ){
                $("#debuginfo").hide("slide", { direction: "down" }, 500);
                $("#showHideDebug").html("Show");
        } else {
                $("#debuginfo").show("slide", { direction: "down" }, 500);
                $("#showHideDebug").html("Hide");
        }
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
        //cmbSite = document.getElementById("site");
        //cmbSite.selectedIndex = 1;
        optOther.selected = true;
}


// vim:et:ts=8:sw=8:autoindent
