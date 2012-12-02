#! /usr/bin/env python2
import sys
import json
import os
import tempfile
from shutil import rmtree, copy

from sandbox import *
from sandboxpolicy import SelectiveOpenPolicy


def makeTempFile(contents, prefix, dir):
    tf = tempfile.TemporaryFile(dir=dir, prefix=prefix)
    if contents is not None:
        tf.write(contents.encode('utf-8'))
        tf.seek(0)
    return tf

# Main program
# Usage: liusandbox.py jsonEncodedRunSpec

if __name__ == '__main__':
    workdir = None
    symbol = dict((getattr(Sandbox, 'S_RESULT_%s' % i), i) for i in \
    ('PD', 'OK', 'RF', 'RT', 'TL', 'ML', 'OL', 'AT', 'IE', 'BP'))

    try:
        assert len(sys.argv) == 2
        workdir = tempfile.mkdtemp(dir="/tmp", prefix="coderunner_")
        os.chdir(workdir)
        runSpecFile = open(sys.argv[1])

        jsonData = runSpecFile.read()
        runSpec = json.loads(jsonData)
        runSpecFile.close()

        filename = runSpec['filename']

        copy(filename, workdir)  # Copy the source/executable to the working dir
        basename = os.path.basename(filename)
        copyfilename = os.sep.join([workdir, basename])

        if runSpec['input']:
            inputFile = makeTempFile(runSpec['input'], 'in_', workdir)
        else:
            inputFile = open('/dev/null')

        outputFile = makeTempFile(None, 'out_', workdir)
        stderrFile = makeTempFile(None, 'err_', workdir)


        cookbook = {
            'args':   runSpec['args'] + [copyfilename],      # command to execute
            'stdin':  inputFile,            # input to targeted program
            'stdout': outputFile,           # output from targeted program
            'stderr': stderrFile,           # error from targeted program
            #'owner' : 'nobody',   # TODO: see if there's any way to make this work
            'quota':  runSpec['quota']
        }

        # create a sandbox instance and execute till end

        s = Sandbox(**cookbook)
        s.policy = SelectiveOpenPolicy(s, extraWriteablePaths=[workdir])
        s.run()
        retCode = symbol.get(s.result, 'NA')
        details = s.probe(False)
        outputFile.seek(0)
        stderrFile.seek(0)
        output = outputFile.read().decode('utf-8')
        stderr = stderrFile.read().decode('utf-8')

    except Exception as e:
        retCode = 'RT'
        output = ''
        stderr = repr(e)
        details = {}


    finally:
        result = { 'returnCode': retCode, 'output': output, 'stderr': stderr, 'details': details}
        print(json.dumps(result))

        if workdir is not None:
            rmtree(workdir)
            pass




