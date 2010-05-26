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

#ifndef __ALTITUDE_H__
#define __ALTITUDE_H__

typedef struct altitude_model_s altitude_model_t;

// create an altitude/time model with given parameters, must be called before
// calling run_model if descent_mode is DESCENT_MODE_DESCENDING then we start
// off with the balloon descending i.e. after burst
altitude_model_t    *altitude_model_new    (int                 descent_mode, 
                                            float               burst_alt, 
                                            float               ascent_rate,
                                            float               drag_coeff);

// free resources associated with the specified altitude model.
void                 altitude_model_free   (altitude_model_t   *model);

// returns the altitude corresponding to a certain time into the flight (in seconds)
// the result it stored in the alt variable.
// the contents of alt when the function is called with time_into_flight = 0
// will be taken as the starting altitude returns 1 normally and 0 when the
// flight has terminated
int                  altitude_model_get_altitude
                                           (altitude_model_t   *model,
                                            int                 time_into_flight, 
                                            float              *alt);



// it seems like overkill to do it this way but it is in preparation for being able to load in
// arbitrary altitude/time profiles from a file

#define DESCENT_MODE_DESCENDING 1
#define DESCENT_MODE_NORMAL 0

#endif // __ALTITUDE_H__

