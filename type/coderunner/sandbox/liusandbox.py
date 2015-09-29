#! /usr/bin/env python2
import sys
import json
import os
import tempfile
import traceback
from shutil import rmtree, copy

from sandbox import *
from sandboxpolicy import SelectiveOpenPolicy


# Main program
# Usage: liusandbox.py jsonEncodedRunSpec

if __name__ == '__main__':
    workdir = None
    symbol = dict((getattr(Sandbox, 'S_RESULT_%s' % i), i) for i in \
    ('PD', 'OK', 'RF', 'RT', 'TL', 'ML', 'OL', 'AT', 'IE', 'BP'))

    try:
        assert len(sys.argv) == 2
        runSpecFile = open(sys.argv[1])
        jsonData = runSpecFile.read()
        runSpec = json.loads(jsonData)
        runSpecFile.close()

        if runSpec['input']:
            inputFile = open('prog.in', 'w+')
            inputFile.write(runSpec['input'])
            inputFile.seek(0)
        else:
            inputFile = open('/dev/null')

        outputFile = open('prog.out', 'w+')
        stderrFile = open('prog.err', 'w+')
        cmd = runSpec['cmd']

        cookbook = {
            'args':   cmd,                  # command/args to execute
            'stdin':  inputFile,            # input to targeted program
            'stdout': outputFile,           # output from targeted program
            'stderr': stderrFile,           # error from targeted program
            'quota':  runSpec['quota']
        }

        # create a sandbox instance and execute till end

        s = Sandbox(**cookbook)
        readableDirs = runSpec['readableDirs']
        workdir = runSpec['workdir']
        s.policy = SelectiveOpenPolicy(s, readableDirs, extraWriteablePaths=[workdir])

        s.run()
        retCode = symbol.get(s.result, 'NA')
        details = s.probe(False)
        
        # monkey-patch probe details from policy status
        if hasattr(s.policy, 'details'):
            details.update(s.policy.details)

        outputFile.seek(0)
        stderrFile.seek(0)
        output = outputFile.read().decode('utf-8')
        stderr = stderrFile.read().decode('utf-8')
        if retCode == 'RF':
            stderr += s.policy.error

    except Exception as e:
        retCode = 'RT'
        output = ''
        stderr = repr(e) + "\n" + traceback.format_exc()  # TODO Remove traceback when debugged
        details = {}


    finally:
        result = { 'returnCode': retCode, 'output': output, 'stderr': stderr, 'details': details}
        print(json.dumps(result))
