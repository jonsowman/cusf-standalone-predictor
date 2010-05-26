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

#ifndef __PRED_H__
#define __PRED_H__

#define VERSION "0.0.1"

// write a position entry into the output files
void write_position(float lat, float lng, float alt, int timestamp);

// start and finish KML files, basically just write header and footer in
void start_kml();
void finish_kml();

#endif // __PRED_H__


