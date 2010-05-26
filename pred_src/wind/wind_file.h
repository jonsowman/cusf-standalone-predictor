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

#ifndef __WIND_FILE_H__
#define __WIND_FILE_H__

#ifdef __cplusplus
extern "C" {
#endif // __cplusplus

// An opaque type representing the cache itself.
typedef struct wind_file_s        wind_file_t;

// An opaque type representing a cache entry.
typedef struct wind_file_entry_s  wind_file_entry_t;

//                      Open 'file' and parse contents. Return NULL on failure.
wind_file_t            *wind_file_new          (const char         *file);

//                      Free resources associated with 'file'.
void                    wind_file_free         (wind_file_t        *file);

void                    wind_file_get_wind     (wind_file_t        *file, 
                                                float               lat,
                                                float               lon,
                                                float               height, 
                                                float              *windu,
                                                float              *windv,
                                                float              *windusq,
                                                float              *windvsq);

#ifdef __cplusplus
}
#endif // __cplusplus

#endif // __WIND_FILE_H__

// Data for God's own editor.
// vim:sw=8:ts=8:et:cindent
