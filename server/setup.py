#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

# A setup script to install the _twobit module
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

from distutils.core import setup
from distutils.extension import Extension
from Pyrex.Distutils import build_ext

extensions = []
extensions.append(Extension("utils._twobit", ["utils/_twobit.pyx"]))
extensions.append(Extension("utils.bitset", ["utils/bitset.pyx", "utils/binBits.c", "utils/bits.c", "utils/common.c"]))
extensions.append(Extension("simplejson._speedups", ["simplejson/_speedups.c"]))

def main():
    setup(name="trait",
        ext_modules=extensions,
        cmdclass={'build_ext': build_ext})
      
if __name__ == "__main__":
    main()
