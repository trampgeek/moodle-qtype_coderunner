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
require_once($CFG->dirroot . '/question/type/coderunner/Twig/ExtensionInterface.php');
require_once($CFG->dirroot . '/question/type/coderunner/Twig/Extension.php');


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

            $twig->addExtension(new qtype_coderunner_RandomExtension);

            if (empty($twigoptions)) {
                self::$twigenvironment = $twig;
            }
        }
        return $twig;
    }
}

/** Define a Twig extension that overrides the built-in random function
 *  with one that uses mt_rand everywhere. The built-in version mostly
 *  uses mt_rand but switches to PHP's array_rand function when selecting
 *  from an array. array_rand does not allow setting of a seed, which is
 *  required by CodeRunner.
 */

class qtype_coderunner_RandomExtension extends Twig_Extension
{
    public function getFunctions() {
        return array(
            new Twig_SimpleFunction('random', 'qtype_coderunner_random',
                array('needs_environment' => true)),
            new Twig_SimpleFunction('set_random_seed', 'qtype_coderunner_set_random_seed',
                array('needs_environment' => true))
            );
    }
};


/**
 * HACKED VERSION OF THE BUILT-IN RANDOM (see above).
 * Returns a random value depending on the supplied parameter type:
 * - a random item from a Traversable or array
 * - a random character from a string
 * - a random integer between 0 and the integer parameter.
 *
 * @param Twig_Environment                   $env
 * @param Traversable|array|int|float|string $values The values to pick a random item from
 *
 * @throws Twig_Error_Runtime When $values is an empty array (does not apply to an empty string which is returned as is).
 *
 * @return mixed A random value from the given sequence
 */
function qtype_coderunner_random(Twig_Environment $env, $values = null)
{
    if (null === $values) {
        return mt_rand();
    }

    if (is_int($values) || is_float($values)) {
        return $values < 0 ? mt_rand($values, 0) : mt_rand(0, $values);
    }

    if ($values instanceof Traversable) {
        $values = iterator_to_array($values);
    } elseif (is_string($values)) {
        if ('' === $values) {
            return '';
        }
        if (null !== $charset = $env->getCharset()) {
            if ('UTF-8' !== $charset) {
                $values = twig_convert_encoding($values, 'UTF-8', $charset);
            }

            // unicode version of str_split()
            // split at all positions, but not after the start and not before the end
            $values = preg_split('/(?<!^)(?!$)/u', $values);

            if ('UTF-8' !== $charset) {
                foreach ($values as $i => $value) {
                    $values[$i] = twig_convert_encoding($value, $charset, 'UTF-8');
                }
            }
        } else {
            return $values[mt_rand(0, strlen($values) - 1)];
        }
    }

    if (!is_array($values)) {
        return $values;
    }

    if (0 === count($values)) {
        throw new Twig_Error_Runtime('The random function cannot pick from an empty array.');
    }

    // The original version did: return $values[array_rand($values, 1)];
    $keys = array_keys($values);
    $key = $keys[mt_rand(0, count($keys) - 1)];
    return $values[$key];
}

/**
 *  A hook into PHP's mt_srand function, to set the MT random number generator
 *  seed to the given value.
 *  @return '' The empty string
 */
function qtype_coderunner_set_random_seed(Twig_Environment $env, $seed)
{
    mt_srand($seed);
    return '';
}
