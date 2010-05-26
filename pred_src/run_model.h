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

#ifndef __RUN_MODEL_H__
#define __RUN_MODEL_H__

#include "wind/wind_file_cache.h"
#include "altitude.h"

// run the model
int run_model(wind_file_cache_t* cache, altitude_model_t* alt_model,
              float initial_lat, float initial_lng, float initial_alt, 
	      long int initial_timestamp, float rmswinderror);

#define TIMESTEP 1          // in seconds
#define LOG_DECIMATE 50     // write entry to output files every x timesteps

#define METRES_TO_DEGREES  0.00000899289281755   // one metre corresponds to this many degrees latitude
#define DEGREES_TO_METRES  111198.92345          // one degree latitude corresponds to this many metres
#define DEGREES_TO_RADIANS 0.0174532925          // 1 degree is this many radians

// get the wind values in the u and v directions at a point in space and time from the dataset data
// we interpolate lat, lng, alt and time. The GRIB data only contains pressure levels so we first
// determine which pressure levels straddle to our desired altitude and then interpolate between them
int get_wind(wind_file_cache_t* cache, float lat, float lng, float alt, long int timestamp, float* wind_v, float* wind_u, float *wind_var);
// note: get_wind will likely call load_data and load a different tile into data, so just be careful that data could be pointing
// somewhere else after running get_wind

#endif // __RUN_MODEL_H__

