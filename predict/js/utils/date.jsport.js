Date.prototype.monthNames = new Array(
"January", "February", "March", "April",
"May", "June", "July", "August",
"September", "October", "November", "December"
);


Date.prototype.dayNames = new Array(
"Sunday", "Monday", "Tuesday", "Wednesday",
"Thursday", "Friday", "Saturday"
);


Date.prototype.format = function (formatStr) {
var heap = formatStr.split("");
var resHeap = new Array(heap.length);
var escapeChar = "\\"; // you can change this to something different, but
// don't use a character that has a formatting meaning,
// unless you want to disable it's functionality

// go through array and extract identifiers from its fields
for (var i = 0; i < heap.length; i++) {
switch(heap[i]) {
case escapeChar:
resHeap[i] = heap[i+1];
i++;
break;

case "a": // "am" or "pm"
var temp = this.getHours();
resHeap[i] = (temp < 12) ? "am" : "pm";
break;

case "A": // "AM" or "PM"
var temp = this.getHours();
resHeap[i] = (temp < 12) ? "AM" : "PM";
break;

case "d": // day of the month, 2 digits with leading zeros; i.e. "01" to "31"
var temp = String(this.getDate());
resHeap[i] = (temp.length > 1) ? temp : "0" + temp;
break;

case "D": // day of the week, textual, 3 letters; i.e. "Fri"
var temp = this.dayNames[this.getDay()];
resHeap[i] = temp.substring(0, 3);
break;

case "F": // month, textual, long; i.e. "January"
resHeap[i] = this.monthNames[this.getMonth()];
break;

case "g": // hour, 12-hour format without leading zeros; i.e. "1" to "12"
var temp = this.getHours();
resHeap[i] = (temp <= 12) ? temp : (temp - 12);
break;

case "G": // hour, 24-hour format without leading zeros; i.e. "0" to "23"
resHeap[i] = String(this.getHours());
break;

case "h": // hour, 12-hour format; i.e. "01" to "12"
var temp = String(this.getHours());
temp = (temp <= 12) ? temp : (temp - 12);
resHeap[i] = (temp.length > 1) ? temp : "0" + temp;
break;

case "H": // hour, 24-hour format; i.e. "00" to "23"
var temp = String(this.getHours());
resHeap[i] = (temp.length > 1) ? temp : "0" + temp;
break;

case "i": // minutes; i.e. "00" to "59"
var temp = String(this.getMinutes());
resHeap[i] = (temp.length > 1) ? temp : "0" + temp;
break;

case "I": // "1" if Daylight Savings Time, "0" otherwise. Works only on the northern hemisphere
var firstDay = new Date(this.getFullYear(), 0, 1);
resHeap[i] = (this.getTimezoneOffset() != firstDay.getTimezoneOffset()) ? (1) : (0);
break;

case "J": // day of the month without leading zeros; i.e. "1" to "31"
resHeap[i] = this.getDate();
break;

case "l": // day of the week, textual, long; i.e. "Friday"
resHeap[i] = this.dayNames[this.getDay()];
break;

case "L": // boolean for whether it is a leap year; i.e. "0" or "1"
resHeap[i] = (this.getFullYear() % 4) ? false : true;
break;

case "m": // month; i.e. "01" to "12"
var temp = String(this.getMonth() + 1);
resHeap[i] = (temp.length > 1) ? temp : "0" + temp;
break;

case "M": // month, textual, 3 letters; i.e. "Jan"
resHeap[i] = this.monthNames[this.getMonth()];
break;

case "n": // month without leading zeros; i.e. "1" to "12"
resHeap[i] = this.getMonth() + 1;
break;

case "O": // Difference to Greenwich time in hours; i.e. "+0200"
var minZone = this.getTimezoneOffset();
var mins = minZone % 60;
var hour = String(((minZone - mins) / 60) * -1);

if (hour.charAt(0) != "-") {
hour = "+" + hour;
}

hour = (hour.length == 3) ? (hour) : (hour.replace(/([+-])(\d)/, "$1" + 0 + "$2"));
resHeap[i] = hour + mins + "0";
break;

case "r": // RFC 822 formatted date; e.g. "Thu, 21 Dec 2000 16:01:07 +0200"
var dayName = this.dayNames[this.getDay()].substr(0, 3);
var monthName = this.monthNames[this.getMonth()].substr(0, 3);
resHeap[i] = dayName + ", " + this.getDate() + " " + monthName + this.format(" Y H:i:s O");
break;

case "s": // seconds; i.e. "00" to "59"
var temp = String(this.getSeconds());
resHeap[i] = (temp.length > 1) ? temp : "0" + temp;
break;

case "S": // English ordinal suffix for the day of the month, 2 characters; i.e. "st", "nd", "rd" or "th"
var temp = this.getDate();
var suffixes = ["st", "nd", "rd"];
var suffix = "";

if (temp >= 11 && temp <= 13) {
resHeap[i] = "th";
} else {
resHeap[i] = (suffix = suffixes[String(temp).substr(-1) - 1]) ? (suffix) : ("th");
}
break;


case "t": // number of days in the given month; i.e. "28" to "31"
resHeap[i] = this.getDay();
break;

/*
* T: Not implemented
*/

case "U": // seconds since the Unix Epoch (January 1 1970 00:00:00 GMT)
// remember that this does not return milisecs!
resHeap[i] = Math.floor(this.getTime() / 1000);
break;

case "w": // day of the week, numeric, i.e. "0" (Sunday) to "6" (Saturday)
resHeap[i] = this.getDay();
break;


case "W": // ISO-8601 week number of year, weeks starting on Monday
var startOfYear = new Date(this.getFullYear(), 0, 1, 0, 0, 0, 0);
var firstDay = startOfYear.getDay() - 1;

if (firstDay < 0) {
firstDay = 6;
}

var firstMonday = Date.UTC(this.getFullYear(), 0, 8 - firstDay);
var thisDay = Date.UTC(this.getFullYear(), this.getMonth(), this.getDate());
resHeap[i] = Math.floor((thisDay - firstMonday) / (1000 * 60 * 60 * 24 * 7)) + 2;
break;

case "y": // year, 2 digits; i.e. "99"
resHeap[i] = String(this.getFullYear()).substring(2);
break;

case "Y": // year, 4 digits; i.e. "1999"
resHeap[i] = this.getFullYear();
break;

case "z": // day of the year; i.e. "0" to "365"
var firstDay = Date.UTC(this.getFullYear(), 0, 0);
var thisDay = Date.UTC(this.getFullYear(), this.getMonth(), this.getDate());
resHeap[i] = Math.floor((thisDay - firstDay) / (1000 * 60 * 60 * 24));
break;

case "Z": // timezone offset in seconds (i.e. "-43200" to "43200").
resHeap[i] = this.getTimezoneOffset() * 60;
break;

default:
resHeap[i] = heap[i];
}
}

// return joined array
return resHeap.join("");
}
