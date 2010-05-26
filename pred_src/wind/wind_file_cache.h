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

#ifndef __WIND_FILES_H__
#define __WIND_FILES_H__

#include "wind_file.h"

// A cache which scans the wind data directory for data files, tries to read
// the header and parse out their timestamp and window information. It then
// allows one to query for files closest in time and space for a specified
// latitude/longitude/time.

#ifdef __cplusplus
extern "C" {
#endif // __cplusplus

// An opaque type representing the cache itself.
typedef struct wind_file_cache_s        wind_file_cache_t;

// An opaque type representing a cache entry.
typedef struct wind_file_cache_entry_s  wind_file_cache_entry_t;

//                      Scan 'directory' for wind files. Return a new cache.
wind_file_cache_t      *wind_file_cache_new    (const char               *directory);

//                      Free resources associated with 'cache'.
void                    wind_file_cache_free   (wind_file_cache_t        *cache);

//                      Search for a cache entry closest to the specified lat, lon and time.
//                      *earlier and *later are set to the nearest cache entries which are
//                      (respectively) earlier and later.
void                    wind_file_cache_find_entry
                                               (wind_file_cache_t        *cache,
                                                float                     lat,
                                                float                     lon,
                                                unsigned long             timestamp,
                                                wind_file_cache_entry_t **earlier,
                                                wind_file_cache_entry_t **later);

//                      Return non-zero if the cache entry specifies contains the latitude
//                      and longitude.
int                     wind_file_cache_entry_contains_point
                                               (wind_file_cache_entry_t  *entry,
                                                float                     lat,
                                                float                     lon);

//                      Return a string which gives the full path to the wind file
//                      corresponding to 'entry'. This should not be freed since it
//                      is owned by the cache itself.
const char*             wind_file_cache_entry_file_path
                                               (wind_file_cache_entry_t  *entry);

//                      Return the timestamp of the specified cache entry.
unsigned int            wind_file_cache_entry_timestamp
                                               (wind_file_cache_entry_t  *entry);

//                      Return the file for of the specified cache entry loading it if 
//                      necessary.
wind_file_t*            wind_file_cache_entry_file
                                               (wind_file_cache_entry_t  *entry);

#ifdef __cplusplus
}
#endif // __cplusplus

#endif // __WIND_FILES_H__

// Data for God's own editor.
// vim:sw=8:ts=8:et:cindent
