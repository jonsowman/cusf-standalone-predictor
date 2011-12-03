// --------------------------------------------------------------
// CU Spaceflight Landing Prediction
// Copyright (c) CU Spaceflight 2009, All Right Reserved
//
// Written by Rob Anderson 
// Modified by Fergus Noble
//
// THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY 
// KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
// IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
// PARTICULAR PURPOSE.
// --------------------------------------------------------------

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <errno.h>
#include <unistd.h>

#include "ini/iniparser.h"
#include "util/gopt.h"
#include "wind/wind_file_cache.h"

#include "run_model.h"
#include "pred.h"
#include "altitude.h"

FILE* output;
FILE* kml_file;
const char* data_dir;
int verbosity;

int main(int argc, const char *argv[]) {
    
    const char* argument;
    
    long int initial_timestamp;
    float initial_lat, initial_lng, initial_alt;
    float burst_alt, ascent_rate, drag_coeff, rmswinderror;
    int descent_mode;
    int scenario_idx, n_scenarios;
    int alarm_time;
    char* endptr;       // used to check for errors on strtod calls 
    
    wind_file_cache_t* file_cache;
    dictionary*        scenario = NULL;
    
    // configure command-line options parsing
    void *options = gopt_sort(&argc, argv, gopt_start(
        gopt_option('h', 0, gopt_shorts('h', '?'), gopt_longs("help")),
        gopt_option('z', 0, gopt_shorts(0), gopt_longs("version")),
        gopt_option('v', GOPT_REPEAT, gopt_shorts('v'), gopt_longs("verbose")),
        gopt_option('o', GOPT_ARG, gopt_shorts('o'), gopt_longs("output")),
        gopt_option('k', GOPT_ARG, gopt_shorts('k'), gopt_longs("kml")),
        gopt_option('t', GOPT_ARG, gopt_shorts('t'), gopt_longs("start_time")),
        gopt_option('i', GOPT_ARG, gopt_shorts('i'), gopt_longs("data_dir")),
        gopt_option('d', 0, gopt_shorts('d'), gopt_longs("descending")),
        gopt_option('e', GOPT_ARG, gopt_shorts('e'), gopt_longs("wind_error")),
        gopt_option('a', GOPT_ARG, gopt_shorts('a'), gopt_longs("alarm"))
    ));

    if (gopt(options, 'h')) {
        // Help!
        // Print usage information
        printf("Usage: %s [options] [scenario files]\n", argv[0]);
        printf("Options:\n\n");
        printf(" -h --help               Display this information.\n");
        printf(" --version               Display version information.\n");
        printf(" -v --verbose            Display more information while running,\n");
        printf("                           Use -vv, -vvv etc. for even more verbose output.\n");
        printf(" -t --start_time <int>   Start time of model, defaults to current time.\n");
        printf("                           Should be a UNIX standard format timestamp.\n");
        printf(" -o --output <file>      Output file for CSV data, defaults to stdout. Overrides scenario.\n");
        printf(" -k --kml <file>         Output KML file.\n");
        printf(" -d --descending         We are in the descent phase of the flight, i.e. after\n");
        printf("                           burst or cutdown. burst_alt and ascent_rate ignored.\n");
        printf(" -i --data_dir <dir>     Input directory for wind data, defaults to current dir.\n\n");
        printf(" -e --wind_error <err>   RMS windspeed error (m/s).\n");
        printf(" -a --alarm <seconds>    Use alarm() to kill pred incase it hangs.\n");
        printf("The scenario file is an INI-like file giving the launch scenario. If it is\n");
        printf("omitted, the scenario is read from standard input.\n");
      exit(0);
    }

    if (gopt(options, 'z')) {
      // Version information
      printf("Landing Prediction version: %s\nCopyright (c) CU Spaceflight 2009\n", VERSION);
      exit(0);
    }

    if (gopt_arg(options, 'a', &argument) && strcmp(argument, "-")) {
      alarm_time = strtol(argument, &endptr, 0);
      if (endptr == argument) {
        fprintf(stderr, "ERROR: %s: invalid alarm length\n", argument);
        exit(1);
      }
      alarm(alarm_time);
    }
    
    verbosity = gopt(options, 'v');
    
    if (gopt(options, 'd'))
        descent_mode = DESCENT_MODE_DESCENDING;
    else
        descent_mode = DESCENT_MODE_NORMAL;
      
    if (gopt_arg(options, 'k', &argument) && strcmp(argument, "-")) {
      kml_file = fopen(argument, "wb");
      if (!kml_file) {
        fprintf(stderr, "ERROR: %s: could not open KML file for output\n", argument);
        exit(1);
      }
    }
    else
      kml_file = NULL;

    if (gopt_arg(options, 't', &argument) && strcmp(argument, "-")) {
      initial_timestamp = strtol(argument, &endptr, 0);
      if (endptr == argument) {
        fprintf(stderr, "ERROR: %s: invalid start timestamp\n", argument);
        exit(1);
      }
    } else {
      initial_timestamp = time(NULL);
    }
    
    if (!(gopt_arg(options, 'i', &data_dir) && strcmp(data_dir, "-")))
      data_dir = "./";


    // populate wind data file cache
    file_cache = wind_file_cache_new(data_dir);

    // read in flight parameters
    n_scenarios = argc - 1;
    if(n_scenarios == 0) {
        // we'll parse from std in
        n_scenarios = 1;
    }

    for(scenario_idx = 0; scenario_idx < n_scenarios; ++scenario_idx) {
        char* scenario_output = NULL;

        if(argc > scenario_idx+1) {
            scenario = iniparser_load(argv[scenario_idx+1]);
        } else {
            scenario = iniparser_loadfile(stdin);
        }

        if(!scenario) {
            fprintf(stderr, "ERROR: could not parse scanario file.\n");
            exit(1);
        }

        if(verbosity > 1) {
            fprintf(stderr, "INFO: Parsed scenario file:\n");
            iniparser_dump_ini(scenario, stderr);
        }

        scenario_output = iniparser_getstring(scenario, "output:filename", NULL);

        if (gopt_arg(options, 'o', &argument) && strcmp(argument, "-")) {
            if(verbosity > 0) {
                fprintf(stderr, "INFO: Writing output to file specified on command line: %s\n", argument);
            }
            output = fopen(argument, "wb");
            if (!output) {
                fprintf(stderr, "ERROR: %s: could not open CSV file for output\n", argument);
                exit(1);
            }
        } else if (scenario_output != NULL) {
            if(verbosity > 0) {
                fprintf(stderr, "INFO: Writing output to file specified in scenario: %s\n", scenario_output);
            }
            output = fopen(scenario_output, "wb");
            if (!output) {
                fprintf(stderr, "ERROR: %s: could not open CSV file for output\n", scenario_output);
                exit(1);
            }
        } else {
            if(verbosity > 0) {
                fprintf(stderr, "INFO: Writing output to stdout.\n");
            }
            output = stdout;
        }

        // write KML header
        if (kml_file)
            start_kml();

        // The observant amongst you will notice that there are default values for
        // *all* keys. This information should not be spread around too well.
        // Unfortunately, this means we lack some error checking.

        initial_lat = iniparser_getdouble(scenario, "launch-site:latitude", 0.0);
        initial_lng = iniparser_getdouble(scenario, "launch-site:longitude", 0.0);
        initial_alt = iniparser_getdouble(scenario, "launch-site:altitude", 0.0);

        ascent_rate = iniparser_getdouble(scenario, "altitude-model:ascent-rate", 1.0);

        // The 1.1045 comes from a magic constant buried in
        // ~cuspaceflight/public_html/predict/index.php.
        drag_coeff = iniparser_getdouble(scenario, "altitude-model:descent-rate", 1.0) * 1.1045;

        burst_alt = iniparser_getdouble(scenario, "altitude-model:burst-altitude", 1.0);

        rmswinderror = iniparser_getdouble(scenario, "atmosphere:wind-error", 0.0);
        if(gopt_arg(options, 'e', &argument) && strcmp(argument, "-")) {
            rmswinderror = strtod(argument, &endptr);
            if (endptr == argument) {
                fprintf(stderr, "ERROR: %s: invalid RMS wind speed error\n", argument);
                exit(1);
            }
        }

        {
            int year, month, day, hour, minute, second;
            year = iniparser_getint(scenario, "launch-time:year", -1);
            month = iniparser_getint(scenario, "launch-time:month", -1);
            day = iniparser_getint(scenario, "launch-time:day", -1);
            hour = iniparser_getint(scenario, "launch-time:hour", -1);
            minute = iniparser_getint(scenario, "launch-time:minute", -1);
            second = iniparser_getint(scenario, "launch-time:second", -1);

            if((year >= 0) && (month >= 0) && (day >= 0) && (hour >= 0)
                    && (minute >= 0) && (second >= 0)) 
            {
                struct tm timeval = { 0 };
                time_t scenario_launch_time = -1;

                if(verbosity > 0) {
                    fprintf(stderr, "INFO: Using launch time from scenario: "
                            "%i/%i/%i %i:%i:%i\n",
                            year, month, day, hour, minute, second);
                }

                timeval.tm_sec = second;
                timeval.tm_min = minute;
                timeval.tm_hour = hour;
                timeval.tm_mday = day; /* 1 - 31 */
                timeval.tm_mon = month - 1; /* 0 - 11 */
                timeval.tm_year = year - 1900; /* fuck you Millenium Bug! */

#ifndef _BSD_SOURCE
#               warning This version of mktime does not allow explicit setting of timezone. 
#else
                timeval.tm_zone = "UTC";
#endif

                scenario_launch_time = mktime(&timeval);
                if(scenario_launch_time <= 0) {
                    fprintf(stderr, "WARN: Launch time in scenario is invalid, reverting to "
                            "default timestamp.\n");
                } else {
                    initial_timestamp = scenario_launch_time;
                }
            }
        }

        if(verbosity > 0) {
            fprintf(stderr, "INFO: Scenario loaded:\n");
            fprintf(stderr, "    - Initial latitude  : %lf deg N\n", initial_lat);
            fprintf(stderr, "    - Initial longitude : %lf deg E\n", initial_lng);
            fprintf(stderr, "    - Initial altitude  : %lf m above sea level\n", initial_alt);
            fprintf(stderr, "    - Initial timestamp : %li\n", initial_timestamp);
            fprintf(stderr, "    - Drag coeff.       : %lf\n", drag_coeff);
            if(!descent_mode) {
                fprintf(stderr, "    - Ascent rate       : %lf m/s\n", ascent_rate);
                fprintf(stderr, "    - Burst alt.        : %lf m\n", burst_alt);
            }
            fprintf(stderr, "    - Windspeed err.    : %f m/s\n", rmswinderror);
        }
        
        {
            // do the actual stuff!!
            altitude_model_t* alt_model = altitude_model_new(descent_mode, burst_alt, 
                                                             ascent_rate, drag_coeff);
            if(!alt_model) {
                    fprintf(stderr, "ERROR: error initialising altitude profile\n");
                    exit(1);
            }

            if (!run_model(file_cache, alt_model, 
                           initial_lat, initial_lng, initial_alt, initial_timestamp,
                           rmswinderror)) {
                    fprintf(stderr, "ERROR: error during model run!\n");
                    exit(1);
            }

            altitude_model_free(alt_model);
        }

        // release the scenario
        iniparser_freedict(scenario);
        
        // write footer to KML and close output files
        if (kml_file) {
            finish_kml();
            fclose(kml_file);
        }

        if (output != stdout) {
            fclose(output);
        }
    }

    // release gopt data, 
    gopt_free(options);

    // release the file cache resources.
    wind_file_cache_free(file_cache);

    return 0;
}

