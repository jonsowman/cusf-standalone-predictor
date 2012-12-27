// --------------------------------------------------------------
// CU Spaceflight Landing Prediction
// Copyright (c) CU Spaceflight 2009, All Right Reserved
//
// Written by Rich Wareham <rjw57@cam.ac.uk>
//
// THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY 
// KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
// IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
// PARTICULAR PURPOSE.
// --------------------------------------------------------------

#include "wind_file.h"

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include <math.h>

#include "../util/getline.h"

extern int verbosity;

typedef struct wind_file_axis_s wind_file_axis_t;
struct wind_file_axis_s 
{
        unsigned int            n_values;

        //                      in actual fact, enough space is allocated for all values.
        float                   values[1];
};

struct wind_file_s
{
        //                      This is from the file header.
        float                   lat, latrad;
        float                   lon, lonrad;
        unsigned long           timestamp;

        //                      This describes the axes.
        unsigned int            n_axes;
        wind_file_axis_t      **axes;

        unsigned int            n_components;

        //                      A pointer to the actual data.
        float                  *data;
};

// These exciting functions are all to do with the fact that 'left' and 'right'
// is an interesting thing to talk about on a sphere.

// Canonicalise a longitude into the range (0, 360].
static float
_canonicalise_longitude(float lon)
{
        lon = fmodf(lon, 360.f);
        if(lon < 0.f) 
                lon += 360.f;
        assert((lon >= 0.f) && (lon < 360.f));
        return lon;
}

// Return the 'distance' between two canonical longitudes. This will never
// exceed 180 degrees.
static float
_longitude_distance(float lon_a, float lon_b)
{
        float d1 = fabsf(lon_a - lon_b);
        float d2 = 360.f - d1;
        return (d1 < d2) ? d1 : d2;
}

// Given two canonical longitudes, return non-zero iff lon_a is 'left' of lon_b
// or the same. 'Left' means that the number of degrees east one needs to go
// from lon_a to get to lon_b is < 180.
static int
_longitude_is_left_of(float lon_a, float lon_b)
{
        float deg_east;

        lon_a = _canonicalise_longitude(lon_a);
        lon_b = _canonicalise_longitude(lon_b);

        deg_east = lon_b - lon_a;
        if(deg_east < 0.f) 
                deg_east += 360.f;
        if(deg_east >= 360.f) 
                deg_east -= 360.f;

        assert((deg_east >= 0.f) && (deg_east < 360.f));

        return deg_east < 180.f;
}

// Given two values return non-zero iff a is 'left' of b, i.e. a <= b.
static int
_float_is_left_of(float a, float b)
{
        return a <= b;
}

// Given an axis, find the indicies of those values left and right of 'value'.
// Return non-zero if 'value' is outside of the axis range. Left is defined as
// 'rightmost value A where left_fun(A,value) returns non-zero' and 'right' is
// defined as 'leftmost value where left_fun(value,A) returns non-zero'.
static int
_wind_file_axis_find_value(wind_file_axis_t* axis, float value,
                int (*left_fun) (float a, float b),
                unsigned int* left, unsigned int* right)
{
        // NOTE: This function can't assume the axis is ordered since
        // at least longitude doesn't work that way. Similarly pressure is 
        // ordered greatest to smallest and latitude is ordered smallest to 
        // greatest.
        
        unsigned int i;
        float lval, rval;

        *left = *right = axis->n_values;
        lval = rval = 0.f;

        // Look over all axis values.
        for(i=0; i<axis->n_values; ++i)
        {
                float axval = axis->values[i];
                if((!left_fun(axval,lval) || (*left==axis->n_values)) && left_fun(axval, value))
                {
                        *left = i;
                        lval = axval;
                }
                if((!left_fun(rval,axval) || (*right==axis->n_values)) && left_fun(value, axval))
                {
                        *right = i;
                        rval = axval;
                }
        }
        return (*left != axis->n_values) && (*right != axis->n_values);
}

// Scan forward in 'file' looking for the first line which is a non-comment
// line. Update *line with a pointer to the line which should be free-ed after
// use and update *n with the number of characters in that line.
static
int _get_non_comment_line(char** line, size_t *n, FILE* file)
{
        *line = NULL;
        while((getline(line, n, file) >= 0) && ((*line)[0] == '#'))
        {
                // line does not need to be free-ed since getline will realloc
                // it if it is too small. See getline(3).
        }

        if(feof(file)) {
                return -1;
        }

        return *n;
}

