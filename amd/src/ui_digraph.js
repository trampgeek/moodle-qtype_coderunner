/*

 Directed Graph Drawing Package.
 Based on: Finite State Machine Designer (http://madebyevan.com/fsm/)
 License: MIT License (see below)

 Copyright (c) 2010 Evan Wallace

 Modified 16 May 2017 by Emily Price, University of Canterbury
 Further modified November/December 2017 by Richard Lobb, Univ of Canterbury

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
define(['jquery'], function($) {

    /***********************************************************************
     *
     * Utility functions/data/constants
     *
     ***********************************************************************/

    var SNAP_TO_PADDING = 6;
    var HIT_TARGET_PADDING = 6; // pixels
    var NODE_RADIUS = 20;

    var greekLetterNames = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon',
                            'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa', 'Lambda',
                            'Mu', 'Nu', 'Xi', 'Omicron', 'Pi', 'Rho', 'Sigma',
                            'Tau', 'Upsilon', 'Phi', 'Chi', 'Psi', 'Omega' ];



    function convertLatexShortcuts(text) {
        // html greek characters
        for(var i = 0; i < greekLetterNames.length; i++) {
            var name = greekLetterNames[i];
            text = text.replace(new RegExp('\\\\' + name, 'g'), String.fromCharCode(913 + i + (i > 16)));
            text = text.replace(new RegExp('\\\\' + name.toLowerCase(), 'g'), String.fromCharCode(945 + i + (i > 16)));
        }

        // subscripts
        for(var i = 0; i < 10; i++) {
            text = text.replace(new RegExp('_' + i, 'g'), String.fromCharCode(8320 + i));
        }

        return text;
    }

    function drawArrow(c, x, y, angle) {
        var dx = Math.cos(angle);
        var dy = Math.sin(angle);
        c.beginPath();
        c.moveTo(x, y);
        c.lineTo(x - 8 * dx + 5 * dy, y - 8 * dy - 5 * dx);
        c.lineTo(x - 8 * dx - 5 * dy, y - 8 * dy + 5 * dx);
        c.fill();
    }


    function det(a, b, c, d, e, f, g, h, i) {
        return a * e * i + b * f * g + c * d * h - a * f * h - b * d * i - c * e * g;
    }


    function circleFromThreePoints(x1, y1, x2, y2, x3, y3) {
        var a = det(x1, y1, 1, x2, y2, 1, x3, y3, 1);
        var bx = -det(x1 * x1 + y1 * y1, y1, 1, x2 * x2 + y2 * y2, y2, 1, x3 * x3 + y3 * y3, y3, 1);
        var by = det(x1 * x1 + y1 * y1, x1, 1, x2 * x2 + y2 * y2, x2, 1, x3 * x3 + y3 * y3, x3, 1);
        var c = -det(x1 * x1 + y1 * y1, x1, y1, x2 * x2 + y2 * y2, x2, y2, x3 * x3 + y3 * y3, x3, y3);
        return {
            'x': -bx / (2 * a),
            'y': -by / (2 * a),
            'radius': Math.sqrt(bx * bx + by * by - 4 * a * c) / (2 * Math.abs(a))
        };
    }


    function isInside(pos, rect){
        return pos.x > rect.x && pos.x < rect.x + rect.width && pos.y < rect.y + rect.height && pos.y > rect.y;
    }


    function crossBrowserKey(e) {
        e = e || window.event;
        return e.which || e.keyCode;
    }


    function crossBrowserElementPos(e) {
        e = e || window.event;
        var obj = e.target || e.srcElement;
        var x = 0, y = 0;
        while(obj.offsetParent) {
            x += obj.offsetLeft;
            y += obj.offsetTop;
            obj = obj.offsetParent;
        }
        return { 'x': x, 'y': y };
    }


    function crossBrowserMousePos(e) {
        e = e || window.event;
        return {
            'x': e.pageX || e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft,
            'y': e.pageY || e.clientY + document.body.scrollTop + document.documentElement.scrollTop,
        };
    }


    function crossBrowserRelativeMousePos(e) {
        var element = crossBrowserElementPos(e);
        var mouse = crossBrowserMousePos(e);
        return {
            'x': mouse.x - element.x,
            'y': mouse.y - element.y
        };
    }


    function drawHelpBox(c) {
        c.beginPath();
        c.fillStyle = 'rgba(119,136,153,0.5)';
        c.fillRect(750,0,50,25);
        c.lineWidth = 2;
        c.strokeStyle = '#000000';
        c.stroke();
        c.closePath();

        c.font = '12pt "Times New Roman", serif';
        c.fillStyle = '#000000';
        c.textAlign = "center";
        c.fillText('Help', 775, 17);
        c.textAlign = "left";
    }

    /***********************************************************************
     *
     * Define a class Node that represents a node in a graph
     *
     ***********************************************************************/

    function Node(parent, x, y) {
        this.parent = parent;  // The ui_graph instance
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
        // draw the circle
        c.beginPath();
        c.arc(this.x, this.y, NODE_RADIUS, 0, 2 * Math.PI, false);
        c.stroke();

        // draw the text
        this.parent.drawText(this.text, this.x, this.y, null, this);

        // draw a double circle for an accept state
        if(this.isAcceptState) {
            c.beginPath();
            c.arc(this.x, this.y, NODE_RADIUS - 6, 0, 2 * Math.PI, false);
            c.stroke();
        }
    };

    Node.prototype.closestPointOnCircle = function(x, y) {
        var dx = x - this.x;
        var dy = y - this.y;
        var scale = Math.sqrt(dx * dx + dy * dy);
        return {
            'x': this.x + dx * NODE_RADIUS / scale,
            'y': this.y + dy * NODE_RADIUS / scale,
        };
    };

    Node.prototype.containsPoint = function(x, y) {
        return (x - this.x) * (x - this.x) + (y - this.y) * (y - this.y) < NODE_RADIUS * NODE_RADIUS;
    };


    /***********************************************************************
     *
     * Define a class Link that represents a connection between two nodes
     *
     ***********************************************************************/
    function Link(parent, a, b) {
        this.parent = parent;  // The parent ui_digraph isntance
        this.nodeA = a;
        this.nodeB = b;
        this.text = '';
        this.lineAngleAdjust = 0; // value to add to textAngle when link is straight line

        // make anchor point relative to the locations of nodeA and nodeB
        this.parallelPart = 0.5; // percentage from nodeA to nodeB
        this.perpendicularPart = 0; // pixels from line between nodeA and nodeB
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
        // snap to a straight line
        if(this.parallelPart > 0 && this.parallelPart < 1 && Math.abs(this.perpendicularPart) < SNAP_TO_PADDING) {
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
        var circle = circleFromThreePoints(this.nodeA.x, this.nodeA.y, this.nodeB.x, this.nodeB.y, anchor.x, anchor.y);
        var isReversed = (this.perpendicularPart > 0);
        var reverseScale = isReversed ? 1 : -1;
        var rRatio = reverseScale * NODE_RADIUS / circle.radius;
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
        // draw arc
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
        // draw the head of the arrow
        if(linkInfo.hasCircle) {
            drawArrow(c,
                      linkInfo.endX,
                      linkInfo.endY,
                      linkInfo.endAngle - linkInfo.reverseScale * (Math.PI / 2));
        } else {
            drawArrow(c,
                      linkInfo.endX,
                      linkInfo.endY,
                      Math.atan2(linkInfo.endY - linkInfo.startY, linkInfo.endX - linkInfo.startX));
        }
        // draw the text
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
            if(Math.abs(distance) < HIT_TARGET_PADDING) {
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
            return (percent > 0 && percent < 1 && Math.abs(distance) < HIT_TARGET_PADDING);
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
        // snap to 90 degrees
        var snap = Math.round(this.anchorAngle / (Math.PI / 2)) * (Math.PI / 2);
        if(Math.abs(this.anchorAngle - snap) < 0.1) {
            this.anchorAngle = snap;
        }
        // keep in the range -pi to pi so our containsPoint() function always works
        if(this.anchorAngle < -Math.PI) {
            this.anchorAngle += 2 * Math.PI;
        }
        if(this.anchorAngle > Math.PI) {
            this.anchorAngle -= 2 * Math.PI;
        }
    };


    SelfLink.prototype.getEndPointsAndCircle = function() {
        var circleX = this.node.x + 1.5 * NODE_RADIUS * Math.cos(this.anchorAngle);
        var circleY = this.node.y + 1.5 * NODE_RADIUS * Math.sin(this.anchorAngle);
        var circleRadius = 0.75 * NODE_RADIUS;
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
        // draw arc
        c.beginPath();
        c.arc(linkInfo.circleX, linkInfo.circleY, linkInfo.circleRadius, linkInfo.startAngle, linkInfo.endAngle, false);
        c.stroke();
        // draw the text on the loop farthest from the node
        var textX = linkInfo.circleX + linkInfo.circleRadius * Math.cos(this.anchorAngle);
        var textY = linkInfo.circleY + linkInfo.circleRadius * Math.sin(this.anchorAngle);
        this.parent.drawText(this.text, textX, textY, this.anchorAngle, this);
        // draw the head of the arrow
        drawArrow(c, linkInfo.endX, linkInfo.endY, linkInfo.endAngle + Math.PI * 0.4);
    };


    SelfLink.prototype.containsPoint = function(x, y) {
        var linkInfo = this.getEndPointsAndCircle();
        var dx = x - linkInfo.circleX;
        var dy = y - linkInfo.circleY;
        var distance = Math.sqrt(dx * dx + dy * dy) - linkInfo.circleRadius;
        return (Math.abs(distance) < HIT_TARGET_PADDING);
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

        if(Math.abs(this.deltaX) < SNAP_TO_PADDING) {
            this.deltaX = 0;
        }

        if(Math.abs(this.deltaY) < SNAP_TO_PADDING) {
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

        // draw the line
        c.beginPath();
        c.moveTo(endPoints.startX, endPoints.startY);
        c.lineTo(endPoints.endX, endPoints.endY);
        c.stroke();

        // draw the head of the arrow
        drawArrow(c, endPoints.endX, endPoints.endY, Math.atan2(-this.deltaY, -this.deltaX));
    };


    StartLink.prototype.containsPoint = function(x, y) {
        var endPoints = this.getEndPoints();
        var dx = endPoints.endX - endPoints.startX;
        var dy = endPoints.endY - endPoints.startY;
        var length = Math.sqrt(dx * dx + dy * dy);
        var percent = (dx * (x - endPoints.startX) + dy * (y - endPoints.startY)) / (length * length);
        var distance = (dx * (y - endPoints.startY) - dy * (x - endPoints.startX)) / length;
        return (percent > 0 && percent < 1 && Math.abs(distance) < HIT_TARGET_PADDING);
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
        // draw the line
        c.beginPath();
        c.moveTo(this.to.x, this.to.y);
        c.lineTo(this.from.x, this.from.y);
        c.stroke();

        // draw the head of the arrow
        drawArrow(c, this.to.x, this.to.y, Math.atan2(this.to.y - this.from.y, this.to.x - this.from.x));
    };


    /***********************************************************************
     *
     * A GraphCanvas is a wrapper for a DigraphInstance's HTML canvas
     * object.
     *
     ************************************************************************/

    function GraphCanvas(parent, textareaId) {
        // Constructor, given the DigraphInstance that owns this canvas.

        this.parent = parent;
        this.canvas = document.createElement("canvas");
        this.canvas.setAttribute("id", "id_fsmcanvas_" + textareaId);
        this.canvas.setAttribute("width", "800");
        this.canvas.setAttribute("height", "600");
        this.canvas.setAttribute("style", "background-color: white");
        this.canvas.setAttribute("class", "coderunner_fsmcanvas");
        this.canvas.setAttribute("tabindex", "1");  // So canvas can get focus

        this.canvas.onmousedown = function(e) {
            parent.mousedown(e);
        };

        this.canvas.onmouseup = function(e) {
            parent.mouseup(e);
        };

        this.canvas.ondblclick = function(e) {
            parent.dblclick(e);
        };

        this.canvas.onkeydown = function(e) {
            parent.keydown(e);
        };

        this.canvas.onmousemove = function(e) {
            parent.mousemove(e);
        };

        this.canvas.onkeypress = function(e) {
            parent.keypress(e);
        };
    }



    /***********************************************************************
     *
     *  Now a class to wrap a specific instance of a Digraph UI managing
     *  a particular text area element, passed as a parameter.
     *  *** TODO *** A div with a roll-your-own resize handle is wrapped around the
     *  Digraph editor node so that users can resize the editor panel.
     *
     ***********************************************************************/
    function DigraphInstance(textareaId) {
        // Warning: IDs from Moodle can contain colons - don't work with jQuery!

        this.helpBox = {
                x:750,
                y:0,
                width: 50,
                height: 25
            };

        this.graphCanvas = new GraphCanvas(this, textareaId);
        this.textArea = document.getElementById(textareaId);
        this.caretVisible = true;
        this.caretTimer = 0;  // Need global so we can kill a running timer
        this.originalClick = null;
        this.nodes = [];
        this.links = [];
        this.selectedObject = null; // either a Link or a Node
        this.currentLink = null; // a Link
        this.movingObject = false;

        $(this.getCanvas()).insertBefore(this.textArea);
        $(this.textArea).hide();
        this.restoreBackup();
        this.draw();
    }

    DigraphInstance.prototype.getCanvas = function() {
        return this.graphCanvas.canvas;
    };

    DigraphInstance.prototype.keypress = function(e) {
        var key = crossBrowserKey(e);

        if(key >= 0x20 &&
                  key <= 0x7E &&
                  !e.metaKey &&
                  !e.altKey &&
                  !e.ctrlKey &&
                  this.selectedObject !== null &&
                  'text' in this.selectedObject) {
            this.selectedObject.text += String.fromCharCode(key);
            this.resetCaret();
            this.draw();

            // don't let keys do their actions (like space scrolls down the page)
            return false;
        } else if(key === 8) {
            // backspace is a shortcut for the back button, but do NOT want to change pages
            return false;
        }
    };



    DigraphInstance.prototype.mousedown = function(e) {
        var mouse = crossBrowserRelativeMousePos(e);

        this.selectedObject = this.selectObject(mouse.x, mouse.y);
        this.movingObject = false;
        this.originalClick = mouse;

        if (isInside(mouse, this.helpBox)) {
            var helptext = M.util.get_string('fsmhelp', 'qtype_coderunner');
            alert(helptext);
        }

        if(this.selectedObject !== null) {
            if(e.shiftKey && this.selectedObject instanceof Node) {
                this.currentLink = new SelfLink(this, this.selectedObject, mouse);
            } else {
                this.movingObject = true;
                if(this.selectedObject.setMouseStart) {
                    this.selectedObject.setMouseStart(mouse.x, mouse.y);
                }
            }
            this.resetCaret();
        } else if(e.shiftKey) {
            this.currentLink = new TemporaryLink(this, mouse, mouse);
        }

        this.draw();

        if(this.canvasHasFocus()) {
            // disable drag-and-drop only if the canvas is already focused
            return false;
        } else {
            // otherwise, let the browser switch the focus away from wherever it was
            this.resetCaret();
            return true;
        }
    };


    DigraphInstance.prototype.keydown = function(e) {
        var key = crossBrowserKey(e);

        if(key === 8) { // backspace key
            if(this.selectedObject !== null && 'text' in this.selectedObject) {
                this.selectedObject.text = this.selectedObject.text.substr(0, this.selectedObject.text.length - 1);
                this.resetCaret();
                this.draw();
            }

            // backspace is a shortcut for the back button, but do NOT want to change pages
            return false;
        } else if(key === 46) { // delete key
            if(this.selectedObject !== null) {
                for(var i = 0; i < this.nodes.length; i++) {
                    if(this.nodes[i] === this.selectedObject) {
                        this.nodes.splice(i--, 1);
                    }
                }
                for(var i = 0; i < this.links.length; i++) {
                    if(this.links[i] === this.selectedObject ||
                       this.links[i].node === this.selectedObject ||
                       this.links[i].nodeA === this.selectedObject ||
                       this.links[i].nodeB === this.selectedObject) {
                       this.links.splice(i--, 1);
                    }
                }
                this.selectedObject = null;
                this.draw();
            }
        } else if(key === 13) { // enter key
            if(this.selectedObject !== null) {
                // deselect the object
                this.selectedObject = null;
                this.draw();
            }
        }
    };


    DigraphInstance.prototype.dblclick = function(e) {
        var mouse = crossBrowserRelativeMousePos(e);

        this.selectedObject = this.selectObject(mouse.x, mouse.y);

        if(this.selectedObject === null) {
            this.selectedObject = new Node(this, mouse.x, mouse.y);
            this.nodes.push(this.selectedObject);
            this.resetCaret();
            this.draw();
        } else {
            if(this.selectedObject instanceof Node) {
                this.selectedObject.isAcceptState = !this.selectedObject.isAcceptState;
                this.draw();
            }
        }
    };


    DigraphInstance.prototype.mousemove = function(e) {
        var mouse = crossBrowserRelativeMousePos(e);

        if(this.currentLink !== null) {
            var targetNode = this.selectObject(mouse.x, mouse.y);
            if(!(targetNode instanceof Node)) {
                targetNode = null;
            }

            if(this.selectedObject === null) {
                if(targetNode !== null) {
                    this.currentLink = new StartLink(this, targetNode, this.originalClick);
                } else {
                    this.currentLink = new TemporaryLink(this, this.originalClick, mouse);
                }
            } else {
                if(targetNode === this.selectedObject) {
                    this.currentLink = new SelfLink(this, this.selectedObject, mouse);
                } else if(targetNode !== null) {
                    this.currentLink = new Link(this, this.selectedObject, targetNode);
                } else {
                    this.currentLink = new TemporaryLink(this, this.selectedObject.closestPointOnCircle(mouse.x, mouse.y), mouse);
                }
            }
            this.draw();
        }

        if(this.movingObject) {
            this.selectedObject.setAnchorPoint(mouse.x, mouse.y);
            if(this.selectedObject instanceof Node) {
                this.snapNode(this.selectedObject);
            }
            this.draw();
        }
    };


    DigraphInstance.prototype.mouseup = function() {
        this.movingObject = false;

        if(this.currentLink !== null) {
            if(!(this.currentLink instanceof TemporaryLink)) {
                this.selectedObject = this.currentLink;
                this.links.push(this.currentLink);
                this.resetCaret();
            }
            this.currentLink = null;
            this.draw();
        }
    };

    DigraphInstance.prototype.selectObject = function(x, y) {
        for(var i = 0; i < this.nodes.length; i++) {
            if(this.nodes[i].containsPoint(x, y)) {
                return this.nodes[i];
            }
        }
        for(var i = 0; i < this.links.length; i++) {
            if(this.links[i].containsPoint(x, y)) {
                return this.links[i];
            }
        }
        return null;
    };


    DigraphInstance.prototype.snapNode = function(node) {
        for(var i = 0; i < this.nodes.length; i++) {
            if(this.nodes[i] === node){
                continue;
            }

            if(Math.abs(node.x - this.nodes[i].x) < SNAP_TO_PADDING) {
                node.x = this.nodes[i].x;
            }

            if(Math.abs(node.y - this.nodes[i].y) < SNAP_TO_PADDING) {
                node.y = this.nodes[i].y;
            }
        }
    };


    DigraphInstance.prototype.restoreBackup = function() {
        if(!JSON) {
            return;
        }

        try {
            // load up the student's previous answer
            var backup = JSON.parse($(this.textArea).val());

            for(var i = 0; i < backup.nodes.length; i++) {
                var backupNode = backup.nodes[i];
                var backupNodeLayout = backup.nodeGeometry[i];
                var node = new Node(this, backupNodeLayout[0], backupNodeLayout[1]);
                node.isAcceptState = backupNode[1];
                node.text = backupNode[0];
                this.nodes.push(node);
            }

            for(var i = 0; i < backup.edges.length; i++) {
                var backupLink = backup.edges[i];
                var backupLinkLayout = backup.edgeGeometry[i];
                var link = null;
                if(backupLink[0] === backupLink[1]) {
                    // self link has two identical nodes
                    link = new SelfLink(this, this.nodes[backupLink[0]]);
                    link.anchorAngle = backupLinkLayout.anchorAngle;
                    link.text = backupLink[2];
                } else if(backupLink[0] === -1) {
                    link = new StartLink(this, this.nodes[backupLink[1]]);
                    link.deltaX = backupLinkLayout.deltaX;
                    link.deltaY = backupLinkLayout.deltaY;
                } else {
                    link = new Link(this, this.nodes[backupLink[0]], this.nodes[backupLink[1]]);
                    link.parallelPart = backupLinkLayout.parallelPart;
                    link.perpendicularPart = backupLinkLayout.perpendicularPart;
                    link.text = backupLink[2];
                    link.lineAngleAdjust = backupLinkLayout.lineAngleAdjust;
                }
                if(link !== null) {
                    this.links.push(link);
                }
            }
        } catch(e) {
            // error loading previous answer
        }
    };

    DigraphInstance.prototype.saveBackup = function() {
        if(!JSON) {
            return;
        }

        var backup = {
            'edgeGeometry': [],
            'nodeGeometry': [],
            'nodes': [],
            'edges': [],
        };

        for(var i = 0; i < this.nodes.length; i++) {
            var node = this.nodes[i];

            var nodeData = [node.text, node.isAcceptState];
            var nodeLayout = [node.x, node.y];

            backup.nodeGeometry.push(nodeLayout);
            backup.nodes.push(nodeData);
        }

        for(var i = 0; i < this.links.length; i++) {
            var link = this.links[i],
                linkData = null,
                linkLayout = null;

            if(link instanceof SelfLink) {
                linkLayout = {
                    'anchorAngle': link.anchorAngle,
                };
                linkData = [this.nodes.indexOf(link.node), this.nodes.indexOf(link.node), link.text];
            } else if(link instanceof StartLink) {
                linkLayout = {
                    'deltaX': link.deltaX,
                    'deltaY': link.deltaY
                };
                linkData = [-1, this.nodes.indexOf(link.node), ""];
            } else if(link instanceof Link) {
                linkLayout = {
                    'lineAngleAdjust': link.lineAngleAdjust,
                    'parallelPart': link.parallelPart,
                    'perpendicularPart': link.perpendicularPart,
                };
                linkData = [this.nodes.indexOf(link.nodeA), this.nodes.indexOf(link.nodeB), link.text];
            }
            if(linkData !== null && linkLayout !== null) {
                backup.edges.push(linkData);
                backup.edgeGeometry.push(linkLayout);
            }
        }
        $(this.textArea).val(JSON.stringify(backup));
    };


    DigraphInstance.prototype.destroy = function () {
        var canvas = this.getCanvas();
        canvas.parentNode.removeChild(canvas);
    };


    DigraphInstance.prototype.canvasHasFocus = function() {
        return document.activeElement == this.getCanvas();
    };


    DigraphInstance.prototype.resetCaret = function () {
        var t = this; // For embedded function to access this

        clearInterval(this.caretTimer);
        this.caretTimer = setInterval(function() {
            t.caretVisible = !t.caretVisible;
            t.draw();
        }, 500);
        this.caretVisible = true;
    };


    DigraphInstance.prototype.draw = function () {
        var c = this.getCanvas().getContext('2d');

        c.clearRect(0, 0, this.getCanvas().width, this.getCanvas().height);
        c.save();
        c.translate(0.5, 0.5);

        drawHelpBox(c);

        for(var i = 0; i < this.nodes.length; i++) {
            c.lineWidth = 1;
            c.fillStyle = c.strokeStyle = (this.nodes[i] === this.selectedObject) ? 'blue' : 'black';
            this.nodes[i].draw(c);
        }
        for(var i = 0; i < this.links.length; i++) {
            c.lineWidth = 1;
            c.fillStyle = c.strokeStyle = (this.links[i] === this.selectedObject) ? 'blue' : 'black';
            this.links[i].draw(c);
        }
        if(this.currentLink !== null) {
            c.lineWidth = 1;
            c.fillStyle = c.strokeStyle = 'black';
            this.currentLink.draw(c);
        }

        c.restore();
        this.saveBackup();
    };


    DigraphInstance.prototype.drawText = function(originalText, x, y, angleOrNull, theObject) {
        var c = this.getCanvas().getContext('2d'),
            text = convertLatexShortcuts(originalText),
            width = c.measureText(text).width;

        c.font = '20px "Times New Roman", serif';

        // center the text
        x -= width / 2;

        // position the text intelligently if given an angle
        if(angleOrNull !== null) {
            var cos = Math.cos(angleOrNull);
            var sin = Math.sin(angleOrNull);
            var cornerPointX = (width / 2 + 5) * (cos > 0 ? 1 : -1);
            var cornerPointY = (10 + 5) * (sin > 0 ? 1 : -1);
            var slide = sin * Math.pow(Math.abs(sin), 40) * cornerPointX - cos * Math.pow(Math.abs(cos), 10) * cornerPointY;
            x += cornerPointX - sin * slide;
            y += cornerPointY + cos * slide;
        }

        // draw text and caret (round the coordinates so the caret falls on a pixel)
        if('advancedFillText' in c) {
            c.advancedFillText(text, originalText, x + width / 2, y, angleOrNull);
        } else {
            x = Math.round(x);
            y = Math.round(y);
            c.fillText(text, x, y + 6);
            if(theObject == this.selectedObject && this.caretVisible && this.canvasHasFocus() && document.hasFocus()) {
                x += width;
                c.beginPath();
                c.moveTo(x, y - 10);
                c.lineTo(x, y + 10);
                c.stroke();
            }
        }
    };



    /***********************************************************************
     *
     *  Lastly the Interface class that manages all Digraph UI instances
     *  on a page.
     *
     ***********************************************************************/

    function DigraphInterface() {
        var thisInterface = this;
        this.activeInstances = [];  // Keys are text area IDs, values are Digraph instances

        $(document.body).on('keydown', function(e) {
            var KEY_M = 77, graphInstance, textarea;

            if (e.keyCode === KEY_M && e.ctrlKey && e.altKey) {
                for (var taId in thisInterface.activeInstances) {
                    graphInstance = thisInterface.activeInstances[taId];
                    textarea = document.getElementById(taId);
                    $(graphInstance.getCanvas()).toggle();
                    $(textarea).toggle();
                }
            }
        });
    }


    DigraphInterface.prototype.destroyInstance = function(taId) {
        var instance = this.activeInstances[taId];

        if (instance) {
            instance.destroy();
            delete this.activeInstances[taId];
        }
    };

    DigraphInterface.prototype.init = function(taId) {
        this.activeInstances[taId] = new DigraphInstance(taId);
    };

    return new DigraphInterface();
});