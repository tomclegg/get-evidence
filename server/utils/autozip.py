import re
import sys
import zipfile
import os
import bz2

def file_open(filename, mode='r'):
    """Return file obj, with compression if appropriate extension is given"""
    if re.search("\.zip", filename):
        archive = zipfile.ZipFile(filename, mode)
        if mode == 'r':
            files = archive.infolist()
            if len(files) == 1:
                if hasattr(archive, "open"):
                    return archive.open(files[0])
                else:
                    sys.exit("zipfile.ZipFile.open not available. Upgrade " +
                             "python to 2.6 (or later) to work with " +
                             "zip-compressed files!")
            else:
                sys.exit("Zip archive " + filename + 
                         " has more than one file!")
        else:
            sys.exit("Zip archive only supported for reading.")
    elif re.search("\.gz", filename):
        if mode == 'r':
            return os.popen('zcat ' + filename)
        elif mode == 'w':
            return os.popen('gzip -c > ' + filename, 'w')
        else:
            sys.exit("Only read ('r') and write ('w') available for gzip")
    elif re.search("\.bz2", filename):
        return bz2.BZ2File(filename, mode)
    else:
        return open(filename, mode)
