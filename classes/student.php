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
 * Student class to access user details without exposing all properties of global $USER.
 *
 * @package    qtype_coderunner
 * @copyright  2017 David Bowes <d.h.bowes@herts.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_coderunner_student {

    /** @var int */
    public $id;

    /** @var string */
    public $username;

    /** @var string */
    public $email;

    /** @var string */
    public $firstname;

    /** @var string */
    public $lastname;

    /** @var bool Whether the user can view hidden test cases. */
    public $canviewhidden;

    public function __construct($user) {
        if (!empty($user->username)) {
            $this->id        = $user->id;
            $this->username  = $user->username;
            $this->email     = $user->email;
            $this->firstname = $user->firstname;
            $this->lastname  = $user->lastname;
            $this->canviewhidden = qtype_coderunner_testing_outcome::can_view_hidden();
        }
    }
}
