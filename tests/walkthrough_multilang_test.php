<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.



namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');


/**
 * A walkthrough of a simple multilanguage question that asks for a program
 * that echos stdin to stdout. Tests all languages supported by the current
 * multilanguage question type: C, C++, Java, Python3
 * @group qtype_coderunner
 * @coversNothing
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2018 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class walkthrough_multilang_test extends \qbehaviour_walkthrough_test_base {
    protected function setUp(): void {
        parent::setUp();
        \qtype_coderunner_testcase::setup_test_sandbox_configuration();
    }

    public function test_echostdin() {

        $answers = [
            'python3' => "try:\n    while 1:\n        print(input())\n\nexcept:\n    pass\n",
            'c' => "#include <stdio.h>\nint main() { int c; while ((c = getchar()) != EOF) { putchar(c); }}",
            'cpp' => "#include <iostream>\nint main () { std::cout << std::cin.rdbuf();}",
            'java' => "import java.io.IOException;
public class InOut {
    public static void main(String[] args) throws IOException {
        byte[] buffer = new byte[8192];
        while (true) {
            int bytesRead = System.in.read(buffer);
            if (bytesRead == -1) {
                return;
            }
            System.out.write(buffer, 0, bytesRead);
        }
    }
}",
        ];
        $q = \test_question_maker::make_question('coderunner', 'multilang_echo_stdin');

        // Submit a right answer in all languages.
        foreach ($answers as $lang => $answer) {
            $this->start_attempt_at_question($q, 'adaptive', 1, 1);
            $this->process_submission(
                [
                        '-submit'  => 1,
                        'answer'   => $answer,
                        'language' => $lang,
                    ]
            );
            $this->check_current_mark(1.0);
        }
    }
}
