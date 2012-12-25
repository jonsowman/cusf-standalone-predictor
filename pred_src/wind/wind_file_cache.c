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

#include "wind_file_cache.h"
#include "wind_file.h"

#include <sys/types.h>
#include <sys/stat.h>

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>

#include <assert.h>
#include <dirent.h>
#include <errno.h>
#include <string.h>
#include <math.h>

#include "../util/getline.h"

extern int verbosity;

struct wind_file_cache_entry_s
{
        char                   *filepath;               // Full path.
        unsigned long           timestamp;              // As POSIX timestamp.
        float                   lat, lon;               // Window centre.
        float                   latrad, lonrad;         // Window radius.
        wind_file_t            *loaded_file;            // Initially NULL.
};

struct wind_file_cache_s
{
        char                   *directory_name;
        unsigned int            n_entries;
        struct wind_file_cache_entry_s    **entries;    // Matching directory entries.
};

// Yuk! Needed to make use of scandir. Gotta love APIs designed in the 80s.
static wind_file_cache_t* _scandir_current_cache;

static int
_parse_header(const char* filepath,
                float *lat, float *latrad, 
                float *lon, float *lonrad, 
                unsigned long* timestamp)
{
        FILE* file;
        char* line;
        size_t line_len;

        // Can I open this file?
        file = fopen(filepath, "r");
        if(!file) {
                // No, abort
                return 0;
        }

        // Look for first non-comment line.
        line = NULL;
        while((getline(&line, &line_len, file) >= 0) && (line[0] == '#'))
        {
                // line does not need to be free-ed since getline will realloc
                // it if it is too small. See getline(3).
        }

        if(feof(file)) 
        {
                // Got to the end without finding non-comment.
                free(line);
                fclose(file);
                return 0;
        }


        // 'line' is first non-comment. Try to parse it.
        if(5 != sscanf(line, "%f,%f,%f,%f,%ld", lat, latrad, lon, lonrad, timestamp))
        {
                // Failed to parse, it is invalid.
                free(line);
                fclose(file);
                return 0;
        }

        // File seems valid.
        free(line);
        fclose(file);

        return 1;
}

#ifdef __APPLE__
static int
_file_filter(struct dirent *entry)
#else
static int
_file_filter(const struct dirent *entry)
#endif
{
        int filepath_len;
        int rv;
        wind_file_cache_t* self = _scandir_current_cache;
        char* filepath = NULL;
        struct stat stat_buf;

        float lat, latrad, lon, lonrad;
        unsigned long timestamp;

        // This is using sprintf in C99 mode to create a buffer with
        // the full file path/
        filepath_len = 1 + snprintf(NULL, 0, "%s/%s", self->directory_name, entry->d_name);
        filepath = (char*)malloc(filepath_len);
        snprintf(filepath, filepath_len, "%s/%s", self->directory_name, entry->d_name);

        // Stat the file.
        rv = stat(filepath, &stat_buf);
        if(rv < 0)
        {
                perror("Error scanning data dir");
                free(filepath);
                return 0;
        }

        // Is this a regular file?
        if(!S_ISREG(stat_buf.st_mode))
        {
                free(filepath);
                return 0;
        }

        // Can I parse out the header?
        if(!_parse_header(filepath, &lat, &latrad, &lon, &lonrad, &timestamp))
        {
                free(filepath);
                return 0;
        }

        free(filepath);

        return 1;
}

