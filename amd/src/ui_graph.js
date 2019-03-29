// This file is part of Moodle - http://moodle.org/
//
// Much of this code is from Finite State Machine Designer:
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

/**
 * JavaScript to interfdigraph2 to the Graph editor, which is used both in
 * the author editing page and by the student question submission page.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



define(['jquery', 'qtype_coderunner/graphutil', 'qtype_coderunner/graphelements'], function($, util, elements) {

    /***********************************************************************
     *
     * A GraphCanvas is a wrapper for a Graph's HTML canvas
     * object.
     *
     ************************************************************************/

    function GraphCanvas(parent, canvasId, w, h) {
        // Constructor, given the Graph that owns this canvas, the
        // required canvasId and the height and width of the wrapper that
        // encloses the Canvas.

        this.HANDLE_SIZE = 10;

        this.parent = parent;
        this.canvas = $(document.createElement("canvas"));
        this.canvas.attr({
            id:         canvasId,
            class:      "coderunner_graphcanvas",
            tabindex:   1 // So canvas can get focus.
        });
        this.canvas.css({'background-color': 'white'});

        this.canvas.on('mousedown', function(e) {
            return parent.mousedown(e);
        });

        this.canvas.on('mouseup', function(e) {
            return parent.mouseup(e);
        });

        this.canvas.on('dblclick', function(e) {
            return parent.dblclick(e);
        });

        this.canvas.on('keydown', function(e) {
            return parent.keydown(e);
        });

        this.canvas.on('mousemove', function(e) {
            return parent.mousemove(e);
        });

        this.canvas.on('keypress', function(e) {
            return parent.keypress(e);
        });

        this.resize = function(w, h) {
            // Resize to given dimensions.
            this.canvas.attr("width", w);
            this.canvas.attr("height", h);
        };

        this.resize(w, h);
    }

    /***********************************************************************
     *
     *  This is the ui component for a graph-drawing coderunner question.
     *
     ***********************************************************************/

    function Graph(textareaId, width, height, templateParams) {
        // Constructor.
        var save_this = this;

        this.SNAP_TO_PADDING = 6;
        this.DUPLICATE_LINK_OFFSET = 16; // Pixels offset for a duplicate link
        this.HIT_TARGET_PADDING = 6;    // Pixels.
        this.DEFAULT_NODE_RADIUS = 26;  // Pixels. Template parameter noderadius can override this.
        this.DEFAULT_FONT_SIZE = 20;    // px. Template parameter fontsize can override this.

        this.canvasId = 'graphcanvas_' + textareaId;
        this.textArea = $(document.getElementById(textareaId));
        this.helpText = ''; // Obtained by JSON - see below
        this.readOnly = this.textArea.prop('readonly');
        this.templateParams = templateParams;
        this.graphCanvas = new GraphCanvas(this,  this.canvasId, width, height);
        this.caretVisible = true;
        this.caretTimer = 0;  // Need global so we can kill a running timer.
        this.originalClick = null;
        this.nodes = [];
        this.links = [];
        this.helpBox = new elements.HelpBox(this, 0, 0);
        this.helpBoxHighlighted = false;
        this.selectedObject = null; // Either a elements.Link or a elements.Node.
        this.currentLink = null;
        this.movingObject = false;
        this.fail = false;  // Will be set true if reload fails (can't deserialise).
        this.failString = null;  // Language string key for fail error message
        require(['core/str'], function(str) {
            // Get help text via AJAX
            var helpPresent = str.get_string('graphhelp', 'qtype_coderunner');
            $.when(helpPresent).done(function(graphhelp) {
                save_this.helpText = graphhelp;
            });
        });
        this.reload();
        if (!this.fail) {
            this.draw();
        }
    }

    Graph.prototype.failed = function() {
        return this.fail;
    };

    Graph.prototype.failMessage = function() {
        return this.failString;
    };

    Graph.prototype.getElement = function() {
        return this.getCanvas();
    };

    Graph.prototype.hasFocus = function() {
        return document.activeElement == this.getCanvas();
    };

    Graph.prototype.getCanvas = function() {
        var canvas = this.graphCanvas.canvas[0];
        return canvas;
    };

    Graph.prototype.nodeRadius = function() {
        return this.templateParams.noderadius ? this.templateParams.noderadius : this.DEFAULT_NODE_RADIUS;
    };

    Graph.prototype.fontSize = function() {
        return this.templateParams.fontsize ? this.templateParams.fontsize : this.DEFAULT_FONT_SIZE;
    };

    Graph.prototype.isFsm = function() {
        return this.templateParams.isfsm !== undefined ? this.templateParams.isfsm : true;
    };

    // Draw an arrow head if this is a directed graph. Otherwise do nothing.
    Graph.prototype.arrowIfReqd = function(c, x, y, angle) {
        if (this.templateParams.isdirected === undefined || this.templateParams.isdirected) {
            util.drawArrow(c, x, y, angle);
        }
    };

    // Copy the serialised version of the graph to the TextArea.
    Graph.prototype.sync = function() {
        // Nothing to do ... always sync'd
    };


    Graph.prototype.keypress = function(e) {
        var key = util.crossBrowserKey(e);

        if (this.readOnly) {
            return;
        }

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

            // Don't let keys do their actions (like space scrolls down the page).
            return false;
        } else if(key === 8 || key === 0x20 || key === 9) {
            // Disable scrolling on backspace, tab and space.
            return false;
        }
    };

    Graph.prototype.mousedown = function(e) {
        var mouse = util.crossBrowserRelativeMousePos(e);

        if (this.readOnly) {
            return;
        }

        this.selectedObject = this.selectObject(mouse.x, mouse.y);
        this.movingObject = false;
        this.originalClick = mouse;

        if(this.selectedObject !== null) {
            if(e.shiftKey && this.selectedObject instanceof elements.Node) {
                this.currentLink = new elements.SelfLink(this, this.selectedObject, mouse);
            } else {
                this.movingObject = true;
                if(this.selectedObject.setMouseStart) {
                    this.selectedObject.setMouseStart(mouse.x, mouse.y);
                }
            }
            this.resetCaret();
        } else if(e.shiftKey && this.isFsm()) {
            this.currentLink = new elements.TemporaryLink(this, mouse, mouse);
        }

        this.draw();

        if(this.hasFocus()) {
            // Disable drag-and-drop only if the canvas is already focused.
            return false;
        } else {
            // Otherwise, let the browser switch the focus away from wherever it was.
            this.resetCaret();
            return true;
        }
    };

    Graph.prototype.keydown = function(e) {
        var key = util.crossBrowserKey(e);

        if (this.readOnly) {
            return;
        }

        if(key === 8) { // Backspace key.
            if(this.selectedObject !== null && 'text' in this.selectedObject) {
                this.selectedObject.text = this.selectedObject.text.substr(0, this.selectedObject.text.length - 1);
                this.resetCaret();
                this.draw();
            }

            // Backspace is a shortcut for the back button, but do NOT want to change pages.
            return false;
        } else if(key === 46) { // Delete key.
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
        } else if(key === 13) { // Enter key.
            if(this.selectedObject !== null) {
                // Deselect the object.
                this.selectedObject = null;
                this.draw();
            }
        }
    };

    Graph.prototype.dblclick = function(e) {
        var mouse = util.crossBrowserRelativeMousePos(e);

        if (this.readOnly) {
            return;
        }

        this.selectedObject = this.selectObject(mouse.x, mouse.y);

        if(this.selectedObject === null) {
            this.selectedObject = new elements.Node(this, mouse.x, mouse.y);
            this.nodes.push(this.selectedObject);
            this.resetCaret();
            this.draw();
        } else {
            if(this.selectedObject instanceof elements.Node) {
                this.selectedObject.isAcceptState = !this.selectedObject.isAcceptState;
                this.draw();
            }
        }
    };

    Graph.prototype.resize = function(w, h) {
        this.graphCanvas.resize(w, h);
        this.draw();
    };

    Graph.prototype.mousemove = function(e) {
        var mouse = util.crossBrowserRelativeMousePos(e),
            closestPoint,
            mouseInHelpBox = this.helpBox.containsPoint(mouse.x, mouse.y);

        if (this.readOnly) {
            return;
        }

        if (mouseInHelpBox != this.helpBoxHighlighted) {
            this.helpBoxHighlighted = mouseInHelpBox;
            this.draw();
        }

        if(this.currentLink !== null) {
            var targetNode = this.selectObject(mouse.x, mouse.y);
            if(!(targetNode instanceof elements.Node)) {
                targetNode = null;
            }

            if(this.selectedObject === null) {
                if(targetNode !== null) {
                    this.currentLink = new elements.StartLink(this, targetNode, this.originalClick);
                } else {
                    this.currentLink = new elements.TemporaryLink(this, this.originalClick, mouse);
                }
            } else {
                if(targetNode === this.selectedObject) {
                    this.currentLink = new elements.SelfLink(this, this.selectedObject, mouse);
                } else if(targetNode !== null) {
                    this.currentLink = new elements.Link(this, this.selectedObject, targetNode);
                } else {
                    closestPoint = this.selectedObject.closestPointOnCircle(mouse.x, mouse.y);
                    this.currentLink = new elements.TemporaryLink(this, closestPoint, mouse);
                }
            }
            this.draw();
        }

        if(this.movingObject) {
            this.selectedObject.setAnchorPoint(mouse.x, mouse.y);
            if(this.selectedObject instanceof elements.Node) {
                this.snapNode(this.selectedObject);
            }
            this.draw();
        }
    };

    Graph.prototype.mouseup = function() {

        if (this.readOnly) {
            return;
        }

        this.movingObject = false;

        if(this.currentLink !== null) {
            if(!(this.currentLink instanceof elements.TemporaryLink)) {
                this.selectedObject = this.currentLink;
                this.addLink(this.currentLink);
                this.resetCaret();
            }
            this.currentLink = null;
            this.draw();
        }
    };

    Graph.prototype.selectObject = function(x, y) {
        if (this.helpBox.containsPoint(x, y) && this.selectedObject != this.helpBox) {
            // Clicking the help box menu item toggles its select state.
            return this.helpBox;
        }

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

    Graph.prototype.snapNode = function(node) {
        for(var i = 0; i < this.nodes.length; i++) {
            if(this.nodes[i] === node){
                continue;
            }

            if(Math.abs(node.x - this.nodes[i].x) < this.SNAP_TO_PADDING) {
                node.x = this.nodes[i].x;
            }

            if(Math.abs(node.y - this.nodes[i].y) < this.SNAP_TO_PADDING) {
                node.y = this.nodes[i].y;
            }
        }
    };

    // Add a new link (always 'this.currentLink') to the set of links.
    // If the link connects two nodes already linked, the angle of the new link
    // is tweaked so it is distinguishable from the existing links.
    Graph.prototype.addLink = function(newLink) {
        var maxPerpRHS = null;
        for (var i = 0; i < this.links.length; i++) {
            var link = this.links[i];
            if (link.nodeA === newLink.nodeA && link.nodeB === newLink.nodeB) {
                if (maxPerpRHS === null || link.perpendicularPart > maxPerpRHS) {
                    maxPerpRHS = link.perpendicularPart;
                }
            }
            if (link.nodeA === newLink.nodeB && link.nodeB === newLink.nodeA) {
                if (maxPerpRHS === null || -link.perpendicularPart > maxPerpRHS ) {
                    maxPerpRHS = -link.perpendicularPart;
                }
            }
        }
        if (maxPerpRHS !== null) {
            newLink.perpendicularPart = maxPerpRHS + this.DUPLICATE_LINK_OFFSET;
        }
        this.links.push(newLink);
    };

    Graph.prototype.reload = function() {
        var content = $(this.textArea).val();
        if (content) {
            try {
                // Load up the student's previous answer if non-empty.
                var backup = JSON.parse(content);

                for(var i = 0; i < backup.nodes.length; i++) {
                    var backupNode = backup.nodes[i];
                    var backupNodeLayout = backup.nodeGeometry[i];
                    var node = new elements.Node(this, backupNodeLayout[0], backupNodeLayout[1]);
                    node.isAcceptState = backupNode[1];
                    node.text = backupNode[0].toString();
                    this.nodes.push(node);
                }

                for(var i = 0; i < backup.edges.length; i++) {
                    var backupLink = backup.edges[i];
                    var backupLinkLayout = backup.edgeGeometry[i];
                    var link = null;
                    if(backupLink[0] === backupLink[1]) {
                        // Self link has two identical nodes.
                        link = new elements.SelfLink(this, this.nodes[backupLink[0]]);
                        link.anchorAngle = backupLinkLayout.anchorAngle;
                        link.text = backupLink[2].toString();
                    } else if(backupLink[0] === -1) {
                        link = new elements.StartLink(this, this.nodes[backupLink[1]]);
                        link.deltaX = backupLinkLayout.deltaX;
                        link.deltaY = backupLinkLayout.deltaY;
                    } else {
                        link = new elements.Link(this, this.nodes[backupLink[0]], this.nodes[backupLink[1]]);
                        link.parallelPart = backupLinkLayout.parallelPart;
                        link.perpendicularPart = backupLinkLayout.perpendicularPart;
                        link.text = backupLink[2].toString();
                        link.lineAngleAdjust = backupLinkLayout.lineAngleAdjust;
                    }
                    if(link !== null) {
                        this.links.push(link);
                    }
                }
            } catch(e) {
                this.fail = true;
                this.failString = 'graph_ui_invalidserialisation';
            }
        }
    };

    Graph.prototype.save = function() {

        var backup = {
            'edgeGeometry': [],
            'nodeGeometry': [],
            'nodes': [],
            'edges': [],
        };

        if(!JSON || (this.textArea.val().trim() === '' && this.nodes.length === 0)) {
            return;  // Don't save if we have an empty textbox and no graphic content.
        }

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

            if(link instanceof elements.SelfLink) {
                linkLayout = {
                    'anchorAngle': link.anchorAngle,
                };
                linkData = [this.nodes.indexOf(link.node), this.nodes.indexOf(link.node), link.text];
            } else if(link instanceof elements.StartLink) {
                linkLayout = {
                    'deltaX': link.deltaX,
                    'deltaY': link.deltaY
                };
                linkData = [-1, this.nodes.indexOf(link.node), ""];
            } else if(link instanceof elements.Link) {
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
        this.textArea.val(JSON.stringify(backup));
    };

    Graph.prototype.destroy = function () {
        clearInterval(this.caretTimer); // Stop the caret timer.
        this.graphCanvas.canvas.off();  // Stop all events.
        this.graphCanvas.canvas.remove();

    };

    Graph.prototype.resetCaret = function () {
        var t = this; // For embedded function to access this.

        clearInterval(this.caretTimer);
        this.caretTimer = setInterval(function() {
            t.caretVisible = !t.caretVisible;
            t.draw();
        }, 500);
        this.caretVisible = true;
    };

    Graph.prototype.draw = function () {
        var canvas = this.getCanvas(),
            c = canvas.getContext('2d');

        c.clearRect(0, 0, this.getCanvas().width, this.getCanvas().height);
        c.save();
        c.translate(0.5, 0.5);

        this.helpBox.draw(c, this.selectedObject == this.helpBox, this.helpBoxHighlighted);
        if (this.selectedObject != this.helpBox) {  // Only proceed if help info not showing.

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
        }

        c.restore();
        this.save();
    };

    Graph.prototype.drawText = function(originalText, x, y, angleOrNull, theObject) {
        var c = this.getCanvas().getContext('2d'),
            text = util.convertLatexShortcuts(originalText),
            width,
            dy;

        c.font = this.fontSize() + 'px Arial';
        width = c.measureText(text).width;

        // Center the text.
        x -= width / 2;

        // Position the text intelligently if given an angle.
        if(angleOrNull !== null) {
            var cos = Math.cos(angleOrNull);
            var sin = Math.sin(angleOrNull);
            var cornerPointX = (width / 2 + 5) * (cos > 0 ? 1 : -1);
            var cornerPointY = (10 + 5) * (sin > 0 ? 1 : -1);
            var slide = sin * Math.pow(Math.abs(sin), 40) * cornerPointX - cos * Math.pow(Math.abs(cos), 10) * cornerPointY;
            x += cornerPointX - sin * slide;
            y += cornerPointY + cos * slide;
        }

        // Draw text and caret (round the coordinates so the caret falls on a pixel).
        if('advancedFillText' in c) {
            c.advancedFillText(text, originalText, x + width / 2, y, angleOrNull);
        } else {
            x = Math.round(x);
            y = Math.round(y);
            dy = Math.round(this.fontSize() / 3); // Don't understand this
            c.fillText(text, x, y + dy);
            if(theObject == this.selectedObject && this.caretVisible && this.hasFocus() && document.hasFocus()) {
                x += width;
                dy = Math.round(this.fontSize() / 2);
                c.beginPath();
                c.moveTo(x, y - dy);
                c.lineTo(x, y + dy);
                c.stroke();
            }
        }
    };

    return {
        Constructor: Graph
    };
});