void write_position(float lat, float lng, float alt, int timestamp) {
    if (kml_file) {
        fprintf(kml_file, "%g,%g,%g\n", lng, lat, alt);
        if (ferror(kml_file)) {
          fprintf(stderr, "ERROR: error writing to KML file\n");
          exit(1);
        }
    }
        
    fprintf(output, "%d,%g,%g,%g\n", timestamp, lat, lng, alt);
    if (ferror(output)) {
      fprintf(stderr, "ERROR: error writing to CSV file\n");
      exit(1);
    }
}

void start_kml() {
    FILE* kml_header;
    char c;
    
    kml_header = fopen("kml_header", "r");
    
    while (!feof(kml_header)) {
      c = fgetc(kml_header);
      if (ferror(kml_header)) {
        fprintf(stderr, "ERROR: error reading KML header file\n");
        exit(1);
      }
      if (!feof(kml_header)) fputc(c, kml_file);
      if (ferror(kml_file)) {
        fprintf(stderr, "ERROR: error writing header to KML file\n");
        exit(1);
      }
    }
    
    fclose(kml_header);
}

void finish_kml() {
    FILE* kml_footer;
    char c;
    
    kml_footer = fopen("kml_footer", "r");
    
    while (!feof(kml_footer)) {
      c = fgetc(kml_footer);
      if (ferror(kml_footer)) {
        fprintf(stderr, "ERROR: error reading KML footer file\n");
        exit(1);
      }
      if (!feof(kml_footer)) fputc(c, kml_file);
      if (ferror(kml_file)) {
        fprintf(stderr, "ERROR: error writing footer to KML file\n");
        exit(1);
      }
    }
    
    fclose(kml_footer);
}

