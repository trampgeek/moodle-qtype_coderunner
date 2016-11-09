"""Strip trailing white space from all lines in the given set of files"""

import sys

def strip(filename):
    """Strip the trailing whitespace from all lines in the given file"""
    print("Stripping", filename)
    with open(filename) as fin:
        lines = fin.readlines()
    with open(filename, 'w') as fout:
        fout.write('\n'.join([line.rstrip() for line in lines]) + '\n')
    

def main():
    files = sys.argv[1:]
    if len(files) == 0:
        print("Usage: {} filename...".format(sys.argv[0]), file=sys.stderr)
    else:
        for file in files:
            strip(file)

main()