'''Program to process a set of xml export files of Moodle questions,
   keeping only the category and ccode or pycode questions, replacing
   these with equivalent coderunner c_program and python2 questions
   respectively. Processes all files in the current directory that
   end in .xml but not in .cr.xml. For each such file, writes a new
   version ending in .cr.xml.
'''
import os
import re
files = os.listdir('.')
for f in files:
    if f.endswith('.xml') and not f.endswith('.cr.xml'):
        print("Doing " + f)
        lines = open(f).readlines()
        newFile = open(f[:-4] + '.cr.xml', 'w')
        inQuestion = False

        for line in lines:
            if not inQuestion:
                match = re.match('(.*<question type=")(.*)(">.*)', line)
                if match:
                    inQuestion = True
                    qType = match.group(2)
                    isKeeper = qType in ['ccode', 'pycode', 'category']
                    if isKeeper and qType != 'category':
                        codeRunnerType = {'ccode': 'c_program', 'pycode': 'python2'}[qType];
                        line = match.group(1) + 'coderunner' + match.group(3)
                        line += '    <coderunner_type>' + codeRunnerType + '''</coderunner_type>
    <all_or_nothing>1</all_or_nothing>
    <custom_template/>
'''
            if not inQuestion or isKeeper:
                newFile.write(line)
            if '</question>' in line:
                inQuestion = False;

        newFile.close()
