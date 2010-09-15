#ifndef __COMMON_H__
#define __COMMON_H__

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <assert.h>
#include <math.h>

/* Let's pretend C has a boolean type. */
#define TRUE 1
#define FALSE 0
#define boolean int
#define bool char

#define AllocVar(pt) (pt = malloc_and_zero(sizeof(*pt)))
/* Shortcut to allocating a single variable on the heap and assigning pointer to it. */
#define AllocArray(pt, size) (pt = malloc_and_zero(sizeof(*pt) * (size)))

void *malloc_and_zero(size_t size);
void free_unless_null(void *p);
void zero_unless_null(void *p, size_t n);

/*
void *needMem(size_t size);

void freeMem(void *pt);

void *needLargeZeroedMem(size_t size);

void freez(void *vpt);

void zeroBytes(void *vpt, int count);
*/

#endif
