import re
import sys
import zipfile
import os
import bz2

def file_open(filename, mode='r', arch_file=''):
    """Return file obj, with compression if appropriate extension is given

    Arguments:
    mode -- 'r' for reading a file (default, available for .zip, .gz, and .bz2)
            'w' for writing a file (available for .gz and .bz2)
    """

    # If filename is not a string, it is returned.
    if not isinstance(filename, basestring):
        return filename

    # ZIP compression actions.
    if re.search("\.zip", filename):
        archive = zipfile.ZipFile(filename, mode)
        if not hasattr(archive, 'open'):
            raise ImportError ('zipfile.ZipFile.open not available. '
                               'Upgrade python to 2.6 (or later) to ' +
                               'work with zip-compressed files!')
        if mode == 'r':
            files = archive.infolist()
            if not arch_file:
                assert len(files) == 1, \
                    'More than one file in ZIP archive, no filename provided.'
                return archive.open(files[0])
            else:
                return archive.open(arch_file)
        else:
            raise TypeError ('Zip archive only supported for reading.')

    # GZIP compression actions.
    elif re.search("\.gz", filename):
        if mode == 'r':
            return os.popen('zcat ' + filename)
        elif mode == 'w':
            return os.popen('gzip -c > ' + filename, 'w')
        else:
            raise TypeError ("Only read ('r') and write ('w') available " + 
                             "for gzip")

    # BZIP2 compression actions.
    elif re.search("\.bz2", filename):
        return bz2.BZ2File(filename, mode)
    else:
        return open(filename, mode)
