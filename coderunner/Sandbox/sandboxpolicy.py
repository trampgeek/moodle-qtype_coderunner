# Define the policy for this sandbox
# ==================================

from sandbox import *
from posix import O_RDONLY
from platform import machine as arch
import os

system, machine = os.uname()[0], os.uname()[4]
if system not in ('Linux', ) or machine not in ('i686', 'x86_64', ):
    raise AssertionError("Unsupported platform type.\n")

class SelectiveOpenPolicy(SandboxPolicy):
    SC_open   = (2, 0)  if arch() == 'x86_64' else (5, 0)
    SC_unlink = (87, 0) if arch() == 'x86_64' else (10, 0)
    O_CLOEXEC = 0O2000000
    READABLE_FILE_PATHS = []  # Default readable file paths

    WRITEABLE_FILE_PATHS = []

    sc_table = None
    sc_safe = dict( # white list of essential linux syscalls
        i686 = set([3, 4, 19, 45, 54, 90, 91, 122, 125, 140, 163, \
                    192, 197, 224, 243, 252, ]),
        x86_64 = set([0, 1, 2, 5, 8, 9, 10, 11, 12, 16, 25, 63, 158, 231, ])
    )
    sc_safe['x86_64'] = sc_safe['x86_64'] | set([
        # User-defined safe calls added here
        3,      # close
        4,      # stat
        6,      # lstat
        13,     # rt_sigaction
        14,     # rt_sigprocmask
        15,     # rt_sigreturn
        21,     # access
        #22,     # pipe MATLAB
        32,     # dup
        33,     # dup3
        39,     # getpid MATLAB
        #41,     # sendfile MATLAB **CONSIDER**
        #42,     # socket MATLAB **CONSIDER**
        #43,     # connect MATLAB **CONSIDER**
        #56,     # clone MATLAB THIS IS A NO-NO. Breaks sandbox.
        #59,     # execve MATLAB ** CONSIDER **
        #61,     # wait4 MATLAB
        72,     # fcntl
        78,     # getdents
        79,     # getcwd
        #80,     # chdir MATLAB **CONSIDER**
        89,     # readlink
        97,     # getrlimit
        100,    # times
        102,    # getuid
        104,    # getgid
        107,    # geteuid
        108,    # getegid
        #110,    # getppid MATLAB
        #111,    # getpgrp MATLAB
        202,    # futex
        #203,    # sched_setaffinity MATLAB
        #204,    # sched_getaffinity MATLAB
        218,    # set_tid_address
        #257,    # openat MATLAB ***CONSIDER***
        #269,    # faccessat MATLAB
        273,    # set_robust_list
    ])

    def __init__(self, sbox, extraPaths = [], extraWriteablePaths = []):
        assert(isinstance(sbox, Sandbox))
        self.READABLE_FILE_PATHS += extraPaths
        self.WRITEABLE_FILE_PATHS += extraWriteablePaths

        # initialize table of system call rules
        self.sc_table = [self._KILL_RF, ] * 1024
        for scno in self.sc_safe[machine]:
            self.sc_table[scno] = self._CONT
        self.sbox = sbox
        self.error = 'UNKNOWN ERROR. PLEASE REPORT'

    def __call__(self, e, a):
        ext = e.ext0 if arch() == 'x86_64' else 0
        if e.type == S_EVENT_SYSCALL and (e.data, ext) == self.SC_open:
            return self.SYS_open(e, a)

        elif e.type == S_EVENT_SYSCALL and (e.data, ext) == self.SC_unlink:
            return self.SYS_unlink(e, a)

        elif e.type == S_EVENT_SYSRET and (e.data, ext) == self.SC_unlink:
            return self._CONT(e, a)  # allow return from unlink

        elif e.type in (S_EVENT_SYSCALL, S_EVENT_SYSRET):
            if machine == 'x86_64' and e.ext0 != 0:
                return self._KILL_RF(e, a)
            elif (e.data, ext) == self.SC_unlink and e.type == S_EVENT_SYSCALL:
                return self.SYS_unlink(e, a)
            else:
                return self.sc_table[e.data](e, a)

        else:
            # bypass other events to base class
            return SandboxPolicy.__call__(self, e, a)

    def _CONT(self, e, a): # continue
        a.type = S_ACTION_CONT
        return a

    def _KILL_RF(self, e, a): # restricted func.
        self.error = "ILLEGAL SYSTEM CALL (#{0})".format(e.data)
        a.type, a.data = S_ACTION_KILL, S_RESULT_RF
        return a

    def SYS_open(self, e, a):
        pathBytes, mode = self.sbox.dump(T_STRING, e.ext1), e.ext2
        path = pathBytes.decode('utf8').strip()
        path = collapseDotDots(path)

        if '..' in path:
            # Kill any attempt to work up the file tree
            self.error = "ILLEGAL FILE ACCESS ({0},{1})".format(path, mode)
            return SandboxAction(S_ACTION_KILL, S_RESULT_RF)
        elif not path.startswith('/'):
            # Allow all access to the current directory (which is a special directory in /tmp)
            return SandboxAction(S_ACTION_CONT)
        else:
            for prefix in self.READABLE_FILE_PATHS + self.WRITEABLE_FILE_PATHS:
                if path.startswith(prefix):
                    if (prefix in self.WRITEABLE_FILE_PATHS or
                                mode == O_RDONLY or
                                mode == O_RDONLY|self.O_CLOEXEC):
                        return SandboxAction(S_ACTION_CONT)
            self.error = "ILLEGAL FILE ACCESS ({0},{1})".format(path, mode)
            return self._KILL_RF(e, a)

    def SYS_unlink(self, e, a):
        pathBytes = self.sbox.dump(T_STRING, e.ext1)
        path = pathBytes.decode('utf8')
        if path.startswith('/tmp/'):
            return self._CONT(e, a)
        else:
            self.error = "Attempt to unlink {0}".format(path)
            return self._KILL_RF(e, a)


# Attempt to collapse '..' elements in a path. If not possible,
# the path is left untouched. Otherwise, the adjusted version is returned.
def collapseDotDots(path):
    bits = path.split('/')
    newBits = []
    for bit in bits:
        if bit == '..':
            if len(newBits) > 0:
                newBits = newBits[:-1]
            else:
                newBits.append(bit)
        else:
            newBits.append(bit)
    if len(newBits) == 0:
        return path
    elif len(newBits) == 1 and newBits[0] == '':
        return '/'
    else:
        return '/'.join(newBits)