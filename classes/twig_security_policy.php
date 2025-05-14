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

/* A sandbox that uses the remote ideone.com compute server to run
 * student submissions. This is completely safe but gives a poor turn-around,
 * which can be up to a minute. It was developed as a proof of concept of
 * the idea of a remote sandbox and is not recommended for general purpose use.
 *
 * @package    qtype_coderunner
 * @copyright  2012, 2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// A tweaked version of the Twig SecurityPolicy class to allow '*' as
// a value fpr allowed properties and methods of an object.

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @package    qtype_coderunner
 */


use Twig\Markup;
use Twig\Template;

/**
 * Represents a security policy which need to be enforced when sandbox mode is enabled.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class qtype_coderunner_twig_security_policy implements Twig\Sandbox\SecurityPolicyInterface {

    /** @var array */
    private $allowedTags;

    /** @var array */
    private $allowedFilters;

    /** @var array */
    private $allowedMethods;

    /** @var array */
    private $allowedProperties;

    /** @var array */
    private $allowedFunctions;

    public function __construct(
        array $allowedtags = [],
        array $allowedfilters = [],
        array $allowedmethods = [],
        array $allowedproperties = [],
        array $allowedfunctions = []
    ) {
        $this->allowedTags = $allowedtags;
        $this->allowedFilters = $allowedfilters;
        $this->setAllowedMethods($allowedmethods);
        $this->allowedProperties = $allowedproperties;
        $this->allowedFunctions = $allowedfunctions;
    }

    public function setallowedtags(array $tags): void {
        $this->allowedTags = $tags;
    }

    public function setallowedfilters(array $filters): void {
        $this->allowedFilters = $filters;
    }

    public function setallowedmethods(array $methods): void {
        $this->allowedMethods = [];
        foreach ($methods as $class => $m) {
            // Do not convert wildcard string to array.
            if ($m === '*') {
                $this->allowedMethods[$class] = $m;
                continue;
            }
            $this->allowedMethods[$class] = array_map(function ($value) {
                return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            }, \is_array($m) ? $m : [$m]);
        }
    }

    public function setallowedproperties(array $properties): void {
        $this->allowedProperties = $properties;
    }

    public function setallowedfunctions(array $functions): void {
        $this->allowedFunctions = $functions;
    }

    public function checksecurity($tags, $filters, $functions): void {
        foreach ($tags as $tag) {
            if (!\in_array($tag, $this->allowedTags)) {
                throw new Twig\Sandbox\SecurityNotAllowedTagError(sprintf('Tag "%s" is not allowed.', $tag), $tag);
            }
        }

        foreach ($filters as $filter) {
            if (!\in_array($filter, $this->allowedFilters)) {
                throw new Twig\Sandbox\SecurityNotAllowedFilterError(sprintf('Filter "%s" is not allowed.', $filter), $filter);
            }
        }

        foreach ($functions as $function) {
            if (!\in_array($function, $this->allowedFunctions)) {
                throw new Twig\Sandbox\SecurityNotAllowedFunctionError(
                    sprintf('Function "%s" is not allowed.', $function),
                    $function
                );
            }
        }
    }

    public function checkmethodallowed($obj, $method): void {
        if ($obj instanceof Template || $obj instanceof Markup) {
            return;
        }

        $allowed = false;
        $method = strtr($method, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
        foreach ($this->allowedMethods as $class => $methods) {
            if ($obj instanceof $class) {
                $allowed = $methods === '*' || \in_array($method, $methods);

                break;
            }
        }

        if (!$allowed) {
            $class = \get_class($obj);
            throw new Twig\Sandbox\SecurityNotAllowedMethodError(
                sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, $class),
                $class,
                $method
            );
        }
    }

    public function checkpropertyallowed($obj, $property): void {
        $allowed = false;
        foreach ($this->allowedProperties as $class => $properties) {
            if ($obj instanceof $class) {
                $allowed = $properties === '*' || (\in_array($property, \is_array($properties) ? $properties : [$properties]));

                break;
            }
        }

        if (!$allowed) {
            $class = \get_class($obj);
            throw new Twig\Sandbox\SecurityNotAllowedPropertyError(
                sprintf('Calling "%s" property on a "%s" object is not allowed.', $property, $class),
                $class,
                $property
            );
        }
    }
}
