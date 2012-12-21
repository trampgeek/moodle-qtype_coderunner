#!/usr/bin/env python

# The script that's called by the PHP localsandbox class to run a job
# locally.
# Should reside in the same 'Sandbox' directory as the php sandbox code.
# Usage: localrunner.py workingDirPath timeoutInSecs memLimitInBytes maxProcesses progToRun arg1 arg2 ...

# Set maxProcesses to 0 to prevent a limitation on forking (apparently
# necessary for supporting Matlab for reasons that aren't clear).

# If the executing program requires stdin, it should be in a file prog.in
# within the working directory. Otherwise, /dev/null is used.

# After the program has been run, stdout and stderr will be in the working
# directory in files prog.out and prog.err respectively.

import os
import sys
import resource
import subprocess

def setlimits():
    global timeout, maxmem, maxprocs
    resource.setrlimit(resource.RLIMIT_CPU, (timeout, timeout))
    resource.setrlimit(resource.RLIMIT_CORE, (0, 0))
    resource.setrlimit(resource.RLIMIT_AS, (maxmem, maxmem))
    if maxprocs != 0:
        resource.setrlimit(resource.RLIMIT_NPROC, (maxprocs, maxprocs))
    resource.setrlimit(resource.RLIMIT_NOFILE, (20, 20))
    resource.setrlimit(resource.RLIMIT_FSIZE, (2000000, 2000000))

os.chdir(sys.argv[1])
timeout = int(sys.argv[2])
maxmem = int(sys.argv[3])
maxprocs = int(sys.argv[4])
sys.stdout = open("prog.out", "w")
sys.stderr = open("prog.err", "w")
if os.path.exists("prog.in"):
	sys.stdin = open("prog.in", "r")
else:
	sys.stdin = open("/dev/null", "r")

p = subprocess.Popen(sys.argv[5:],
	stdout = sys.stdout,
	stdin = sys.stdin,
	stderr = sys.stderr,
	preexec_fn=setlimits)
retCode = p.wait()
if retCode < 0:
    sys.stderr.write("Killed by signal #{}\n".format(-retCode))
elif retCode !=0:
    sys.stderr.write("Abnormal termination\n")
