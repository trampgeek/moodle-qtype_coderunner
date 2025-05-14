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
 * Macros for the Twig environment.
 * @package    qtype_coderunner
 */

// Class that simply provides a static method to supply the template
// of macros for the Twig_Loader_Array() class.
class qtype_coderunner_twigmacros {
    public static function macros() {
        $htmlmacros = <<<EOMACROS
{% macro input(name, size = 10) %}
<input type="text" name="crui_{{ name }}" size="{{ size }}" class="coderunner-ui-element" />{% endmacro %}

{% macro select(name, options) %}
<select name="crui_{{ name }}" class="coderunner-ui-element">
{% for option in options %}
{% if option[0] is not defined %}
   <option value="{{ option }}">{{ option }}</option>
{% else %}
   <option value="{{ option[0] }}">{{ option[1] }}</option>
{% endif %}
{% endfor %}
</select>{% endmacro %}

{% macro radio(name, items) %}
{% for item in items %}
{% if item[0] is not defined %}
   <label>
   <input type="radio" name="crui_{{ name }}" class="coderunner-ui-element" value="{{ item }}" style="margin-right:10px;">{{ item }}
   </label>
   <br>
{% else %}
   <label>
   <input type="radio" name="crui_{{ name }}" class="coderunner-ui-element" value="{{ item[0] }}">{{ item[1] }}
   </label>
{% endif %}
{% endfor %}
{% endmacro %}

{% macro checkbox(name, label, ischecked) %}
<label>{{ label }}
<input type="checkbox" name="crui_{{ name }}" class="coderunner-ui-element"{% if ischecked %} checked{%endif%}>
</label>
{%endmacro %}

{% macro textarea(name, rows=2, cols=60) %}
<textarea name="crui_{{ name }}" rows="{{ rows }}" cols="{{ cols }}" class="coderunner-ui-element"></textarea>{% endmacro %}

EOMACROS;
        return ['html' => $htmlmacros];
    }
}
