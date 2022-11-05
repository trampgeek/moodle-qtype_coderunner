/***********************************************************************
 *
 * Utility functions/data/constants for the ui_graph module.
 *
 ***********************************************************************/
// Most of this code is taken from Finite State Machine Designer:
/*
 Finite State Machine Designer (http://madebyevan.com/fsm/)
 License: MIT License (see below)
 Copyright (c) 2010 Evan Wallace
 Permission is hereby granted, free of charge, to any person
 obtaining a copy of this software and associated documentation
 files (the "Software"), to deal in the Software without
 restriction, including without limitation the rights to use,
 copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the
 Software is furnished to do so, subject to the following
 conditions:
 The above copyright notice and this permission notice shall be
 included in all copies or substantial portions of the Software.
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 OTHER DEALINGS IN THE SOFTWARE.
*/

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
// GNU General Public License for more util.details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


define(function() {
    /**
     * Contstructor for the Util class.
     */
    function Util() {

        this.greekLetterNames = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon',
                                'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa', 'Lambda',
                                'Mu', 'Nu', 'Xi', 'Omicron', 'Pi', 'Rho', 'Sigma',
                                'Tau', 'Upsilon', 'Phi', 'Chi', 'Psi', 'Omega' ];
    }

    Util.prototype.convertLatexShortcuts = function(text) {
        // Html greek characters.
        for(var i = 0; i < this.greekLetterNames.length; i++) {
            var name = this.greekLetterNames[i];
            text = text.replace(new RegExp('\\\\' + name, 'g'), String.fromCharCode(913 + i + (i > 16)));
            text = text.replace(new RegExp('\\\\' + name.toLowerCase(), 'g'), String.fromCharCode(945 + i + (i > 16)));
        }

        // Subscripts.
        for(var i = 0; i < 10; i++) {
            text = text.replace(new RegExp('_' + i, 'g'), String.fromCharCode(8320 + i));
        }
        text = text.replace(new RegExp('_a', 'g'), String.fromCharCode(8336));
        return text;
    };

    Util.prototype.drawArrow = function(c, x, y, angle) {
        // Draw an arrow head on the graphics context c at (x, y) with given angle.

        var dx = Math.cos(angle);
        var dy = Math.sin(angle);
        c.beginPath();
        c.moveTo(x, y);
        c.lineTo(x - 8 * dx + 5 * dy, y - 8 * dy - 5 * dx);
        c.lineTo(x - 8 * dx - 5 * dy, y - 8 * dy + 5 * dx);
        c.fill();
    };

    Util.prototype.det = function(a, b, c, d, e, f, g, h, i) {
        // Determinant of given matrix elements.
        return a * e * i + b * f * g + c * d * h - a * f * h - b * d * i - c * e * g;
    };

    Util.prototype.vectorMagnitude = function(v){
        // Returns magnitude (length) of a vector v
        return Math.sqrt(v.x * v.x + v.y * v.y);
    };

    Util.prototype.scalarProjection = function(a, b) {
        // Returns scalar projection of vector a onto vector b
        return (a.x * b.x + a.y * b.y) / this.vectorMagnitude(b);
    };

    Util.prototype.isCCW = function(a, b) {
        // Returns true iff vector b is in a counter-clockwise orientation relative to a
        return (a.x * b.y) - (b.x * a.y) > 0;
    };

    Util.prototype.circleFromThreePoints = function(x1, y1, x2, y2, x3, y3) {
        // Return {x, y, radius} of circle through (x1, y1), (x2, y2), (x3, y3).
        var a = this.det(x1, y1, 1, x2, y2, 1, x3, y3, 1);
        var bx = -this.det(x1 * x1 + y1 * y1, y1, 1, x2 * x2 + y2 * y2, y2, 1, x3 * x3 + y3 * y3, y3, 1);
        var by = this.det(x1 * x1 + y1 * y1, x1, 1, x2 * x2 + y2 * y2, x2, 1, x3 * x3 + y3 * y3, x3, 1);
        var c = -this.det(x1 * x1 + y1 * y1, x1, y1, x2 * x2 + y2 * y2, x2, y2, x3 * x3 + y3 * y3, x3, y3);
        return {
            'x': -bx / (2 * a),
            'y': -by / (2 * a),
            'radius': Math.sqrt(bx * bx + by * by - 4 * a * c) / (2 * Math.abs(a))
        };
    };

    Util.prototype.isInside = function(pos, rect) {
        // True iff given point pos is inside rectangle.
        return pos.x > rect.x && pos.x < rect.x + rect.width && pos.y < rect.y + rect.height && pos.y > rect.y;
    };

    Util.prototype.crossBrowserKey = function(e) {
        // Return which key was pressed, given the event, in a browser-independent way.
        e = e || window.event;
        return e.which || e.keyCode;
    };


    Util.prototype.crossBrowserRelativeMousePos = function(e) {
        // Earlier complex version was breaking in Moodle 4, so replaced
        // with this much simpler version that should work with all modern
        // browsers.
        const rect = e.target.getBoundingClientRect();
        const x = e.clientX - rect.left; //x position within the element.
        const y = e.clientY - rect.top;  //y position within the element.
        return {'x': x, 'y': y};
    };

    return new Util();
});
