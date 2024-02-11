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
 * @package qtype_coderunner
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/vendor/autoload.php');
require_once($CFG->dirroot . '/question/type/coderunner/classes/twigmacros.php');


// Class that provides a singleton instance of the twig environment.
class qtype_coderunner_twig {
    private static $twigenvironments = [true => null, false => null];

    // Set up a twig loader and the twig environment. Return the
    // singleton twig loader. There are two different environments:
    // one with strict_variables true and one with it false.
    private static function get_twig_environment($isstrict = false, $isdebug = false) {
        if (self::$twigenvironments[$isstrict] === null) {
            // On the first call, build the required environment.
            $macros = qtype_coderunner_twigmacros::macros();
            $twigloader = new \Twig\Loader\ArrayLoader($macros);
            $twigoptions = [
                'cache' => false,
                'optimisations' => 0,
                'autoescape' => false,
                'strict_variables' => $isstrict,
                'debug' => $isdebug];
            $twig = new \Twig\Environment($twigloader, $twigoptions);
            $policy = self::get_policy();
            $twig->addExtension(new \Twig\Extension\SandboxExtension($policy, true));
            if ($isdebug) {
                $twig->addExtension(new \Twig\Extension\DebugExtension());
            }

            // Add some functions to twig: random (modified to use seed), randomseed, shuffle.
            $newrandom = new \Twig\TwigFunction(
                'random',
                'qtype_coderunner_twig_random',
                ['needs_environment' => true]
            );
            $setrandomseed = new \Twig\TwigFunction(
                'set_random_seed',
                'qtype_coderunner_set_random_seed',
                ['needs_environment' => true]
            );
            $twig->addFunction($newrandom);
            $twig->addFunction($setrandomseed);
            $shuffle = new \Twig\TwigFilter(
                'shuffle',
                'qtype_coderunner_twig_shuffle',
                ['needs_environment' => true]
            );
            $twig->addFilter($shuffle);
            self::$twigenvironments[$isstrict] = $twig;

            // Set various escapers for the different languages.
            $escaperextension = $twig->getExtension(\Twig\Extension\EscaperExtension::class);
            $escaperextension->setEscaper('py', 'qtype_coderunner_escapers::python');
            $escaperextension->setEscaper('python', 'qtype_coderunner_escapers::python');
            $escaperextension->setEscaper('c', 'qtype_coderunner_escapers::java');
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
    // Since Twig range functions can result in PHP ValueError being thrown (grr)
    // ValueErrors are caught and re-thrown as TwigErrors.
    public static function render($s, $student, $parameters = [], $isstrict = false) {
        if ($s === null || trim($s) === '') {
            return '';
        }
        $twig = self::get_twig_environment($isstrict);
        $parameters['STUDENT'] = new qtype_coderunner_student($student);
        if (array_key_exists('__twigprefix__', $parameters)) {
            $prefix = $parameters['__twigprefix__'];
            $s = $prefix . $s;
        }
        $template = $twig->createTemplate($s);
        try {
            $renderedstring = $template->render($parameters);
        } catch (ValueError $e) {
            throw new \Twig\Error\Error("Twig error: " . $e->getMessage());
        }
        return $renderedstring;
    }


    // Return a security policy object for Twig. This version whitelists
    // all "reasonable" filters, functions and attributes.
    private static function get_policy() {
        $tags = ['apply', 'block', 'cache', 'deprecated', 'do', 'embed', 'extends',
            'flush', 'for', 'from', 'if', 'import', 'include', 'macro', 'set',
            'use', 'verbatim', 'with'];
        $filters = ['abs', 'batch', 'capitalize', 'column', 'convert_encoding',
            'country_name', 'currency_name', 'currency_symbol', 'data_uri',
            'date', 'date_modify', 'default', 'e', 'escape', 'filter', 'first',
            'format', 'format_currency', 'format_date', 'format_datetime',
            'format_number', 'format_time', 'html_to_markdown', 'inky_to_html',
            'inline_css', 'join', 'json_encode', 'keys', 'language_name',
            'last', 'length', 'locale_name', 'lower', 'map', 'markdown_to_html',
            'merge', 'nl2br', 'number_format', 'raw', 'reduce', 'replace',
            'reverse', 'round', 'shuffle', 'slice', 'slug', 'sort', 'spaceless', 'split',
            'striptags', 'timezone_name', 'title', 'trim', 'u', 'upper',
            'url_encode'];
        $functions = ['attribute', 'block', 'constant', 'country_timezones',
            'cycle', 'date', 'dump', 'html_classes', 'include', 'max', 'min',
            'parent', 'random', 'range', 'source', 'template_from_string',
            'set_random_seed'];
        $methods = [
            'stdClass' => [],
            'qtype_coderunner_student' => '*',
        ];
        $properties = [
            'stdClass' => '*',
            'qtype_coderunner_student' => '*',
            'qtype_coderunner_question' => '*',
        ];
        $policy = new qtype_coderunner_twig_security_policy($tags, $filters, $methods, $properties, $functions);
        return $policy;
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
function qtype_coderunner_twig_random(Twig\Environment $env, $values = null, $max = null) {
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
            // Unicode version of str_split().
            // Split at all positions, but not after the start and not before the end.
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

    $keys = array_keys($values);
    $key = $keys[mt_rand(0, count($keys) - 1)];
    return $values[$key];
}

/**
 * A hook into PHP's mt_srand function, to set the MT random number generator
 * seed to the given value.
 * @return '' The empty string
 */
function qtype_coderunner_set_random_seed(Twig\Environment $env, $seed) {
    mt_srand($seed);
    return '';
}

/**
 * The shuffle function for Twig. Randomises order of element an array.
 * @param array $array The data to shuffle.
 * @return The shuffled $array.
 */
function qtype_coderunner_twig_shuffle(Twig\Environment $env, $array) {
    shuffle($array);
    return $array;
}
