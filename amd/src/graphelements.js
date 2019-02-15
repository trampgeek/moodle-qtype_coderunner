/******************************************************************************
 *
 * A module for use by ui_graph, defining classes Node, Link, SelfLink,
 * StartLink and TemporaryLink
 *
 ******************************************************************************/
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

    /***********************************************************************
     *
     * Define a class Node that represents a node in a graph
     *
     ***********************************************************************/

    function Node(parent, x, y) {
        this.parent = parent;  // The ui_graph instance.
        this.x = x;
        this.y = y;
        this.mouseOffsetX = 0;
        this.mouseOffsetY = 0;
        this.isAcceptState = false;
        this.text = '';
    }

    Node.prototype.setMouseStart = function(x, y) {
        this.mouseOffsetX = this.x - x;
        this.mouseOffsetY = this.y - y;
    };

    Node.prototype.setAnchorPoint = function(x, y) {
        this.x = x + this.mouseOffsetX;
        this.y = y + this.mouseOffsetY;
    };

    Node.prototype.draw = function(c) {
        // Draw the circle.
        c.beginPath();
        c.arc(this.x, this.y, this.parent.nodeRadius(), 0, 2 * Math.PI, false);
        c.stroke();

        // Draw the text.
        this.parent.drawText(this.text, this.x, this.y, null, this);

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

    /***********************************************************************
     *
     * Define a class Link that represents a connection between two nodes.
     *
     ***********************************************************************/
    function Link(parent, a, b) {
        this.parent = parent;  // The parent ui_digraph instance.
        this.nodeA = a;
        this.nodeB = b;
        this.text = '';
        this.lineAngleAdjust = 0; // Value to add to textAngle when link is straight line.

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
        var linkInfo = this.getEndPointsAndCircle();
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
        if(linkInfo.hasCircle) {
            var startAngle = linkInfo.startAngle;
            var endAngle = linkInfo.endAngle;
            if(endAngle < startAngle) {
                endAngle += Math.PI * 2;
            }
            var textAngle = (startAngle + endAngle) / 2 + linkInfo.isReversed * Math.PI;
            var textX = linkInfo.circleX + linkInfo.circleRadius * Math.cos(textAngle);
            var textY = linkInfo.circleY + linkInfo.circleRadius * Math.sin(textAngle);
            this.parent.drawText(this.text, textX, textY, textAngle, this);
        } else {
            var textX = (linkInfo.startX + linkInfo.endX) / 2;
            var textY = (linkInfo.startY + linkInfo.endY) / 2;
            var textAngle = Math.atan2(linkInfo.endX - linkInfo.startX, linkInfo.startY - linkInfo.endY);
            this.parent.drawText(this.text, textX, textY, textAngle + this.lineAngleAdjust, this);
        }
    };

    Link.prototype.containsPoint = function(x, y) {
        var linkInfo = this.getEndPointsAndCircle();
        if(linkInfo.hasCircle) {
            var dx = x - linkInfo.circleX;
            var dy = y - linkInfo.circleY;
            var distance = Math.sqrt(dx * dx + dy * dy) - linkInfo.circleRadius;
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
            var dx = linkInfo.endX - linkInfo.startX;
            var dy = linkInfo.endY - linkInfo.startY;
            var length = Math.sqrt(dx * dx + dy * dy);
            var percent = (dx * (x - linkInfo.startX) + dy * (y - linkInfo.startY)) / (length * length);
            var distance = (dx * (y - linkInfo.startY) - dy * (x - linkInfo.startX)) / length;
            return (percent > 0 && percent < 1 && Math.abs(distance) < this.parent.HIT_TARGET_PADDING);
        }
        return false;
    };

    /***********************************************************************
     *
     * Define a class SelfLink that represents a connection from a node back
     * to itself.
     *
     ***********************************************************************/

    function SelfLink(parent, node, mouse) {
        this.parent = parent;
        this.node = node;
        this.anchorAngle = 0;
        this.mouseOffsetAngle = 0;
        this.text = '';

        if(mouse) {
            this.setAnchorPoint(mouse.x, mouse.y);
        }
    }

    SelfLink.prototype.setMouseStart = function(x, y) {
        this.mouseOffsetAngle = this.anchorAngle - Math.atan2(y - this.node.y, x - this.node.x);
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
        // Draw the text on the loop farthest from the node.
        var textX = linkInfo.circleX + linkInfo.circleRadius * Math.cos(this.anchorAngle);
        var textY = linkInfo.circleY + linkInfo.circleRadius * Math.sin(this.anchorAngle);
        this.parent.drawText(this.text, textX, textY, this.anchorAngle, this);
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

    /***********************************************************************
     *
     * Define a class StartLink that represents a start link in a finite
     * state machine. Not useful in general digraphs.
     *
     ***********************************************************************/
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

    /***********************************************************************
     *
     * Define a class TemporaryLink that represents a link that's in the
     * process of being created.
     *
     ***********************************************************************/

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

    /***********************************************************************
     *
     * Define a class HelpBox for the help box and its pseudo-menu buttonb.
     *
     ***********************************************************************/

    function HelpBox(parent, topX, topY) {
        this.BUTTON_WIDTH = 50;
        this.BUTTON_HEIGHT = 25;
        this.TEXT_OFFSET_X = 25;
        this.TEXT_OFFSET_Y = 17;
        this.LINE_HEIGHT = 18;
        this.HELP_INDENT = 5;
        this.topX = topX;
        this.topY = topY;
        this.parent = parent;
    }

    HelpBox.prototype.containsPoint = function(x, y) {
        return x >= this.topX && y >= this.topY &&
                x <= this.topX + this.BUTTON_WIDTH &&
                y <= this.topY + this.BUTTON_HEIGHT;
    };

    HelpBox.prototype.draw = function(c, isSelected, mouseIsOver) {
        var lines, i, y, helpText;

        if (mouseIsOver) {
            c.fillStyle = '#FFFFFF';
        } else {
            c.fillStyle = '#F0F0F0';
        }
        c.fillRect(this.topX, this.topY,
            this.topX + this.BUTTON_WIDTH, this.topY + this.BUTTON_HEIGHT);
        c.lineWidth = 0.5;
        c.strokeStyle = '#000000';
        c.strokeRect(this.topX, this.topY,
            this.topX + this.BUTTON_WIDTH, this.topY + this.BUTTON_HEIGHT);

        c.font = '12pt Arial';
        c.fillStyle = '#000000';
        c.textAlign = "center";
        c.fillText('Help', this.topX + this.TEXT_OFFSET_X, this.topY + this.TEXT_OFFSET_Y);
        c.textAlign = "left";

        if (isSelected) {
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

    return {
        Node: Node,
        Link: Link,
        SelfLink: SelfLink,
        TemporaryLink: TemporaryLink,
        StartLink: StartLink,
        HelpBox: HelpBox
    };
});