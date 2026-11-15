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

/**
 * Behat data generator for CodeRunner.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Lets a feature say what prototypes exist before it starts.
 *
 * A feature whose subject is something else - a Twig prefix, a combinator
 * grader, a UI parameter - needs a prototype to exist but is not testing how
 * one is made. Building it through the editor each time would make those
 * features slow and tie them to a form they do not test. Features that *are*
 * about making a prototype still drive the editor, because that is their
 * subject.
 */
class behat_qtype_coderunner_generator extends behat_generator_base {
    /**
     * The entities a feature may ask for.
     *
     * @return array the entity specifications.
     */
    protected function get_creatable_entities(): array {
        return [
            'prototypes' => [
                'singular' => 'prototype',
                'datagenerator' => 'prototype',
                'required' => ['name'],
                'switchids' => ['course' => 'courseid'],
            ],
        ];
    }
}