wind_file_cache_t*
wind_file_cache_new(const char *directory)
{
        wind_file_cache_t* self;
        int rv, i;
        struct dirent **dir_entries;

        assert(directory);

        // Allocate memory for ourself
        self = (wind_file_cache_t*) malloc(sizeof(wind_file_cache_t));
        self->n_entries = 0;
        self->directory_name = strdup(directory);

        if(verbosity > 0)
                fprintf(stderr, "INFO: Scanning directory '%s'.\n", directory);

        // Use scandir scan the directory looking for data files.
        _scandir_current_cache = self; // ew!
        rv = scandir(directory, &dir_entries, _file_filter, alphasort);
        if(rv < 0) {
                perror(NULL);
                wind_file_cache_free(self);
                return NULL;
        }
        if(verbosity > 0)
                fprintf(stderr, "INFO: Found %i data files.\n", rv);
        self->n_entries = rv;

        self->entries = (struct wind_file_cache_entry_s**)malloc(sizeof(struct wind_file_cache_entry_s*)*self->n_entries);
        for(i=0; i<self->n_entries; ++i)
        {
                struct dirent* entry = dir_entries[i];
                int filepath_len, parse_rv;
                char* filepath = NULL;

                // allocate the entry.
                self->entries[i] = (struct wind_file_cache_entry_s*)malloc(sizeof(struct wind_file_cache_entry_s));

                // This is using sprintf in C99 mode to create a buffer with
                // the full file path/
                filepath_len = 1 + snprintf(NULL, 0, "%s/%s", self->directory_name, entry->d_name);
                filepath = (char*)malloc(filepath_len);
                snprintf(filepath, filepath_len, "%s/%s", self->directory_name, entry->d_name);

                // Fill in the file path.
                self->entries[i]->filepath = filepath;

                // Parse the file header
                parse_rv = _parse_header(filepath, 
                                &(self->entries[i]->lat), &(self->entries[i]->latrad), 
                                &(self->entries[i]->lon), &(self->entries[i]->lonrad), 
                                &(self->entries[i]->timestamp));
                if(!parse_rv)
                {
                        fprintf(stderr, "WARN: Hmm... some files appear to have "
                                        "changed under me!");
                }

                if(verbosity > 1) {
                        fprintf(stderr, "INFO: Found %s.\n", filepath);
                        fprintf(stderr, "INFO:   - Covers window (lat, long) = "
                                        "(%f +/-%f, %f +/-%f).\n",
                                        self->entries[i]->lat, self->entries[i]->latrad,
                                        self->entries[i]->lon, self->entries[i]->lonrad);
                }

                // initially, no file is loaded.
                self->entries[i]->loaded_file = NULL;

                // finished with this entry
                free(dir_entries[i]);
        }
        // finished with the dir entries.
        free(dir_entries);

        return self;
}

void
wind_file_cache_free(wind_file_cache_t *cache)
{
        if(!cache)
                return;

        free(cache->directory_name);

        if(cache->n_entries > 0)
        {
                unsigned int i;
                for(i=0; i<cache->n_entries; ++i)
                {
                        free(cache->entries[i]->filepath);
                        free(cache->entries[i]);
                        cache->entries[i] = NULL;
                }
                free(cache->entries);
        }

        free(cache);
}

static float
_lon_dist(float a, float b)
{
        float d1 = fabs(a-b);
        float d2 = 360.f - d1;
        return (d1 < d2) ? d1 : d2;
}

int
wind_file_cache_entry_contains_point(wind_file_cache_entry_t* entry, float lat, float lon)
{
        if(!entry)
                return 0;

        if(fabs(entry->lat - lat) > entry->latrad)
                return 0;

        if(_lon_dist(entry->lon, lon) > entry->lonrad)
                return 0;

        return 1;
}

void
wind_file_cache_find_entry(wind_file_cache_t *cache, 
                float lat, float lon, unsigned long timestamp,
                wind_file_cache_entry_t** earlier,
                wind_file_cache_entry_t** later)
{
        assert(cache && earlier && later);

        *earlier = *later = NULL;
        
        // This is the best we can do if we have no entries.
        if(cache->n_entries == 0)
                return;

        // Search for earlier and later entries which match
        unsigned int i;
        for(i=0; i<cache->n_entries; ++i)
        {
                wind_file_cache_entry_t* entry = cache->entries[i];

                if(entry->timestamp <= timestamp) {
                        // This is an earlier entry
                        if(!(*earlier) || (entry->timestamp > (*earlier)->timestamp))
                        {
                                if(wind_file_cache_entry_contains_point(entry,lat,lon))
                                        *earlier = entry;
                        }
                } else {
                        // This is a later entry
                        if(!(*later) || (entry->timestamp < (*later)->timestamp)) {
                                if(wind_file_cache_entry_contains_point(entry,lat,lon))
                                        *later = entry;
                        }
                }
        }
}

const char*
wind_file_cache_entry_file_path(wind_file_cache_entry_t* entry)
{
        if(!entry)
                return NULL;
        return entry->filepath;
}

unsigned int
wind_file_cache_entry_timestamp(wind_file_cache_entry_t* entry)
{
        if(!entry)
                return 0;
        return entry->timestamp;
}

wind_file_t*
wind_file_cache_entry_file(wind_file_cache_entry_t *entry)
{
        const char* filepath;

        if(!entry)
                return NULL;

        if(entry->loaded_file)
                return entry->loaded_file;

        filepath = wind_file_cache_entry_file_path(entry);
        if(!filepath)
                return NULL;

        entry->loaded_file = wind_file_new(filepath);
        return entry->loaded_file;
}

// Data for God's own editor.
// vim:sw=8:ts=8:et:cindent
