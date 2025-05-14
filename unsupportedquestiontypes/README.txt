This folder contains miscellaneous custom question types, mostly ones in use
in the University of Canterbury's (UC's) introductory Python programming
course, COSC131. They are not part of CodeRunner itself (unlike the built-in
question types) and are unsupported. Use them at your own risk.

The prototypes folder contains the question types themselves while the examples
folder contains at least one example of each question type.

To use the question types you can import the prototypes into your question bank 
(they're in Moodle XML format), then open the associated example(s) for editing
or previewing. When editing an example, you can expand the Show Details panel
to find out how the question type works. 

Most of the question types will require additional software to be installed
on the Jobe server. The Ruff style checker is used by most UC question
types, as are the Python numpy, matplotlib and scipy packages.

The dotnet C3 question type has had hardly any testing and has never been
used in a production environment. You will need to have installed the
dotnet package on your jobe server (sudo apt-get install dotnet-sdk-8.0).
It comes with two major additional warnings:
(1) Performance is very poor, because it takes around 2 seconds to compile
    a C# program. Use of this question type in a test or exam is likely
    to be problematic except with very small classes or with large jobe
    server pools.
(2) Dotnet does not play well with the usual Jobe 'ulimit' resource
    limitations, so the memory limit and disklimit (amount of disk i/o)
    have both been disabled. It is potentially possible for a rogue
    task to disable the Jobe server by exceeding these limits, although
    the watchdog timer should kill the job within around 10 seconds and
    the server should then recover. This theory has not been tested
    in practice.
Caveat emptor!
