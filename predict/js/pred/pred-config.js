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

// JavaScript configuration options in this file
// To keep the index page JS-free

var ajaxEventHandle;
var current_uuid = '0';

var map;
var kmlLayer = null;
var map_items = [];
var launch_img = "images/target-1-sm.png";
var land_img = "images/target-8-sm.png";
var burst_img = "images/pop-marker.png";
var clickListener;
var clickMarker;

// Polling progress parameters
var ajaxTimeout = 500;
var maxAjaxTimeout = 2000;
var deltaAjaxTimeout = 500;
var stdPeriod = 2000; // standard
var hlPeriod = 10000; // high latency
var hlTimeout = 5000; // high latency
