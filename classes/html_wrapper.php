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

/** Defines a simple class used to wrap an HTML string as a way of flagging
 * to code that tries to use it that further conversion to HTML must not be done.
 *
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2016, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_coderunner_html_wrapper {

    /** @var string */
    private $html;

    public function __construct($html) {
        $this->html = $html;
    }

    // Access the wrapped html.
    public function value() {
        return $this->html;
    }
}
