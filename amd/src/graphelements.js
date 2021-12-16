/******************************************************************************
 *
 * A module for use by ui_graph, defining classes Node, Link, SelfLink,
 * StartLink and TemporaryLink
 *
 * @module qtype_coderunner/graphelements
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This code is a modified version of Finite State Machine Designer
// (http://madebyevan.com/fsm/)
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


define(['qtype_coderunner/graphutil'], function(util) {

    /**
     * Define a class Node that represents a node in a graph
     * @param {object} parent The Graph to which this node belongs.
     * @param {int} x The x-coordinate of the node.
     * @param {int} y The y-coordinate of the node.
     *
     */
    function Node(parent, x, y) {
        this.parent = parent;  // The ui_graph instance.
        this.x = x;
        this.y = y;
        this.mouseOffsetX = 0;
        this.mouseOffsetY = 0;
        this.isAcceptState = false;
        this.textBox = new TextBox('', this);
        this.caretPosition = 0;
    }

    // At the start of a drag, record our position relative to the mouse.
    Node.prototype.setMouseStart = function(mouseX, mouseY) {
        this.mouseOffsetX = this.x - mouseX;
        this.mouseOffsetY = this.y - mouseY;
    };

    Node.prototype.setAnchorPoint = function(x, y) {
        this.x = x + this.mouseOffsetX;
        this.y = y + this.mouseOffsetY;
    };

    // Given a new mouse position during a drag, move to the appropriate
    // new position.
    Node.prototype.trackMouse = function(mouseX, mouseY) {
        this.x = this.mouseOffsetX + mouseX;
        this.y = this.mouseOffsetY + mouseY;
    };

    Node.prototype.draw = function(c) {
        // Draw the circle.
        c.beginPath();
        c.arc(this.x, this.y, this.parent.nodeRadius(), 0, 2 * Math.PI, false);
        c.stroke();

        // Draw the text.
        this.textBox.draw(this.x, this.y, null, this);

        // Draw a double circle for an accept state.
        if(this.isAcceptState) {
            c.beginPath();
            c.arc(this.x, this.y, this.parent.nodeRadius() - 6, 0, 2 * Math.PI, false);
            c.stroke();
        }
    };

    Node.prototype.closestPointOnCircle = function(x, y) {
        var dx = x - this.x;
        var dy = y - this.y;
        var scale = Math.sqrt(dx * dx + dy * dy);
        return {
            'x': this.x + dx * this.parent.nodeRadius() / scale,
            'y': this.y + dy * this.parent.nodeRadius() / scale,
        };
    };

    Node.prototype.containsPoint = function(x, y) {
        return (x - this.x) * (x - this.x) + (y - this.y) * (y - this.y) < this.parent.nodeRadius() * this.parent.nodeRadius();
    };

    // Method of a Node that, given a list of all links in a graph, returns
    // a list of any nodes that contain a link to this node (excluding StartLinks
    // and SelfLinks).
    Node.prototype.neighbours = function(links) {
        var neighbours = [], link;
        for (var i = 0; i < links.length; i++) {
            link = links[i];
            if (link instanceof Link) { // Exclude SelfLinks and StartLinks.
                if (link.nodeA === this && !neighbours.includes(link.nodeB)) {
                    neighbours.push(link.nodeB);
                } else if (link.nodeB === this && !neighbours.includes(link.nodeA)) {
                    neighbours.push(link.nodeA);
                }
            }
        }
        return neighbours;
    };

    // Method of Node that traverses a graph defined by a given set of links
    // starting at 'this' node and updating the visited list for each new
    // node. Returns the updated visited list, which (for the root call)
    // is a list of all nodes connected to the given start node.
    Node.prototype.traverseGraph = function(links, visited) {
        var neighbours,
            neighbour;
        if (!visited.includes(this)) {
            visited.push(this);
            neighbours = this.neighbours(links);
            for (var i = 0; i < neighbours.length; i++) {
                neighbour = neighbours[i];
                if (!visited.includes(neighbour)) {
                    neighbour.traverseGraph(links, visited);
                }
            }
        }
        return visited;
    };

    /**
     * Define a class Link that represents a connection between two nodes.
     * @param {object} parent The graph to which this link belongs.
     * @param {object} a The node at one end of the link.
     * @param {object} b The node at the other end of the link.
     *
     */
    function Link(parent, a, b) {
        this.parent = parent;  // The parent ui_digraph instance.
        this.nodeA = a;
        this.nodeB = b;
        this.textBox = new TextBox('', this);
        this.lineAngleAdjust = 0; // Value to add to textAngle when link is straight line.
        this.caretPosition = 0;

        // Make anchor point relative to the locations of nodeA and nodeB.
        this.parallelPart = 0.5;    // Percentage from nodeA to nodeB.
        this.perpendicularPart = 0; // Pixels from line between nodeA and nodeB.
    }

    Link.prototype.getAnchorPoint = function() {
        var dx = this.nodeB.x - this.nodeA.x;
        var dy = this.nodeB.y - this.nodeA.y;
        var scale = Math.sqrt(dx * dx + dy * dy);
        return {
            'x': this.nodeA.x + dx * this.parallelPart - dy * this.perpendicularPart / scale,
            'y': this.nodeA.y + dy * this.parallelPart + dx * this.perpendicularPart / scale
        };
    };

    Link.prototype.setAnchorPoint = function(x, y) {
        var dx = this.nodeB.x - this.nodeA.x;
        var dy = this.nodeB.y - this.nodeA.y;
        var scale = Math.sqrt(dx * dx + dy * dy);
        this.parallelPart = (dx * (x - this.nodeA.x) + dy * (y - this.nodeA.y)) / (scale * scale);
        this.perpendicularPart = (dx * (y - this.nodeA.y) - dy * (x - this.nodeA.x)) / scale;
        // Snap to a straight line.
        if(this.parallelPart > 0 && this.parallelPart < 1 && Math.abs(this.perpendicularPart) < this.parent.SNAP_TO_PADDING) {
            this.lineAngleAdjust = (this.perpendicularPart < 0) * Math.PI;
            this.perpendicularPart = 0;
        }
    };

    Link.prototype.getEndPointsAndCircle = function() {
        if(this.perpendicularPart === 0) {
            var midX = (this.nodeA.x + this.nodeB.x) / 2;
            var midY = (this.nodeA.y + this.nodeB.y) / 2;
            var start = this.nodeA.closestPointOnCircle(midX, midY);
            var end = this.nodeB.closestPointOnCircle(midX, midY);
            return {
                'hasCircle': false,
                'startX': start.x,
                'startY': start.y,
                'endX': end.x,
                'endY': end.y,
            };
        }
        var anchor = this.getAnchorPoint();
        var circle = util.circleFromThreePoints(this.nodeA.x, this.nodeA.y, this.nodeB.x, this.nodeB.y, anchor.x, anchor.y);
        var isReversed = (this.perpendicularPart > 0);
        var reverseScale = isReversed ? 1 : -1;
        var rRatio = reverseScale * this.parent.nodeRadius() / circle.radius;
        var startAngle = Math.atan2(this.nodeA.y - circle.y, this.nodeA.x - circle.x) - rRatio;
        var endAngle = Math.atan2(this.nodeB.y - circle.y, this.nodeB.x - circle.x) + rRatio;
        var startX = circle.x + circle.radius * Math.cos(startAngle);
        var startY = circle.y + circle.radius * Math.sin(startAngle);
        var endX = circle.x + circle.radius * Math.cos(endAngle);
        var endY = circle.y + circle.radius * Math.sin(endAngle);
        return {
            'hasCircle': true,
            'startX': startX,
            'startY': startY,
            'endX': endX,
            'endY': endY,
            'startAngle': startAngle,
            'endAngle': endAngle,
            'circleX': circle.x,
            'circleY': circle.y,
            'circleRadius': circle.radius,
            'reverseScale': reverseScale,
            'isReversed': isReversed,
        };
    };

    Link.prototype.draw = function(c) {
        var linkInfo = this.getEndPointsAndCircle(), textX, textY, textAngle, relDist;
        // Draw arc.
        c.beginPath();
        if(linkInfo.hasCircle) {
            c.arc(linkInfo.circleX,
                  linkInfo.circleY,
                  linkInfo.circleRadius,
                  linkInfo.startAngle,
                  linkInfo.endAngle,
                  linkInfo.isReversed);
        } else {
            c.moveTo(linkInfo.startX, linkInfo.startY);
            c.lineTo(linkInfo.endX, linkInfo.endY);
        }
        c.stroke();
        // Draw the head of the arrow.
        if(linkInfo.hasCircle) {
            this.parent.arrowIfReqd(c,
                      linkInfo.endX,
                      linkInfo.endY,
                      linkInfo.endAngle - linkInfo.reverseScale * (Math.PI / 2));
        } else {
            this.parent.arrowIfReqd(c,
                      linkInfo.endX,
                      linkInfo.endY,
                      Math.atan2(linkInfo.endY - linkInfo.startY, linkInfo.endX - linkInfo.startX));
        }
        // Draw the text.
        relDist = this.textBox.relDist;
        if(linkInfo.hasCircle) {
            var startAngle = linkInfo.startAngle;
            var endAngle = linkInfo.endAngle;
            if (endAngle < startAngle) {
                endAngle += Math.PI * 2;
            }

            textAngle = ((1 - relDist) * startAngle + relDist * endAngle);
            if (linkInfo.isReversed){
              textAngle += (1 - relDist) * (2 * Math.PI); // Reflect text across the line between the link points
            }
            textX = linkInfo.circleX + linkInfo.circleRadius * Math.cos(textAngle);
            textY = linkInfo.circleY + linkInfo.circleRadius * Math.sin(textAngle);
            this.textBox.draw(textX, textY, textAngle, this);
        } else {
            textX = ((1 - relDist) * linkInfo.startX + relDist * linkInfo.endX);
            textY = ((1 - relDist) * linkInfo.startY + relDist * linkInfo.endY);
            textAngle = Math.atan2(linkInfo.endX - linkInfo.startX, linkInfo.startY - linkInfo.endY);
            this.textBox.draw(textX, textY, textAngle + this.lineAngleAdjust, this);
        }
    };

    Link.prototype.containsPoint = function(x, y) {
        var linkInfo = this.getEndPointsAndCircle(), dx, dy, distance;
        if(linkInfo.hasCircle) {
            dx = x - linkInfo.circleX;
            dy = y - linkInfo.circleY;
            distance = Math.sqrt(dx * dx + dy * dy) - linkInfo.circleRadius;
            if(Math.abs(distance) < this.parent.HIT_TARGET_PADDING) {
                var angle = Math.atan2(dy, dx);
                var startAngle = linkInfo.startAngle;
                var endAngle = linkInfo.endAngle;
                if(linkInfo.isReversed) {
                    var temp = startAngle;
                    startAngle = endAngle;
                    endAngle = temp;
                }
                if(endAngle < startAngle) {
                    endAngle += Math.PI * 2;
                }
                if(angle < startAngle) {
                    angle += Math.PI * 2;
                } else if(angle > endAngle) {
                    angle -= Math.PI * 2;
                }
                return (angle > startAngle && angle < endAngle);
            }
        } else {
            dx = linkInfo.endX - linkInfo.startX;
            dy = linkInfo.endY - linkInfo.startY;
            var length = Math.sqrt(dx * dx + dy * dy);
            var percent = (dx * (x - linkInfo.startX) + dy * (y - linkInfo.startY)) / (length * length);
            distance = (dx * (y - linkInfo.startY) - dy * (x - linkInfo.startX)) / length;
            return (percent > 0 && percent < 1 && Math.abs(distance) < this.parent.HIT_TARGET_PADDING);
        }
        return false;
    };

    /**
     * Define a class SelfLink that represents a connection from a node back
     * to itself.
     * @param {object} parent The graph to which this link belongs.
     * @param {object} node The node the link emerges from and returns to.
     * @param {object} mouse The current position of the mouse that's defining
     * the position of the self-link.
     */
    function SelfLink(parent, node, mouse) {
        this.parent = parent;
        this.node = node;
        this.anchorAngle = 0;
        this.mouseOffsetAngle = 0;
        this.textBox = new TextBox('', this);

        if(mouse) {
            this.setAnchorPoint(mouse.x, mouse.y);
        }
    }

    SelfLink.prototype.setMouseStart = function(x, y) {
        this.mouseStartX = x;
        this.mouseStartY = y;
    };

    SelfLink.prototype.setAnchorPoint = function(x, y) {
        this.anchorAngle = Math.atan2(y - this.node.y, x - this.node.x) + this.mouseOffsetAngle;
        // Snap to 90 degrees.
        var snap = Math.round(this.anchorAngle / (Math.PI / 2)) * (Math.PI / 2);
        if(Math.abs(this.anchorAngle - snap) < 0.1) {
            this.anchorAngle = snap;
        }
        // Keep in the range -pi to pi so our containsPoint() function always works.
        if(this.anchorAngle < -Math.PI) {
            this.anchorAngle += 2 * Math.PI;
        }
        if(this.anchorAngle > Math.PI) {
            this.anchorAngle -= 2 * Math.PI;
        }
    };

    SelfLink.prototype.getEndPointsAndCircle = function() {
        var circleX = this.node.x + 1.5 * this.parent.nodeRadius() * Math.cos(this.anchorAngle);
        var circleY = this.node.y + 1.5 * this.parent.nodeRadius() * Math.sin(this.anchorAngle);
        var circleRadius = 0.75 * this.parent.nodeRadius();
        var startAngle = this.anchorAngle - Math.PI * 0.8;
        var endAngle = this.anchorAngle + Math.PI * 0.8;
        var startX = circleX + circleRadius * Math.cos(startAngle);
        var startY = circleY + circleRadius * Math.sin(startAngle);
        var endX = circleX + circleRadius * Math.cos(endAngle);
        var endY = circleY + circleRadius * Math.sin(endAngle);
        return {
            'hasCircle': true,
            'startX': startX,
            'startY': startY,
            'endX': endX,
            'endY': endY,
            'startAngle': startAngle,
            'endAngle': endAngle,
            'circleX': circleX,
            'circleY': circleY,
            'circleRadius': circleRadius
        };
    };

    SelfLink.prototype.draw = function(c) {
        var linkInfo = this.getEndPointsAndCircle();
        // Draw arc.
        c.beginPath();
        c.arc(linkInfo.circleX, linkInfo.circleY, linkInfo.circleRadius, linkInfo.startAngle, linkInfo.endAngle, false);
        c.stroke();
        // Draw the text on the loop.
        var relDist = this.textBox.relDist;
        var textAngle = linkInfo.startAngle * (1 - relDist) + linkInfo.endAngle * relDist;
        var textX = linkInfo.circleX + linkInfo.circleRadius * Math.cos(textAngle);
        var textY = linkInfo.circleY + linkInfo.circleRadius * Math.sin(textAngle);
        this.textBox.draw(textX, textY, textAngle, this);
        // Draw the head of the arrow.
        this.parent.arrowIfReqd(c, linkInfo.endX, linkInfo.endY, linkInfo.endAngle + Math.PI * 0.4);
    };

    SelfLink.prototype.containsPoint = function(x, y) {
        var linkInfo = this.getEndPointsAndCircle();
        var dx = x - linkInfo.circleX;
        var dy = y - linkInfo.circleY;
        var distance = Math.sqrt(dx * dx + dy * dy) - linkInfo.circleRadius;
        return (Math.abs(distance) < this.parent.HIT_TARGET_PADDING);
    };

    /**
     * Define a class StartLink that represents a start link in a finite
     * state machine. Not useful in general digraphs.
     * @param {object} parent The graph to which this link belongs.
     * @param {node} node The node that the link leads into.
     * @param {object} start The point at the open end of the link.
     */
    function StartLink(parent, node, start) {
        this.parent = parent;
        this.node = node;
        this.deltaX = 0;
        this.deltaY = 0;

        if(start) {
            this.setAnchorPoint(start.x, start.y);
        }
    }

    StartLink.prototype.setAnchorPoint = function(x, y) {
        this.deltaX = x - this.node.x;
        this.deltaY = y - this.node.y;

        if(Math.abs(this.deltaX) < this.parent.SNAP_TO_PADDING) {
            this.deltaX = 0;
        }

        if(Math.abs(this.deltaY) < this.parent.SNAP_TO_PADDING) {
            this.deltaY = 0;
        }
    };

    StartLink.prototype.getEndPoints = function() {
        var startX = this.node.x + this.deltaX;
        var startY = this.node.y + this.deltaY;
        var end = this.node.closestPointOnCircle(startX, startY);
        return {
            'startX': startX,
            'startY': startY,
            'endX': end.x,
            'endY': end.y,
        };
    };

    StartLink.prototype.draw = function(c) {
        var endPoints = this.getEndPoints();

        // Draw the line.
        c.beginPath();
        c.moveTo(endPoints.startX, endPoints.startY);
        c.lineTo(endPoints.endX, endPoints.endY);
        c.stroke();

        // Draw the head of the arrow.
        this.parent.arrowIfReqd(c, endPoints.endX, endPoints.endY, Math.atan2(-this.deltaY, -this.deltaX));
    };

    StartLink.prototype.containsPoint = function(x, y) {
        var endPoints = this.getEndPoints();
        var dx = endPoints.endX - endPoints.startX;
        var dy = endPoints.endY - endPoints.startY;
        var length = Math.sqrt(dx * dx + dy * dy);
        var percent = (dx * (x - endPoints.startX) + dy * (y - endPoints.startY)) / (length * length);
        var distance = (dx * (y - endPoints.startY) - dy * (x - endPoints.startX)) / length;
        return (percent > 0 && percent < 1 && Math.abs(distance) < this.parent.HIT_TARGET_PADDING);
    };

    /**
     * Define a class TemporaryLink that represents a link that's in the
     * process of being created.
     * @param {object} parent The graph to which this link belongs.
     * @param {object} from The node the link starts at.
     * @param {object} to The node the link goes to.
     */
    function TemporaryLink(parent, from, to) {
        this.parent = parent;
        this.from = from;
        this.to = to;
    }

    TemporaryLink.prototype.draw = function(c) {
        // Draw the line.
        c.beginPath();
        c.moveTo(this.to.x, this.to.y);
        c.lineTo(this.from.x, this.from.y);
        c.stroke();

        // Draw the head of the arrow.
        this.parent.arrowIfReqd(c, this.to.x, this.to.y, Math.atan2(this.to.y - this.from.y, this.to.x - this.from.x));
    };

    /**
     * Define a class Button for a pseudo-menu button.
     * @param {object} parent The graph to which this button belongs.
     * @param {int} topX The x coordinate of the top left corner of the menu text.
     * @param {int} topY The y coordinate of the top left corner of the menu text.
     * @param {string} text The button label text.
     */
    function Button(parent, topX, topY, text) {
      this.BUTTON_WIDTH = 60;
      this.BUTTON_HEIGHT = 25;
      this.TEXT_OFFSET_X = 30;
      this.TEXT_OFFSET_Y = 17;
      this.topX = topX;
      this.topY = topY;
      this.parent = parent;
      this.text = text;
      this.highLighted = false;
    }

    Button.prototype.containsPoint = function(x, y) {
        return util.isInside({x: x, y: y},
          {x: this.topX, y: this.topY, width: this.BUTTON_WIDTH, height: this.BUTTON_HEIGHT});
    };

    Button.prototype.draw = function(c) {
        if (this.highLighted) {
            c.fillStyle = '#FFFFFF';
        } else {
            c.fillStyle = '#F0F0F0';
        }
        c.fillRect(this.topX, this.topY,
            this.BUTTON_WIDTH, this.BUTTON_HEIGHT);
        c.lineWidth = 0.5;
        c.strokeStyle = '#000000';
        c.strokeRect(this.topX, this.topY,
            this.BUTTON_WIDTH, this.BUTTON_HEIGHT);

        c.font = '12pt Arial';
        c.fillStyle = '#000000';
        c.textAlign = "center";
        c.fillText(this.text, this.topX + this.TEXT_OFFSET_X, this.topY + this.TEXT_OFFSET_Y);
        c.textAlign = "left";
    };

    Button.prototype.onClick = function() {

    };

    /**
     * Define a class HelpBox for the help box and its pseudo-menu button.
     * @param {object} parent The graph to which this help box belongs.
     * @param {int} topX The x coordinate of the top left corner of the help box.
     * @param {int} topY The y coordinate of the top left corner of the help box.
     */
    function HelpBox(parent, topX, topY) {
      Button.call(this, parent, topX, topY, "Help");
      this.helpOpen = false;
      this.LINE_HEIGHT = 18;
      this.HELP_INDENT = 5;
    }

    HelpBox.prototype = new Button();

    HelpBox.prototype.draw = function(c) {
        var lines, i, y, helpText;

        Button.prototype.draw.call(this, c);

        if (this.helpOpen) {
            helpText = this.parent.helpText;
            c.font = '12pt Arial';
            lines = helpText.split('\n');
            y = this.topY + this.BUTTON_HEIGHT;
            for (i = 0; i < lines.length; i += 1) {
                y += this.LINE_HEIGHT;
                c.fillText(lines[i], this.topX + this.HELP_INDENT, y);
            }
        }
    };

    HelpBox.prototype.onClick = function() {
        this.helpOpen = ! this.helpOpen;
        this.parent.draw();
    };


    /**
     * Define a class TextBox for a possibly editable text box that might
     * be contained in another element.
     * @param {string} text The text to put in the text box.
     * @param {object} parent The graph to which the text box belongs.
     **/
    function TextBox(text, parent) {
        this.text = text;
        this.parent = parent;
        this.caretPosition = text.length;
        this.relDist = 0.5;
        this.offset = parent.parent.textOffset();
        this.dragged = false;
        this.boundingBox = {};
    }

    // Inserts a given character into the TextBox at its current caretPosition
    TextBox.prototype.insertChar = function(char) {
        this.text = this.text.slice(0, this.caretPosition) + char + this.text.slice(this.caretPosition);
        this.caretRight();
    };

    // Deletes the character in the TextBox that is located behind the current caretPosition
    TextBox.prototype.deleteChar = function() {
        if (this.caretPosition > 0){
            this.text = this.text.slice(0, this.caretPosition - 1) + this.text.slice(this.caretPosition);
            this.caretLeft();
        }
    };

    // Moves the TextBox's caret left one character if possible
    TextBox.prototype.caretLeft = function() {
        if (this.caretPosition > 0) {
            this.caretPosition --;
        }
    };

    // Moves the TextBox's caret right one character if possible
    TextBox.prototype.caretRight = function() {
        if (this.caretPosition < this.text.length) {
            this.caretPosition ++;
        }
    };

    TextBox.prototype.containsPoint = function(x, y) {
        var point = {x: x, y: y};
        return util.isInside(point, this.boundingBox);
    };

    TextBox.prototype.setMouseStart = function(x, y) {
      // At the start of a drag, record our position relative to the mouse.
        this.mouseOffsetX = this.position.x - x;
        this.mouseOffsetY = this.position.y - y;
    };

    TextBox.prototype.setAnchorPoint = function(x, y) {
        x += (this.mouseOffsetX || 0);
        y += (this.mouseOffsetY || 0);
        var linkInfo = this.parent.getEndPointsAndCircle();
        var relDist, offset;
        //Calculate the relative distance of the dragged text along its parent link
        if (linkInfo.hasCircle){
            var textAngle = Math.atan2(y-linkInfo.circleY, x-linkInfo.circleX);
            // Ensure textAngle is either between start and end angle, or more than end angle
            if (textAngle < linkInfo.startAngle) {
                textAngle += Math.PI * 2;
            }
            if (linkInfo.endAngle < linkInfo.startAngle) {
                linkInfo.endAngle += Math.PI * 2;
            }
            // Calculate relDist from angle (inverse of angle-from-relDist calculation in Link.prototype.draw)
            if (linkInfo.isReversed){
                relDist = (textAngle - linkInfo.startAngle - Math.PI*2) / (linkInfo.endAngle - linkInfo.startAngle - Math.PI*2);
            }else{
                relDist = (textAngle - linkInfo.startAngle) / (linkInfo.endAngle - linkInfo.startAngle);
            }
            offset = util.vectorMagnitude({x: x-linkInfo.circleX, y: y-linkInfo.circleY}) - linkInfo.circleRadius;
        }
        else {
            // Calculate relative position of the mouse projected onto the link.
            var textVector = {x: x - linkInfo.startX,
                              y: y - linkInfo.startY};
            var linkVector = {x: linkInfo.endX - linkInfo.startX,
                              y: linkInfo.endY - linkInfo.startY};
            var projection = util.scalarProjection(textVector, linkVector);
            relDist = projection / util.vectorMagnitude(linkVector);
            // Calculate offset (closest distance) of the mouse position from the link
            offset = Math.sqrt(Math.pow(util.vectorMagnitude(textVector), 2)- Math.pow(projection, 2));
            // If the mouse is on the opposite side of the link from the default text position, negate the offset
            var ccw = util.isCCW(textVector, linkVector);
            var reversed = (this.parent.lineAngleAdjust != 0);
            if ((!ccw && reversed) || (ccw && !reversed)){
                offset *= -1;
            }
        }
        if (relDist > 0 && relDist < 1){  //Ensure text isn't dragged past end of the link
            this.relDist = relDist;
            this.offset = Math.round(offset);
            this.dragged = true;
        }
    };

    TextBox.prototype.draw = function(x, y, angleOrNull, parentObject) {
        var graph = parentObject.parent,
            c = graph.getCanvas().getContext('2d');

        c.font = graph.fontSize() + 'px Arial';
        //Text before and after caret are drawn separately to expand Latex shortcuts at the caret position
        var beforeCaretText = util.convertLatexShortcuts(this.text.slice(0, this.caretPosition));
        var afterCaretText = util.convertLatexShortcuts(this.text.slice(this.caretPosition));
        var width = c.measureText(beforeCaretText + afterCaretText).width;
        var dy = Math.round(graph.fontSize() / 2);

        // Position the text appropriately if it is part of a link
        if(angleOrNull !== null) {
            var cos = Math.cos(angleOrNull);
            var sin = Math.sin(angleOrNull);

            //Add text offset in the direction of the text angle
            x += this.offset * cos;
            y += this.offset * sin;

            // Position text intelligently if text has not been manually moved
            if (!this.dragged){
                var cornerPointX = (width / 2) * (cos > 0 ? 1 : -1);
                var cornerPointY = (dy / 2) * (sin > 0 ? 1 : -1);
                var slide = sin * Math.pow(Math.abs(sin), 40) * cornerPointX - cos * Math.pow(Math.abs(cos), 10) * cornerPointY;
                x += cornerPointX - sin * slide;
                y += cornerPointY + cos * slide;
            }
            this.position = {x: Math.round(x), y: Math.round(y)};  //Record the position where text is anchored to
        }

        x -= width / 2;  // Center the text.

        //Round the coordinates so they fall on a pixel
        x = Math.round(x);
        y = Math.round(y);

        // Draw text and caret
        if('advancedFillText' in c) {
            c.advancedFillText(this.text, this.text, x + width / 2, y, angleOrNull);
        } else {
             // Draw translucent white rectangle behind text
            var prevStyle = c.fillStyle;
            c.fillStyle = "rgba(255, 255, 255, 0.7)";
            c.fillRect(x, y-dy, width, dy*2);
            c.fillStyle = prevStyle;

            // Draw text
            dy = Math.round(graph.fontSize() / 3); // Don't understand this.
            c.fillText(beforeCaretText, x, y + dy);
            var caretX = x + c.measureText(beforeCaretText).width;
            c.fillText(afterCaretText, caretX, y + dy);

            // Draw caret
            dy = Math.round(graph.fontSize() / 2);
            if(parentObject == graph.selectedObject && graph.caretVisible && graph.hasFocus() && document.hasFocus()) {
                c.beginPath();
                c.moveTo(caretX, y - dy);
                c.lineTo(caretX, y + dy);
                c.stroke();
            }
        }
        this.boundingBox = {x: x, y: y - dy, height: dy * 2, width: width};
    };


    return {
        Node: Node,
        Link: Link,
        SelfLink: SelfLink,
        TemporaryLink: TemporaryLink,
        StartLink: StartLink,
        Button: Button,
        HelpBox: HelpBox,
        TextBox: TextBox
    };
});
