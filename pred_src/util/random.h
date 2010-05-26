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

#ifndef __RANDOM_H__
#define __RANDOM_H__

// Return a random sample drawn from the normal distribution with mean mu and
// variance sigma2. If loglik is non-NULL, *loglik is set to the log-likelihood
// of drawing that sample.
float random_sample_normal(float mu, float sigma2, float *loglik);

#endif /* __RANDOM_H__ */

// vim:sw=4:ts=4:et:cindent