// Parse a line of the form value1,value2,...,valueN and add the values to the
// values array of axis. This array should have already been allocated and the
// function ignores any values beyond the number indicated in n_values.
//
// Returns non-zero on success.
static int
_parse_values_line(const char* line, unsigned int n_values, float* values)
{
        unsigned int record_idx = 0;
        const char* record = line;
        float value;

        while(1 == sscanf(record, "%f", &value)) {
                if(record_idx >= n_values)
                {
                        fprintf(stderr, "ERROR: Read too many values for axis "
                                        "(%i, expected %i).\n",
                                        record_idx, n_values);
                        return 0;
                } else {
                        values[record_idx] = value;
                }

                // skip to end of record
                while((*record != ',') && (*record != '\n') && (*record != '\0'))
                        record++;
                
                // and advance past delimiter
                if(record != '\0')
                        record++;

                // update the record index
                record_idx ++;
        }

        // we define success as reading the number of values we expected.
        return record_idx == n_values;
}

wind_file_t*
wind_file_new(const char* filepath)
{
        FILE* file;
        char* line = NULL;
        size_t line_len;
        int num_lines, num_axes, num_components, i;
        wind_file_t* self;

        if(verbosity > 0)
                fprintf(stderr, "INFO: Loading wind data from '%s'.\n", filepath);

        file = fopen(filepath, "r");
        if(!file) {
                perror("ERROR: Could not open file.");
                return NULL;
        }

        // get the header
        if(0 > _get_non_comment_line(&line, &line_len, file))
        {
                fprintf(stderr, "ERROR: EOF before header.\n");
                fclose(file);
                return NULL;
        }

        self = (wind_file_t*)malloc(sizeof(wind_file_t));
        self->n_axes = 0;
        self->axes = NULL;
        self->data = NULL;

        if(5 != sscanf(line, "%f,%f,%f,%f,%ld", 
                                &self->lat, &self->latrad, 
                                &self->lon, &self->lonrad,
                                &self->timestamp))
        {
                fprintf(stderr, "ERROR: Error parsing header '%s'.\n", line);
                free(line);
                fclose(file);
                wind_file_free(self);
                return NULL;
        }
        free(line);

        // get the axis count
        if(0 > _get_non_comment_line(&line, &line_len, file))
        {
                fprintf(stderr, "ERROR: EOF before axis count.\n");
                fclose(file);
                wind_file_free(self);
                return NULL;
        }
        num_axes = atoi(line);
        free(line);

        self->n_axes = num_axes;

        // use calloc(3) so that we initialise everything to NULL.
        self->axes = (wind_file_axis_t**)calloc(num_axes, sizeof(wind_file_axis_t*));

        // process each axis
        for(i=0; i<num_axes; ++i)
        {
                int num_values;

                // get the value count for this axis
                if(0 > _get_non_comment_line(&line, &line_len, file))
                {
                        fprintf(stderr, "ERROR: EOF before axis value count.\n");
                        fclose(file);
                        wind_file_free(self);
                        return NULL;
                }
                num_values = atoi(line);
                free(line);

                if(num_values < 1) 
                {
                        fprintf(stderr, "ERROR: axis %i count is < 1.\n", i);
                        fclose(file);
                        wind_file_free(self);
                        return NULL;
                }

                self->axes[i] = (wind_file_axis_t*)
                        malloc(sizeof(wind_file_axis_t) + sizeof(float)*(num_values-1));
                self->axes[i]->n_values = num_values;

                // get the axis line
                if(0 > _get_non_comment_line(&line, &line_len, file))
                {
                        fprintf(stderr, "ERROR: EOF before axis value line.\n");
                        fclose(file);
                        wind_file_free(self);
                        return NULL;
                }

                if(!_parse_values_line(line, self->axes[i]->n_values, self->axes[i]->values))
                {
                        fprintf(stderr, "ERROR: Error parsing axis value line.\n");
                        fclose(file);
                        free(line);
                        wind_file_free(self);
                        return NULL;
                }

                free(line);
        }

        // get the line count
        if(0 > _get_non_comment_line(&line, &line_len, file))
        {
                fprintf(stderr, "ERROR: EOF before line count.\n");
                fclose(file);
                free(line);
                wind_file_free(self);
                return NULL;
        }

        num_lines = atoi(line);
        free(line);

        // get the datum component count
        if(0 > _get_non_comment_line(&line, &line_len, file))
        {
                fprintf(stderr, "ERROR: EOF before datum component count.\n");
                fclose(file);
                free(line);
                wind_file_free(self);
                return NULL;
        }

        num_components = atoi(line);
        free(line);
        self->n_components = num_components;

        // check number of lines matches what we expect
        {
                int expected_line_count = 1;
                for(i=0; i<self->n_axes; ++i)
                {
                        expected_line_count *= self->axes[i]->n_values;
                }
                if(expected_line_count != num_lines) 
                {
                        fprintf(stderr, "ERROR: Data axes imply %i records. "
                                        "The file header claims %i.\n",
                                        expected_line_count,
                                        num_lines);
                        fclose(file);
                        wind_file_free(self);
                        return NULL;
                }
        }

        if(verbosity > 0)
                fprintf(stderr, "INFO: Data is %i axis made up of "
                                "(%i records) x (%i components).\n",
                                num_axes, num_lines, num_components);

        // we have everything we need to actually read the data now. Allocate an
        // array to store it.
        self->data = (float*)malloc(sizeof(float) * num_lines * num_components);

        // and iterate reading data. FIXME: Extra data is currently ignored silently.
        // we should probably check there are no non-comment lines after the data.
        for(i=0; i<num_lines; ++i)
        {
                if((0 > _get_non_comment_line(&line, &line_len, file)) ||
                   (!_parse_values_line(line, num_components, &(self->data[num_components*i]))))
                {
                        fprintf(stderr, "ERROR: Could not parse data line %i of file. "
                                        "The file may be corrupt or truncated.\n",
                                        i);
                        free(line);
                        fclose(file);
                        wind_file_free(self);
                        return NULL;
                }

                // if we succeed, we need to free the line.
                free(line);
        }

        // close the file since we're done with it now.
        fclose(file);

        if(self->n_axes != 3) 
        {
                fprintf(stderr, "ERROR: Expected 3 axes in file.\n");
                wind_file_free(self);
                return NULL;
        }

        if(self->n_components != 3) 
        {
                fprintf(stderr, "ERROR: Expected 3 component data in file.\n");
                wind_file_free(self);
                return NULL;
        }

        return self;
}

