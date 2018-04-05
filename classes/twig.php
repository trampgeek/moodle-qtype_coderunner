<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Twig environment for CodeRunner
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/Twig/Autoloader.php');

// Class that provides a singleton instance of the twig environment.
class qtype_coderunner_twig {
    private static $twigenvironment = null;

    // Set up a twig loader and the twig environment. Return the
    // singleton twig loader. If $twigoptions is empty returns a standard
    // twig environment that will be reused by other calls (a singleton).
    // Otherwise set up and returns a custom version.
    public static function get_twig_environment($twigoptions=array()) {
        if (self::$twigenvironment && empty($twigoptions)) {
            $twig = self::$twigenvironment;
        } else {
            Twig_Autoloader::register();
            $twigloader = new Twig_Loader_String();
            if (!isset($twigoptions['optimizations'])) {
                $twigoptions['optimizations'] = 0;
            }
            if (!isset($twigoptions['autoescape'])) {
                $twigoptions['autoescape'] = false;
            }
            $twig = new Twig_Environment($twigloader, $twigoptions);
            if (isset($twigoptions['debug']) && $twigoptions['debug']) {
                $twig->addExtension(new Twig_Extension_Debug());
            }

            $twigcore = $twig->getExtension('core');
            $twigcore->setEscaper('py', 'qtype_coderunner_escapers::python');
            $twigcore->setEscaper('python', 'qtype_coderunner_escapers::python');
            $twigcore->setEscaper('c',  'qtype_coderunner_escapers::java');
            $twigcore->setEscaper('java', 'qtype_coderunner_escapers::java');
            $twigcore->setEscaper('ml', 'qtype_coderunner_escapers::matlab');
            $twigcore->setEscaper('matlab', 'qtype_coderunner_escapers::matlab');

            if (empty($twigoptions)) {
                self::$twigenvironment = $twig;
            }
        }
        return $twig;
    }
}