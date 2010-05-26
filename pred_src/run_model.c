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

#include <math.h>
#include <stdio.h>
#include <stdlib.h>
#include <assert.h>

#include "wind/wind_file.h"
#include "util/random.h"
#include "run_model.h"
#include "pred.h"
#include "altitude.h"

extern int verbosity;

#define RADIUS_OF_EARTH 6371009.f

typedef struct model_state_s model_state_t;
struct model_state_s
{
    float               lat;
    float               lng;
    float               alt;
    altitude_model_t   *alt_model;
    double              loglik;
};

// Get the distance (in metres) of one degree of latitude and one degree of
// longitude. This varys with height (not much grant you).
static void
_get_frame(float lat, float lng, float alt, 
        float *d_dlat, float *d_dlng)
{
    float theta, r;

    theta = 2.f * M_PI * (90.f - lat) / 360.f;
    r = RADIUS_OF_EARTH + alt;

    // See the differentiation section of
    // http://en.wikipedia.org/wiki/Spherical_coordinate_system

    // d/dv = d/dlat = -d/dtheta
    *d_dlat = (2.f * M_PI) * r / 360.f;

    // d/du = d/dlong = d/dphi
    *d_dlng = (2.f * M_PI) * r * sinf(theta) / 360.f;
}

static int 
_advance_one_timestep(wind_file_cache_t* cache, 
                      unsigned long delta_t,
                      unsigned long timestamp, unsigned long initial_timestamp,
                      unsigned int n_states, model_state_t* states,
                      float rmserror)
{
    unsigned int i;

    for(i=0; i<n_states; ++i)
    {
        float ddlat, ddlng;
        float wind_v, wind_u, wind_var;
        float u_samp, v_samp, u_lik, v_lik;
        model_state_t* state = &(states[i]);

        if(!altitude_model_get_altitude(state->alt_model, 
                                        timestamp - initial_timestamp, &state->alt))
            return 0;

        if(!get_wind(cache, state->lat, state->lng, state->alt, timestamp, 
                    &wind_v, &wind_u, &wind_var)) {
                fprintf(stderr, "ERROR: error getting wind data\n");
                return 0;
        }

        _get_frame(state->lat, state->lng, state->alt, &ddlat, &ddlng);

        // NOTE: it this really the right thing to be doing? - think about what
        // happens near the poles

        wind_var += rmserror * rmserror;

        assert(wind_var >= 0.f);

        //fprintf(stderr, "U: %f +/- %f, V: %f +/- %f\n",
        //        wind_u, sqrtf(wind_u_var),
        //        wind_v, sqrtf(wind_v_var));

        u_samp = random_sample_normal(wind_u, wind_var, &u_lik);
        v_samp = random_sample_normal(wind_v, wind_var, &v_lik);

        //u_samp = wind_u;
        //v_samp = wind_v;

        state->lat += v_samp * delta_t / ddlat;
        state->lng += u_samp * delta_t / ddlng;

        state->loglik += (double)(u_lik + v_lik);
    }

    return 1;
}

static int _state_compare_rev(const void* a, const void *b)
{
    model_state_t* sa = (model_state_t*)a;
    model_state_t* sb = (model_state_t*)b;

    // this returns a value s.t. the states will be sorted so that
    // the maximum likelihood state is at position 0.
    return sb->loglik - sa->loglik;
}

int run_model(wind_file_cache_t* cache, altitude_model_t* alt_model,
              float initial_lat, float initial_lng, float initial_alt,
              long int initial_timestamp, float rmswinderror) 
{
    model_state_t* states;
    const unsigned int n_states = 1;
    unsigned int i;

    states = (model_state_t*) malloc( sizeof(model_state_t) * n_states );

    for(i=0; i<n_states; ++i) 
    {
        model_state_t* state = &(states[i]);

        state->alt = initial_alt;
        state->lat = initial_lat;
        state->lng = initial_lng;
        state->alt_model = alt_model;
        state->loglik = 0.f;
    }

    long int timestamp = initial_timestamp;
    
    int log_counter = 0; // only write position to output files every LOG_DECIMATE timesteps
    
    while(_advance_one_timestep(cache, TIMESTEP, timestamp, initial_timestamp, 
                n_states, states, rmswinderror))
    {
        // Sort the array of models in order of log likelihood. 
        qsort(states, n_states, sizeof(model_state_t), _state_compare_rev);

        // write the maximum likelihood state out.
        if (log_counter == LOG_DECIMATE) {
            write_position(states[0].lat, states[0].lng, states[0].alt, timestamp);
            log_counter = 0;
        }

        log_counter++;
        timestamp += TIMESTEP;
    }

    for(i=0; i<n_states; ++i) 
    {
        model_state_t* state = &(states[i]);
        write_position(state->lat, state->lng, state->alt, timestamp);
    }

    fprintf(stderr, "INFO: Final maximum log lik: %f (=%f)\n", 
            states[0].loglik, exp(states[0].loglik));

    free(states);

    return 1;
}

int get_wind(wind_file_cache_t* cache, float lat, float lng, float alt, long int timestamp,
        float* wind_v, float* wind_u, float *wind_var) {
    int i;
    float lambda, wu_l, wv_l, wu_h, wv_h;
    float wuvar_l, wvvar_l, wuvar_h, wvvar_h;
    wind_file_cache_entry_t* found_entries[] = { NULL, NULL };
    wind_file_t* found_files[] = { NULL, NULL };
    unsigned int earlier_ts, later_ts;

    // look for a wind file which matches this latitude and longitude...
    wind_file_cache_find_entry(cache, lat, lng, timestamp, 
            &(found_entries[0]), &(found_entries[1]));

    if(!found_entries[0] || !found_entries[1]) {
        fprintf(stderr, "ERROR: Could not locate appropriate wind data tile for time.\n");
        return 0;
    }

    if(!wind_file_cache_entry_contains_point(found_entries[0], lat, lng) || 
            !wind_file_cache_entry_contains_point(found_entries[1], lat, lng))
    {
        fprintf(stderr, "ERROR: Could not locate appropriate wind data tile for location "
                "lat=%f, lon=%f.\n", lat, lng);
        return 0;
    }

    // Look in the cache for the files we need.
    for(i=0; i<2; ++i)
    {
        found_files[i] = wind_file_cache_entry_file(found_entries[i]);
    }

    earlier_ts = wind_file_cache_entry_timestamp(found_entries[0]);
    later_ts = wind_file_cache_entry_timestamp(found_entries[1]);

    if(earlier_ts == later_ts)
    {
        fprintf(stderr, "WARN: Do not have two data files around current time. "
                        "Expect the results to be wrong!\n");
    }

    if(earlier_ts != later_ts)
        lambda = ((float)timestamp - (float)earlier_ts) /
            ((float)later_ts - (float)earlier_ts);
    else
        lambda = 0.5f;

    wind_file_get_wind(found_files[0], lat, lng, alt, &wu_l, &wv_l, &wuvar_l, &wvvar_l);
    wind_file_get_wind(found_files[1], lat, lng, alt, &wu_h, &wv_h, &wuvar_h, &wvvar_h);

    *wind_u = lambda * wu_h + (1.f-lambda) * wu_l;
    *wind_v = lambda * wv_h + (1.f-lambda) * wv_l;

    // flatten the u and v variances into a single mean variance for the
    // magnitude.
    *wind_var = 0.5f * (wuvar_h + wuvar_l + wvvar_h + wvvar_l);

    return 1;
}

// vim:sw=4:ts=4:et:cindent