void
wind_file_free(wind_file_t* file)
{
        if(!file)
                return;

        if(file->n_axes > 0) 
        {
                unsigned int i;
                for(i=0; i<file->n_axes; ++i) 
                {
                        if(file->axes[i])
                        {
                                free(file->axes[i]);
                        }
                }
                free(file->axes);
        }

        if(file->data)
        {
                free(file->data);
        }

        free(file);
}

static float*
_wind_file_get_record(wind_file_t* file, 
                unsigned int lat_idx, unsigned int lon_idx,
                unsigned int pressure_idx)
{
        size_t offset = file->n_components * 
                (lon_idx + 
                        file->axes[2]->n_values * 
                                (lat_idx + file->axes[1]->n_values * pressure_idx));
        return &(file->data[offset]);
}

static float
_wind_file_get_height(wind_file_t* file, 
                unsigned int lat_idx, unsigned int lon_idx,
                unsigned int pressure_idx)
{
        return _wind_file_get_record(file, lat_idx, lon_idx, pressure_idx)[0];
}

static void
_wind_file_get_wind_raw(wind_file_t* file, 
                unsigned int lat_idx, unsigned int lon_idx,
                unsigned int pressure_idx,
                float *u, float* v)
{
        float* record = _wind_file_get_record(file, lat_idx, lon_idx, pressure_idx);
        *u = record[1]; *v = record[2];
}

static float
_lerp(float a, float b, float lambda)
{
        return a * (1.f - lambda) + b * lambda;
}

static float
_bilinear_interpolate(float ll, float lr, float rl, float rr, float lambda1, float lambda2)
{
        float il = _lerp(ll, rl, lambda1);
        float ir = _lerp(lr, rr, lambda1);
        return _lerp(il,ir,lambda2);
}

