/*
 Finite State Machine Designer (http://madebyevan.com/fsm/)
 License: MIT License (see below)

 Copyright (c) 2010 Evan Wallace

 Modified 16 May 2017 by Emily Price, University of Canterbury

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
    var textArea;
    
    function Link(a, b) {
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
        if(this.parallelPart > 0 && this.parallelPart < 1 && Math.abs(this.perpendicularPart) < snapToPadding) {
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
        var startAngle = Math.atan2(this.nodeA.y - circle.y, this.nodeA.x - circle.x) - reverseScale * nodeRadius / circle.radius;
        var endAngle = Math.atan2(this.nodeB.y - circle.y, this.nodeB.x - circle.x) + reverseScale * nodeRadius / circle.radius;
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
            c.arc(linkInfo.circleX, linkInfo.circleY, linkInfo.circleRadius, linkInfo.startAngle, linkInfo.endAngle, linkInfo.isReversed);
        } else {
            c.moveTo(linkInfo.startX, linkInfo.startY);
            c.lineTo(linkInfo.endX, linkInfo.endY);
        }
        c.stroke();
        // draw the head of the arrow
        if(linkInfo.hasCircle) {
            drawArrow(c, linkInfo.endX, linkInfo.endY, linkInfo.endAngle - linkInfo.reverseScale * (Math.PI / 2));
        } else {
            drawArrow(c, linkInfo.endX, linkInfo.endY, Math.atan2(linkInfo.endY - linkInfo.startY, linkInfo.endX - linkInfo.startX));
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
            drawText(c, this.text, textX, textY, textAngle, selectedObject === this);
        } else {
            var textX = (linkInfo.startX + linkInfo.endX) / 2;
            var textY = (linkInfo.startY + linkInfo.endY) / 2;
            var textAngle = Math.atan2(linkInfo.endX - linkInfo.startX, linkInfo.startY - linkInfo.endY);
            drawText(c, this.text, textX, textY, textAngle + this.lineAngleAdjust, selectedObject === this);
        }
    };

    Link.prototype.containsPoint = function(x, y) {
        var linkInfo = this.getEndPointsAndCircle();
        if(linkInfo.hasCircle) {
            var dx = x - linkInfo.circleX;
            var dy = y - linkInfo.circleY;
            var distance = Math.sqrt(dx*dx + dy*dy) - linkInfo.circleRadius;
            if(Math.abs(distance) < hitTargetPadding) {
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
            var length = Math.sqrt(dx*dx + dy*dy);
            var percent = (dx * (x - linkInfo.startX) + dy * (y - linkInfo.startY)) / (length * length);
            var distance = (dx * (y - linkInfo.startY) - dy * (x - linkInfo.startX)) / length;
            return (percent > 0 && percent < 1 && Math.abs(distance) < hitTargetPadding);
        }
        return false;
    };

    function Node(x, y) {
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
        c.arc(this.x, this.y, nodeRadius, 0, 2 * Math.PI, false);
        c.stroke();

        // draw the text
        drawText(c, this.text, this.x, this.y, null, selectedObject === this);

        // draw a double circle for an accept state
        if(this.isAcceptState) {
            c.beginPath();
            c.arc(this.x, this.y, nodeRadius - 6, 0, 2 * Math.PI, false);
            c.stroke();
        }
    };

    Node.prototype.closestPointOnCircle = function(x, y) {
        var dx = x - this.x;
        var dy = y - this.y;
        var scale = Math.sqrt(dx * dx + dy * dy);
        return {
            'x': this.x + dx * nodeRadius / scale,
            'y': this.y + dy * nodeRadius / scale,
        };
    };

    Node.prototype.containsPoint = function(x, y) {
        return (x - this.x)*(x - this.x) + (y - this.y)*(y - this.y) < nodeRadius*nodeRadius;
    };

    function SelfLink(node, mouse) {
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
        var circleX = this.node.x + 1.5 * nodeRadius * Math.cos(this.anchorAngle);
        var circleY = this.node.y + 1.5 * nodeRadius * Math.sin(this.anchorAngle);
        var circleRadius = 0.75 * nodeRadius;
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
        drawText(c, this.text, textX, textY, this.anchorAngle, selectedObject === this);
        // draw the head of the arrow
        drawArrow(c, linkInfo.endX, linkInfo.endY, linkInfo.endAngle + Math.PI * 0.4);
    };

    SelfLink.prototype.containsPoint = function(x, y) {
        var linkInfo = this.getEndPointsAndCircle();
        var dx = x - linkInfo.circleX;
        var dy = y - linkInfo.circleY;
        var distance = Math.sqrt(dx*dx + dy*dy) - linkInfo.circleRadius;
        return (Math.abs(distance) < hitTargetPadding);
    };

    function StartLink(node, start) {
        this.node = node;
        this.deltaX = 0;
        this.deltaY = 0;
        this.text = '';

        if(start) {
            this.setAnchorPoint(start.x, start.y);
        }
    }

    StartLink.prototype.setAnchorPoint = function(x, y) {
        this.deltaX = x - this.node.x;
        this.deltaY = y - this.node.y;

        if(Math.abs(this.deltaX) < snapToPadding) {
            this.deltaX = 0;
        }

        if(Math.abs(this.deltaY) < snapToPadding) {
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

        // draw the text at the end without the arrow
        var textAngle = Math.atan2(endPoints.startY - endPoints.endY, endPoints.startX - endPoints.endX);
        drawText(c, this.text, endPoints.startX, endPoints.startY, textAngle, selectedObject === this);

        // draw the head of the arrow
        drawArrow(c, endPoints.endX, endPoints.endY, Math.atan2(-this.deltaY, -this.deltaX));
    };

    StartLink.prototype.containsPoint = function(x, y) {
        var endPoints = this.getEndPoints();
        var dx = endPoints.endX - endPoints.startX;
        var dy = endPoints.endY - endPoints.startY;
        var length = Math.sqrt(dx*dx + dy*dy);
        var percent = (dx * (x - endPoints.startX) + dy * (y - endPoints.startY)) / (length * length);
        var distance = (dx * (y - endPoints.startY) - dy * (x - endPoints.startX)) / length;
        return (percent > 0 && percent < 1 && Math.abs(distance) < hitTargetPadding);
    };

    function TemporaryLink(from, to) {
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

    function canvasHasFocus() {
        return (document.activeElement || document.body) === document.body;
    }

    function drawText(c, originalText, x, y, angleOrNull, isSelected) {
        var text = convertLatexShortcuts(originalText);
        c.font = '20px "Times New Roman", serif';
        var width = c.measureText(text).width;

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
            if(isSelected && caretVisible && canvasHasFocus() && document.hasFocus()) {
                x += width;
                c.beginPath();
                c.moveTo(x, y - 10);
                c.lineTo(x, y + 10);
                c.stroke();
            }
        }
    }

    var caretTimer;
    var caretVisible = true;

    function resetCaret() {
        clearInterval(caretTimer);
        //The following line breaks things for some reason
        //caretTimer = setInterval('caretVisible = !caretVisible; draw()', 500);
        caretVisible = true;
    }

    var canvas;
    var nodeRadius = 30;
    var nodes = [];
    var links = [];

    var snapToPadding = 6; // pixels
    var hitTargetPadding = 6; // pixels
    var selectedObject = null; // either a Link or a Node
    var currentLink = null; // a Link
    var movingObject = false;
    var originalClick;

    function drawUsing(c) {
        c.clearRect(0, 0, canvas.width, canvas.height);
        c.save();
        c.translate(0.5, 0.5);

        for(var i = 0; i < nodes.length; i++) {
            c.lineWidth = 1;
            c.fillStyle = c.strokeStyle = (nodes[i] === selectedObject) ? 'blue' : 'black';
            nodes[i].draw(c);
        }
        for(var i = 0; i < links.length; i++) {
            c.lineWidth = 1;
            c.fillStyle = c.strokeStyle = (links[i] === selectedObject) ? 'blue' : 'black';
            links[i].draw(c);
        }
        if(currentLink !== null) {
            c.lineWidth = 1;
            c.fillStyle = c.strokeStyle = 'black';
            currentLink.draw(c);
        }

        c.restore();
    }

    function draw() {
        drawUsing(canvas.getContext('2d'));       
        saveBackup();
    }

    function selectObject(x, y) {
        for(var i = 0; i < nodes.length; i++) {
            if(nodes[i].containsPoint(x, y)) {
                return nodes[i];
            }
        }
        for(var i = 0; i < links.length; i++) {
            if(links[i].containsPoint(x, y)) {
                return links[i];
            }
        }
        return null;
    }

    function snapNode(node) {
        for(var i = 0; i < nodes.length; i++) {
            if(nodes[i] === node){
                continue;
            }

            if(Math.abs(node.x - nodes[i].x) < snapToPadding) {
                node.x = nodes[i].x;
            }

            if(Math.abs(node.y - nodes[i].y) < snapToPadding) {
                node.y = nodes[i].y;
            }
        }
    }

    function init() {
        textArea = $(this);
        canvas = document.createElement("canvas");
        canvas.setAttribute("id", "canvas");
        canvas.setAttribute("width", "800");
        canvas.setAttribute("height", "600");
        canvas.setAttribute("style", "background-color: white");
        $(canvas).insertBefore(textArea);
        restoreBackup();
        draw();

        canvas.onmousedown = function(e) {
            var mouse = crossBrowserRelativeMousePos(e);
            selectedObject = selectObject(mouse.x, mouse.y);
            movingObject = false;
            originalClick = mouse;

            if(selectedObject !== null) {
                if(shift && selectedObject instanceof Node) {
                    currentLink = new SelfLink(selectedObject, mouse);
                } else {
                    movingObject = true;
                    if(selectedObject.setMouseStart) {
                        selectedObject.setMouseStart(mouse.x, mouse.y);
                    }
                }
                resetCaret();
            } else if(shift) {
                currentLink = new TemporaryLink(mouse, mouse);
            }

            draw();

            if(canvasHasFocus()) {
                // disable drag-and-drop only if the canvas is already focused
                return false;
            } else {
                // otherwise, let the browser switch the focus away from wherever it was
                resetCaret();
                return true;
            }
        };

        canvas.ondblclick = function(e) {
            var mouse = crossBrowserRelativeMousePos(e);
            selectedObject = selectObject(mouse.x, mouse.y);

            if(selectedObject === null) {
                selectedObject = new Node(mouse.x, mouse.y);
                nodes.push(selectedObject);
                resetCaret();
                draw();
            } else if(selectedObject instanceof Node) {
                selectedObject.isAcceptState = !selectedObject.isAcceptState;
                draw();
            }
        };

        canvas.onmousemove = function(e) {
            var mouse = crossBrowserRelativeMousePos(e);

            if(currentLink !== null) {
                var targetNode = selectObject(mouse.x, mouse.y);
                if(!(targetNode instanceof Node)) {
                    targetNode = null;
                }

                if(selectedObject === null) {
                    if(targetNode !== null) {
                        currentLink = new StartLink(targetNode, originalClick);
                    } else {
                        currentLink = new TemporaryLink(originalClick, mouse);
                    }
                } else {
                    if(targetNode === selectedObject) {
                        currentLink = new SelfLink(selectedObject, mouse);
                    } else if(targetNode !== null) {
                        currentLink = new Link(selectedObject, targetNode);
                    } else {
                        currentLink = new TemporaryLink(selectedObject.closestPointOnCircle(mouse.x, mouse.y), mouse);
                    }
                }
                draw();
            }

            if(movingObject) {
                selectedObject.setAnchorPoint(mouse.x, mouse.y);
                if(selectedObject instanceof Node) {
                    snapNode(selectedObject);
                }
                draw();
            }
        };

        canvas.onmouseup = function() {
            movingObject = false;

            if(currentLink !== null) {
                if(!(currentLink instanceof TemporaryLink)) {
                    selectedObject = currentLink;
                    links.push(currentLink);
                    resetCaret();
                }
                currentLink = null;
                draw();
            }
        };
    }

    var shift = false; 

    document.onkeydown = function(e) {
        var key = crossBrowserKey(e);

        if(key === 16) {
            shift = true;
        } else if(!canvasHasFocus()) {
            // don't read keystrokes when other things have focus
            return true;
        } else if(key === 8) { // backspace key
            if(selectedObject !== null && 'text' in selectedObject) {
                selectedObject.text = selectedObject.text.substr(0, selectedObject.text.length - 1);
                resetCaret();
                draw();
            }

            // backspace is a shortcut for the back button, but do NOT want to change pages
            return false;
        } else if(key === 46) { // delete key
            if(selectedObject !== null) {
                for(var i = 0; i < nodes.length; i++) {
                    if(nodes[i] === selectedObject) {
                        nodes.splice(i--, 1);
                    }
                }
                for(var i = 0; i < links.length; i++) {
                    if(links[i] === selectedObject ||
                       links[i].node === selectedObject ||
                       links[i].nodeA === selectedObject ||
                       links[i].nodeB === selectedObject) {
                        links.splice(i--, 1);
                    }
                }
                selectedObject = null;
                draw();
            }
        }
    };

    document.onkeyup = function(e) {
        var key = crossBrowserKey(e);

        if(key === 16) {
            shift = false;
        }
    };

    document.onkeypress = function(e) {
        // don't read keystrokes when other things have focus
        var key = crossBrowserKey(e);
        if(!canvasHasFocus()) {
            // don't read keystrokes when other things have focus
            return true;
        } else if(key >= 0x20 &&
                  key <= 0x7E &&
                  !e.metaKey &&
                  !e.altKey &&
                  !e.ctrlKey &&
                  selectedObject !== null &&
                  'text' in selectedObject) {
            selectedObject.text += String.fromCharCode(key);
            resetCaret();
            draw();

            // don't let keys do their actions (like space scrolls down the page)
            return false;
        } else if(key === 8) {
            // backspace is a shortcut for the back button, but do NOT want to change pages
            return false;
        }
    };

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

    function det(a, b, c, d, e, f, g, h, i) {
        return a*e*i + b*f*g + c*d*h - a*f*h - b*d*i - c*e*g;
    }

    function circleFromThreePoints(x1, y1, x2, y2, x3, y3) {
        var a = det(x1, y1, 1, x2, y2, 1, x3, y3, 1);
        var bx = -det(x1*x1 + y1*y1, y1, 1, x2*x2 + y2*y2, y2, 1, x3*x3 + y3*y3, y3, 1);
        var by = det(x1*x1 + y1*y1, x1, 1, x2*x2 + y2*y2, x2, 1, x3*x3 + y3*y3, x3, 1);
        var c = -det(x1*x1 + y1*y1, x1, y1, x2*x2 + y2*y2, x2, y2, x3*x3 + y3*y3, x3, y3);
        return {
            'x': -bx / (2*a),
            'y': -by / (2*a),
            'radius': Math.sqrt(bx*bx + by*by - 4*a*c) / (2*Math.abs(a))
        };
    }

    function restoreBackup() {
        if(!JSON) {
            return;
        }
        
        try {
            // load up the student's previous answer
            var backup = JSON.parse(textArea.val());
            
            for(var i = 0; i < backup.nodes.length; i++) {
                var backupNode = backup.nodes[i];
                var backupNodeLayout = backup.nodeGeometry[i];
                var node = new Node(backupNodeLayout.x, backupNodeLayout.y);
                node.isAcceptState = backupNode.isAcceptState;
                node.text = backupNode.label;
                nodes.push(node);
            }
            
            for(var i = 0; i < backup.links.length; i++) {
                var backupLink = backup.edges[i];
                var backupLinkLayout = backup.edgeGeometry[i];
                var link = null;
                if(backupLink.type === 'SelfLink') {
                    link = new SelfLink(nodes[backupLink.fromNode]);
                    link.anchorAngle = backupLinkLayout.anchorAngle;
                    link.text = backupLink.text;
                } else if(backupLink.type === 'StartLink') {
                    link = new StartLink(nodes[backupLink.fromNode]);
                    link.deltaX = backupLinkLayout.deltaX;
                    link.deltaY = backupLinkLayout.deltaY;
                    link.text = backupLink.text;
                } else if(backupLink.type === 'Link') {
                    link = new Link(nodes[backupLink.fromNode], nodes[backupLink.toNode]);
                    link.parallelPart = backupLinkLayout.parallelPart;
                    link.perpendicularPart = backupLinkLayout.perpendicularPart;
                    link.text = backupLink.text;
                    link.lineAngleAdjust = backupLinkLayout.lineAngleAdjust;
                }
                if(link !== null) {
                    links.push(link);
                }
            }
        } catch(e) {
            // error loading previous answer
            console.error(e);
        }
    }
    
    function saveBackup() {
        if(!JSON) {
            return;
        }
        
        var backup = {
            'edgeGeometry': [],
            'nodeGeometry': [],
            'nodes': [],
            'edges': [],
        };
        
        for(var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            
            var nodeData = {
                'label': node.text,
                'isAcceptState': node.isAcceptState,
            };
            var nodeLayout = {
                'x': node.x,
                'y': node.y,
            };
            
            backup.nodeGeometry.push(nodeLayout);
            backup.nodes.push(nodeData);
        }
        
        for(var i = 0; i < links.length; i++) {
            var link = links[i];
            var linkData = null;
            var linkLayout = null;
            
            if(link instanceof SelfLink) {
                linkLayout = {
                    'anchorAngle': link.anchorAngle,
                };
                linkData = {
                    'fromNode': nodes.indexOf(link.node),
                    'toNode': nodes.indexOf(link.node),
                    'label': link.text,
                    'type': 'SelfLink',
                };
            } else if(link instanceof StartLink) {
                linkLayout = {
                    'deltaX': link.deltaX,
                    'deltaY': link.deltaY,
                };
                linkData = {
                    'fromNode': nodes.indexOf(link.node),
                    'toNode': nodes.indexOf(link.node),
                    'label': link.text,
                    'type': 'StartLink',
                };
                
            } else if(link instanceof Link) {
                linkLayout = {
                    'lineAngleAdjust': link.lineAngleAdjust,
                    'parallelPart': link.parallelPart,
                    'perpendicularPart': link.perpendicularPart,
                };
                linkData = {
                    'fromNode': nodes.indexOf(link.nodeA),
                    'toNode': nodes.indexOf(link.nodeB),
                    'label': link.text,
                    'type': 'Link',
                };
            }
            if(linkData !== null && linkLayout !== null) {
                backup.edges.push(linkData);
                backup.edgeGeometry.push(linkLayout);
            } 
        }
        textArea.val(JSON.stringify(backup));
        
    }
      
   
    function initQuestionTA(taId) {
        $(document.getElementById(taId)).each(init);
    }

    return {
        init: init,
        initQuestionTA : initQuestionTA
    };
});