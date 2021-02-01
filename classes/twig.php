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
require_once $CFG->dirroot . '/question/type/coderunner/vendor/autoload.php';
require_once $CFG->dirroot . '/question/type/coderunner/classes/twigmacros.php';


// Class that provides a singleton instance of the twig environment.
class qtype_coderunner_twig {
    private static $twigenvironments = array(true => null, false => null);

    // Set up a twig loader and the twig environment. Return the
    // singleton twig loader. There are two different environments:
    // one with strict_variables true and one with it false.
    private static function get_twig_environment($isstrict=false, $isdebug=false) {
        if (self::$twigenvironments[$isstrict] === null) {
            // On the first call, build the required environment.
            $macros = qtype_coderunner_twigmacros::macros();
            $twigloader = new \Twig\Loader\ArrayLoader($macros);
            $twigoptions = array(
                'cache' => false,
                'optimisations' => 0,
                'autoescape' => false,
                'strict_variables' => $isstrict,
                'debug' => $isdebug);
            $twig = new \Twig\Environment($twigloader, $twigoptions);
            if ($isdebug) {
                $twig->addExtension(new \Twig\Extension\DebugExtension());
            }
            $newrandom = new \Twig\TwigFunction('random', 'qtype_coderunner_twig_random',
                array('needs_environment' => true));
            $setrandomseed = new \Twig\TwigFunction('set_random_seed', 'qtype_coderunner_set_random_seed',
                array('needs_environment' => true));
            $twig->addFunction($newrandom);
            $twig->addFunction($setrandomseed);

            self::$twigenvironments[$isstrict] = $twig;

            $escaperextension = $twig->getExtension(\Twig\Extension\EscaperExtension::class);
            $escaperextension->setEscaper('py', 'qtype_coderunner_escapers::python');
            $escaperextension->setEscaper('python', 'qtype_coderunner_escapers::python');
            $escaperextension->setEscaper('c',  'qtype_coderunner_escapers::java');
            $escaperextension->setEscaper('java', 'qtype_coderunner_escapers::java');
            $escaperextension->setEscaper('ml', 'qtype_coderunner_escapers::matlab');
            $escaperextension->setEscaper('matlab', 'qtype_coderunner_escapers::matlab');
        }
        return self::$twigenvironments[$isstrict];
    }


    // Render the given Twigged string with the given set of parameters, to
    // which is added the STUDENT parameter.
    // Return the Twig-expanded string.
    // Any Twig exceptions raised must be caught higher up.
    public static function render($s, $student, $parameters=array(), $isstrict=false) {
        $twig = qtype_coderunner_twig::get_twig_environment($isstrict);
        $parameters['STUDENT'] = new qtype_coderunner_student($student);
        if (array_key_exists('__twigprefix__', $parameters)) {
            $prefix = $parameters['__twigprefix__'];
            $s = $prefix . $s;
        }
        $template = $twig->createTemplate($s);
        $renderedstring = $template->render($parameters);
        return $renderedstring;
    }
}

/**
 * HACKED VERSION of Twig's built-in random. Only change is to ensure that
 * the PHP mtrand seed fully determines the results. The original version used
 * array_rand for picking a random element from an array, which does not use
 * mtrand.	
 * Returns a random value depending on the supplied parameter type:	
 * - a random item from a \Traversable or array	
 * - a random character from a string	
 * - a random integer between 0 and the integer parameter.	
 *	
 * @param \Traversable|array|int|float|string $values The values to pick a random item from	
 * @param int|null                            $max    Maximum value used when $values is an int	
 *	
 * @throws RuntimeError when $values is an empty array (does not apply to an empty string which is returned as is)	
 *	
 * @return mixed A random value from the given sequence	
 */	
function qtype_coderunner_twig_random(Twig\Environment $env, $values = null, $max = null)	
{	
    if (null === $values) {	
        return null === $max ? mt_rand() : mt_rand(0, $max);	
    }	
    if (\is_int($values) || \is_float($values)) {	
        if (null === $max) {	
            if ($values < 0) {	
                $max = 0;	
                $min = $values;	
            } else {	
                $max = $values;	
                $min = 0;	
            }	
        } else {	
            $min = $values;	
            $max = $max;	
        }	
        return mt_rand($min, $max);	
    }	
    if (\is_string($values)) {	
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
            return $values[mt_rand(0, \strlen($values) - 1)];	
        }	
    }	
    if (!twig_test_iterable($values)) {	
        return $values;	
    }	
    $values = twig_to_array($values);	
    if (0 === \count($values)) {	
        throw new RuntimeError('The random function cannot pick from an empty array.');	
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
function qtype_coderunner_set_random_seed(Twig\Environment $env, $seed)
{
    mt_srand($seed);
    return '';
}