int
wind_file_get_wind(wind_file_t* file, float lat, float lon, float height, 
                float* windu, float *windv, float *uvar, float *vvar)
{
        // we use static variables to 'cache' the last left and right lat/longs
        // and heights so that we can avoid searching the axes if necessary
        static int have_valid_latlon_cache = 0;
        static int have_valid_pressure_cache = 0;

        static unsigned int left_lat_idx, right_lat_idx;
        static unsigned int left_lon_idx, right_lon_idx;
        static unsigned int left_pr_idx, right_pr_idx;

        static float left_lat, right_lat;
        static float left_lon, right_lon;

        int i;
        float left_height, right_height;
        float lat_lambda, lon_lambda, pr_lambda;

        assert(file);
        assert(windu && windv);

        // canonicalise the longitude
        lon = _canonicalise_longitude(lon);

        // by default, return nothing in case of error.
        *windu = *windv = 0.f;

        // see if the cache is indeed valid
        if(have_valid_latlon_cache)
        {
                if((left_lat > lat) || 
                   (right_lat < lat) ||
                   !_longitude_is_left_of(left_lon, lon) || 
                   !_longitude_is_left_of(lon, right_lon))
                {
                        have_valid_latlon_cache = 0;
                }
        }

        // if we have no cached grid locations, look for them.
        if(!have_valid_latlon_cache)
        {
                // look for latitude along second axis 
                if(!_wind_file_axis_find_value(file->axes[1], lat,
                                        _float_is_left_of, &left_lat_idx, &right_lat_idx))
                {
                        fprintf(stderr, "ERROR: Latitude %f is not covered by file.\n", lat);
                        return 0;
                }
                left_lat = file->axes[1]->values[left_lat_idx];
                right_lat = file->axes[1]->values[right_lat_idx];

                // look for longitude along third axis
                if(!_wind_file_axis_find_value(file->axes[2], lon,
                                        _longitude_is_left_of, &left_lon_idx, &right_lon_idx))
                {
                        fprintf(stderr, "ERROR: Longitude %f is not covered by file.\n", lon);
                        return 0;
                }
                left_lon = file->axes[2]->values[left_lon_idx];
                right_lon = file->axes[2]->values[right_lon_idx];

                if(verbosity > 1)
                        fprintf(stderr, "INFO: Moved to latitude/longitude "
                                        "cell (%f,%f)-(%f,%f)\n",
                                        left_lat, left_lon, right_lat, right_lon);

                have_valid_latlon_cache = 1;
        }

        // compute the normalised lat/lon co-ordinate within the cell we're in.
        if(left_lat_idx != right_lat_idx)
                lat_lambda = (lat - left_lat) / (right_lat - left_lat);
        else
                lat_lambda = 0.5f;

        if(left_lon_idx != right_lon_idx)
                lon_lambda = _longitude_distance(lon, left_lon) 
                        / _longitude_distance(right_lon, left_lon);
        else
                lon_lambda = 0.5f;

        // munge the lambdas into the right range. Numerical approximations can nudge them
        // ~1e-08 either side sometimes.
        lat_lambda = (lat_lambda < 0.f) ? 0.f : lat_lambda;
        lat_lambda = (lat_lambda > 1.f) ? 1.f : lat_lambda;
        lon_lambda = (lon_lambda < 0.f) ? 0.f : lon_lambda;
        lon_lambda = (lon_lambda > 1.f) ? 1.f : lon_lambda;

        // use this normalised co-ordinate to check the left and right heights
        if(have_valid_pressure_cache)
        {
                float ll_height, lr_height, rl_height, rr_height;

                // left
                ll_height = _wind_file_get_height(file, left_lat_idx, left_lon_idx, left_pr_idx);
                lr_height = _wind_file_get_height(file, left_lat_idx, right_lon_idx, left_pr_idx);
                rl_height = _wind_file_get_height(file, right_lat_idx, left_lon_idx, left_pr_idx);
                rr_height = _wind_file_get_height(file, right_lat_idx, right_lon_idx, left_pr_idx);
                left_height = _bilinear_interpolate(ll_height, lr_height, rl_height, rr_height,
                                lat_lambda, lon_lambda);
                // if the leftmost height is too small and we can go lower...
                if((left_height > height) && (left_pr_idx > 0))
                        have_valid_pressure_cache = 0;

                // right
                ll_height = _wind_file_get_height(file, left_lat_idx, left_lon_idx, right_pr_idx);
                lr_height = _wind_file_get_height(file, left_lat_idx, right_lon_idx, right_pr_idx);
                rl_height = _wind_file_get_height(file, right_lat_idx, left_lon_idx, right_pr_idx);
                rr_height = _wind_file_get_height(file, right_lat_idx, right_lon_idx, right_pr_idx);
                right_height = _bilinear_interpolate(ll_height, lr_height, rl_height, rr_height,
                                lat_lambda, lon_lambda);
                // if the rightmost height is too small and we can go higher...
                if((right_height < height) && (right_pr_idx < file->axes[0]->n_values-1))
                        have_valid_pressure_cache = 0;
        }
        
        // if our height cache is out of whack, find a better cell.
        if(!have_valid_pressure_cache)
        {
                // search along all heights to find what pressure level we're at
                left_pr_idx = right_pr_idx = file->axes[0]->n_values;
                left_height = right_height = -1.f;
                for(i=0; i<file->axes[0]->n_values; ++i)
                {
                        // get heights for each corner of our lat/lon cell.
                        float ll_height = _wind_file_get_height(file, 
                                        left_lat_idx, left_lon_idx, i);
                        float lr_height = _wind_file_get_height(file, 
                                        left_lat_idx, right_lon_idx, i);
                        float rl_height = _wind_file_get_height(file, 
                                        right_lat_idx, left_lon_idx, i);
                        float rr_height = _wind_file_get_height(file,
                                        right_lat_idx, right_lon_idx, i);

                        // interpolate within our cell.
                        float interp_height = _bilinear_interpolate(
                                        ll_height, lr_height, rl_height, rr_height,
                                        lat_lambda, lon_lambda);

                        if((interp_height <= height) && 
                           ((interp_height >= left_height) || 
                            (left_pr_idx == file->axes[0]->n_values)))
                        {
                                left_pr_idx = i;
                                left_height = interp_height;
                        }

                        if((interp_height >= height) && 
                           ((interp_height <= right_height) ||
                            (right_pr_idx == file->axes[0]->n_values)))
                        {
                                right_pr_idx = i;
                                right_height = interp_height;
                        }
                }

                if(left_pr_idx == file->axes[0]->n_values)
                {
                        left_pr_idx = right_pr_idx;
                        if(verbosity > 0)
                                fprintf(stderr, "WARN: Moved to %.2fm, below height where we "
                                                "have data. "
                                                "Assuming we're at %.fmb or approx. %.2fm.\n",
                                                height,
                                                file->axes[0]->values[left_pr_idx],
                                                _wind_file_get_height(file,
                                                        left_lat_idx, left_lon_idx, left_pr_idx));
                }

                if(right_pr_idx == file->axes[0]->n_values)
                {
                        right_pr_idx = left_pr_idx;
                        if(verbosity > 0)
                                fprintf(stderr, "WARN: Moved to %.2fm, above height where we "
                                                "have data. "
                                                "Assuming we're at %.fmb or approx. %.2fm.\n",
                                                height,
                                                file->axes[0]->values[right_pr_idx],
                                                _wind_file_get_height(file,
                                                        left_lat_idx, left_lon_idx, right_pr_idx));
                }

                if((left_pr_idx == file->axes[0]->n_values) ||
                   (right_pr_idx == file->axes[0]->n_values))
                {
                        fprintf(stderr, "ERROR: Moved to a totally stupid height (%f). "
                                        "Giving up!\n", height);
                        return 0;
                }

                if(verbosity > 1)
                        fprintf(stderr, "INFO: Moved to pressure cell (%.fmb, %.fmb)\n", 
                                        file->axes[0]->values[left_pr_idx],
                                        file->axes[0]->values[right_pr_idx]);

                have_valid_pressure_cache = 1;
        }

        // compute the normalised pressure co-ordinate within the cell we're in.
        if(left_pr_idx != right_pr_idx)
                pr_lambda = (height - left_height) / (right_height - left_height);
        else
                pr_lambda = 0.5f;

        // pr_lambda might be outside of the range [0,1] depending on if we went
        // above or below our data, munge it appropriately.
        pr_lambda = (pr_lambda < 0.f) ? 0.f : pr_lambda;
        pr_lambda = (pr_lambda > 1.f) ? 1.f : pr_lambda;

        assert(lat_lambda >= 0.f);
        assert(lon_lambda >= 0.f);
        assert(pr_lambda >= 0.f);
        assert(lat_lambda <= 1.f);
        assert(lon_lambda <= 1.f);
        assert(pr_lambda <= 1.f);

        // By this point we have left/right co-ordinates which describe the
        // latitude, longitude and pressure boundaries of our data cell along
        // with normalised co-ordinates within it. We can now actually find
        // some data...
        //
        // We compute the lerped u and v along with the neighbourhood mean and
        // square mean to calculate the neighbourhood variance.
        {
                float llu, lru, rlu, rru;
                float llv, lrv, rlv, rrv;

                float lowu, lowv, highu, highv;

                float umean = 0.f;
                float usqmean = 0.f;
                float vmean = 0.f;
                float vsqmean = 0.f;

                // let's get the wind u and v for the lower lat/lon cell
                _wind_file_get_wind_raw(file, 
                                left_lat_idx, left_lon_idx, left_pr_idx, &llu, &llv);
                _wind_file_get_wind_raw(file, 
                                left_lat_idx, right_lon_idx, left_pr_idx, &lru, &lrv);
                _wind_file_get_wind_raw(file, 
                                right_lat_idx, left_lon_idx, left_pr_idx, &rlu, &rlv);
                _wind_file_get_wind_raw(file, 
                                right_lat_idx, right_lon_idx, left_pr_idx, &rru, &rrv);

                lowu = _bilinear_interpolate(llu, lru, rlu, rru, lat_lambda, lon_lambda);
                lowv = _bilinear_interpolate(llv, lrv, rlv, rrv, lat_lambda, lon_lambda);

                umean = llu + lru + rlu + rru;
                vmean = llv + lrv + rlv + rrv;
                usqmean = llu*llu + lru*lru + rlu*rlu + rru*rru;
                vsqmean = llv*llv + lrv*lrv + rlv*rlv + rrv*rrv;

                /*
                lowusq = _bilinear_interpolate(llu*llu, lru*lru, rlu*rlu, rru*rru,
                                lat_lambda, lon_lambda);
                lowvsq = _bilinear_interpolate(llv*llv, lrv*lrv, rlv*rlv, rrv*rrv, 
                                lat_lambda, lon_lambda);
                                */
                
                // let's get the wind u and v for the upper lat/lon cell
                _wind_file_get_wind_raw(file, 
                                left_lat_idx, left_lon_idx, right_pr_idx, &llu, &llv);
                _wind_file_get_wind_raw(file, 
                                left_lat_idx, right_lon_idx, right_pr_idx, &lru, &lrv);
                _wind_file_get_wind_raw(file, 
                                right_lat_idx, left_lon_idx, right_pr_idx, &rlu, &rlv);
                _wind_file_get_wind_raw(file, 
                                right_lat_idx, right_lon_idx, right_pr_idx, &rru, &rrv);

                highu = _bilinear_interpolate(llu, lru, rlu, rru, lat_lambda, lon_lambda);
                highv = _bilinear_interpolate(llv, lrv, rlv, rrv, lat_lambda, lon_lambda);

                umean += llu + lru + rlu + rru;
                vmean += llv + lrv + rlv + rrv;
                usqmean += llu*llu + lru*lru + rlu*rlu + rru*rru;
                vsqmean += llv*llv + lrv*lrv + rlv*rlv + rrv*rrv;

                /*
                highusq = _bilinear_interpolate(llu*llu, lru*lru, rlu*rlu, rru*rru,
                                lat_lambda, lon_lambda);
                highvsq = _bilinear_interpolate(llv*llv, lrv*lrv, rlv*rlv, rrv*rrv, 
                                lat_lambda, lon_lambda);
                                */

                *windu = _lerp(lowu, highu, pr_lambda);
                *windv = _lerp(lowv, highv, pr_lambda);

                // We will calculate the variance by making use of the fact
                // that the lerping is effectively a weighted mean or
                // expectation and that
                // var = E[X^2] - E[X]^2.
                //
                // In effect this calculates the instantaneous variance by considering the
                // contributions from the cube surrounding the point in question.
                // This is highly cunning and, on the face of it, not entirely wrong.

                umean *= 0.125f; usqmean *= 0.125f;
                vmean *= 0.125f; vsqmean *= 0.125f;

                *uvar = usqmean - umean * umean;
                *vvar = vsqmean - vmean * vmean;
        }

        return 1;
}

// Data for God's own editor.
// vim:sw=8:ts=8:et:cindent
