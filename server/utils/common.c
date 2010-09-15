/* common memory handling routines */

#include "common.h"

void *malloc_and_zero(size_t size)
{
	void *p = malloc(size);
	if (p)
	{
		memset(p, 0, size);
	}
	return p;
}

void free_unless_null(void *p)
{
	if (p)
	{
		free(p);
		p = NULL;
	}
}

void zero_unless_null(void *p, size_t n)
{
	char *char_p = (char *)p;
	if (char_p)
	{
		while (--n >= 0)
		{
			*char_p++ = 0;
		}
	}
}
