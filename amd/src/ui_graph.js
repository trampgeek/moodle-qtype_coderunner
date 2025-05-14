/**
 * This file is part of Moodle - http:moodle.org/
 *
 * Much of this code is from Finite State Machine Designer:
 */
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
/**
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more util.details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http:www.gnu.org/licenses/>.
 */

/**
 * JavaScript to interface to the Graph editor, which is used both in
 * the author editing page and by the student question submission page.
 *
 * @module qtype_coderunner/ui_graph
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'qtype_coderunner/graphutil', 'qtype_coderunner/graphelements'], function($, util, elements) {

    /**
     * Constructor for a GraphCanvas object, which is a wrapper for a Graph's HTML canvas
     * object.
     * @param {object} parent The Graph that owns this object.
     * @param {string} canvasId The ID of the HTML canvas to be wrapped by this object.
     * @param {int} w The required width of the wrapper.
     * @param {int} h The required height of the wrapper.
     */
    function GraphCanvas(parent, canvasId, w, h) {
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

        /**
         * Resize this object to then given dimensions.
         * @param {int} w Required width.
         * @param {int} h Required height.
         */
        this.resize = function(w, h) {
            this.canvas.attr("width", w);
            this.canvas.attr("height", h);
        };

        this.resize(w, h);
    }

    /**
     *  Constructor for the Graph object.
     *  This is the ui component for a graph-drawing coderunner question.
     *
     *  Relevant ui parameters:
     *
     *  isfsm. True if the graph is of a Finite State Machine.
     *         If true, the graph can contain an incoming edge from nowhere
     *         (the start edge). Default: true.
     *  isdirected. True if edges are directed. Default: true.
     *  noderadius. The radius of a node, in pixels. Default: 26.
     *  fontsize. The font size used for node and edge labels. Default: 20 points.
     *  textoffset. An offset in pixels used to determine how far from the link
     *             a label is initially positioned. Default 5. Largely defunct
     *             now that link text can be dragged.
     *  helpmenutext. A string to be used in lieu of the default Help info, if supplied.
     *               No default.
     *  locknodepositions. True to prevent the user from moving nodes. Useful when the
     *             answer box is preloaded with a graph that the student has to
     *             annotate by changing node or edge labels or by
     *             adding/removing edges. Note, though that nodes can still be
     *             added and deleted. See locknodeset.
     *  locknodeset. True to prevent the user from adding or deleting nodes, or
     *             toggling node types to/from acceptors.
     *  locknodelabels: True to prevent the user from editing node labels. This
     *             will also prevent any new nodes having non-empty labels.
     *  lockedgepositions. True to prevent the user from dragging edges to change
     *             their curvature. Possibly useful if the answer box is
     *             preloaded with a graph that the student has to annotate by
     *             changing node or edge labels or by adding/removing edges.
     *             Also ensures that edges added by a student are straight, e.g.
     *             to draw a polygon on a set of given points. Note, though that
     *             edges can still be added and deleted. See lockedgeset.
     *  lockedgeset. True to prevent the user from adding or deleting edges.
     *  lockedgelabels: True to prevent the user from editing edge labels. This
     *             also prevents any new edges from having labels.
     * @param {string} textareaId The ID of the html textarea.
     * @param {int} width The width in pixels of the textarea.
     * @param {int} height The height in pixels of the textarea.
     * @param {object} uiParams The UI parameter object.
     */
    function Graph(textareaId, width, height, uiParams) {
        /**
         * Constructor.
         */
        var save_this = this;

        this.SNAP_TO_PADDING = 6;
        this.DUPLICATE_LINK_OFFSET = 16; // Pixels offset for a duplicate link
        this.HIT_TARGET_PADDING = 6;    // Pixels.
        this.DEFAULT_NODE_RADIUS = 26;  // Pixels. UI parameter noderadius can override this.
        this.DEFAULT_FONT_SIZE = 20;    // px. UI parameter fontsize can override this.
        this.DEFAULT_TEXT_OFFSET = 5;   // Link label tweak. UI params can override.
        this.DEFAULT_LINK_LABEL_REL_DIST = 0.5;  // Relative distance along link to place labels
        this.MAX_VERSIONS = 30;  // Maximum number of versions saved for undo/redo

        this.canvasId = 'graphcanvas_' + textareaId;
        this.textArea = $(document.getElementById(textareaId));
        this.helpText = ''; // Obtained by JSON - see below.
        this.readOnly = this.textArea.prop('readonly');
        this.uiParams = uiParams;
        this.graphCanvas = new GraphCanvas(this,  this.canvasId, width, height);
        this.caretVisible = true;
        this.caretTimer = 0;  // Need global so we can kill a running timer.
        this.originalClick = null;
        this.nodes = [];
        this.links = [];
        this.selectedObject = null; // Either a elements.Link or a elements.Node or a elements.Button.
        this.currentLink = null;
        this.movingObject = false;
        this.fail = false;  // Will be set true if reload fails (can't deserialise).
        this.failString = null;  // Language string key for fail error message.
        this.versions = [];
        this.versionIndex = -1; //Index of current state of graph in versions list

        this.helpBox = new elements.HelpBox(this, 0, 0);   // Button that opens a help text box
        this.clearButton = new elements.Button(this, 60, 0, "Clear");    // Button that clears the canvas
        this.clearButton.onClick = function() {
          if (confirm("Are you sure you want to clear the diagram?")) {
              this.parent.clear();
          }
        };
        this.buttons = [this.helpBox, this.clearButton];

        /**
         * Legacy support for locknodes and lockedges.
         */
        if ('locknodes' in uiParams) {
            uiParams.locknodepositions = uiParams.locknodes;
        }
        if ('lockedges' in uiParams) {
            uiParams.lockedgepositions = uiParams.lockedges;
        }

        if ('helpmenutext' in uiParams) {
            this.helpText = uiParams.helpmenutext;
        } else {
          require(['core/str'], function(str) {
                /**
                 * Get help text via AJAX.
                 */
                var helpPresent = str.get_string('graphhelp', 'qtype_coderunner');
                $.when(helpPresent).done(function(graphhelp) {
                    save_this.helpText = graphhelp;
                });
            });
        }
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
        return this.uiParams.noderadius ? this.uiParams.noderadius : this.DEFAULT_NODE_RADIUS;
    };

    Graph.prototype.fontSize = function() {
        return this.uiParams.fontsize ? this.uiParams.fontsize : this.DEFAULT_FONT_SIZE;
    };

    Graph.prototype.isFsm = function() {
        return this.uiParams.isfsm !== undefined ? this.uiParams.isfsm : true;
    };


    Graph.prototype.textOffset = function() {
        return this.uiParams.textoffset ? this.uiParams.textoffset : this.DEFAULT_TEXT_OFFSET;
    };

    /**
     * Draw an arrow head if this is a directed graph. Otherwise do nothing.
     * @param {object} c The graphic context.
     * @param {int} x The x location of the arrow head.
     * @param {int} y The y location of the arrow head.
     * @param {float} angle The angle of the arrow.
     */
    Graph.prototype.arrowIfReqd = function(c, x, y, angle) {
        if (this.uiParams.isdirected === undefined || this.uiParams.isdirected) {
            util.drawArrow(c, x, y, angle);
        }
    };

    /**
     * Copy the serialised version of the graph to the TextArea.
     */
    Graph.prototype.sync = function() {
        /**
         * Nothing to do ... always sync'd.
         */
    };

    /**
     * Disable autosync, too.
     */
    Graph.prototype.syncIntervalSecs = function() {
        return 0;
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
                  key !== 37 &&  //Don't register arrow keys
                  key !== 39 &&
                  this.selectedObject !== null &&
                  this.canEditText()) {
            if (this.selectedObject.justMoved) {
                this.saveVersion();
            }
            this.selectedObject.justMoved = false;
            this.selectedObject.textBox.insertChar(String.fromCharCode(key));
            this.resetCaret();
            this.draw();

            /**
             * Don't let keys do their actions (like space scrolls down the page).
             */
            return false;
        } else if(key === 8 || key === 0x20 || key === 9) {
            /**
             * Disable scrolling on backspace, tab and space.
             */
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
        this.movingGraph = false;
        this.movingText = false;
        this.originalClick = mouse;

        this.saveVersion();

        if (this.selectedObject !== this.helpBox){
            this.helpBox.helpOpen = false;
        }

        if(this.selectedObject !== null) {
            if(this.selectedObject instanceof elements.Button){
               this.selectedObject.onClick();
           } else if(e.shiftKey && this.selectedObject instanceof elements.Node) {
                if (!this.uiParams.lockedgeset) {
                    this.currentLink = new elements.SelfLink(this, this.selectedObject, mouse);
                }
            } else if (e.altKey && this.selectedObject instanceof elements.Node) {
                /**
                 * Moving an entire connected graph component.
                 */
                if (!this.uiParams.locknodepositions) {
                    this.movingGraph = true;
                    this.movingNodes = this.selectedObject.traverseGraph(this.links, []);
                    for (var i = 0; i < this.movingNodes.length; i++) {
                        this.movingNodes[i].setMouseStart(mouse.x, mouse.y);
                    }
                }
            } else if (this.selectedObject instanceof elements.TextBox){
                if (!this.uiParams.lockedgelabels) {
                    this.movingText = true;
                    this.selectedObject.setMouseStart(mouse.x, mouse.y);
                    this.selectedObject = this.selectedObject.parent;
                }
            } else if (!(this.uiParams.locknodepositions && this.selectedObject instanceof elements.Node) &&
                       !(this.uiParams.lockedgepositions && this.selectedObject instanceof elements.Link)){
                this.movingObject = true;
                if(this.selectedObject.setMouseStart) {
                    this.selectedObject.setMouseStart(mouse.x, mouse.y);
                }
            }
            this.selectedObject.justMoved = true;
            this.resetCaret();
        } else if(e.shiftKey && this.isFsm()) {
            this.currentLink = new elements.TemporaryLink(this, mouse, mouse);
        }

        this.draw();

        if(this.hasFocus()) {
            /**
             * Disable drag-and-drop only if the canvas is already focused.
             */
            return false;
        } else {
            /**
             * Otherwise, let the browser switch the focus away from wherever it was.
             */
            this.resetCaret();
            return true;
        }
    };

    /**
     * Return true if currently selected object has text that we are allowed
     * to edit.
     */
    Graph.prototype.canEditText = function() {
        var isNode = this.selectedObject instanceof elements.Node,
            isLink = (this.selectedObject instanceof elements.Link ||
                this.selectedObject instanceof elements.SelfLink);
        return 'textBox' in this.selectedObject &&
               ((isNode && !this.uiParams.locknodelabels) ||
                (isLink && !this.uiParams.lockedgelabels));
    };

    Graph.prototype.keydown = function(e) {
        var key = util.crossBrowserKey(e), i, nodeDeleted=false;

        if (this.readOnly) {
            return;
        }

        if(key === 8) { // Backspace key.
            if(this.selectedObject !== null && this.canEditText()) {
                this.selectedObject.textBox.deleteChar();
                this.resetCaret();
                this.draw();
            }
            /**
             * Backspace is a shortcut for the back button, but do NOT want to change pages.
             */
            return false;
        } else if(key === 46 && this.selectedObject !== null) { // Delete key
            this.saveVersion();
            for (i = 0; i < this.nodes.length; i++) {
                if (this.nodes[i] === this.selectedObject && !this.uiParams.locknodeset) {
                    this.nodes.splice(i--, 1);
                    nodeDeleted = true;
                }
            }
            for (i = 0; i < this.links.length; i++) {
                if((this.links[i] === this.selectedObject && !this.uiParams.lockedgeset) ||
                    nodeDeleted && (
                       this.links[i].node === this.selectedObject ||
                       this.links[i].nodeA === this.selectedObject ||
                       this.links[i].nodeB === this.selectedObject)) {
                    this.links.splice(i--, 1);
                }
            }
            this.selectedObject = null;
            this.draw();
        } else if(key === 13) { // Enter key.
            if(this.selectedObject !== null) {
                /**
                 * Deselect the object.
                 */
                this.selectedObject = null;
                this.draw();
            }
        } else if(key === 37) { // Left arrow key
            if(this.selectedObject !== null && this.canEditText()) {
                this.selectedObject.textBox.caretLeft();
                this.resetCaret();
                this.draw();
                }
        } else if(key === 39) { // Right arrow key
            if(this.selectedObject !== null && this.canEditText()) {
                this.selectedObject.textBox.caretRight();
                this.resetCaret();
                this.draw();
            }
        } else if ((e.keyCode == 90 && e.ctrlKey && e.shiftKey) || (e.keyCode == 89 && e.ctrlKey)) {  //CTRL+SHIFT+z or CTRL+y
            this.redo();
        } else if (e.keyCode == 90 && e.ctrlKey) {  //CTRL+z
            this.undo();
        }
    };

    Graph.prototype.dblclick = function(e) {
        var mouse = util.crossBrowserRelativeMousePos(e);

        if (this.readOnly || this.uiParams.locknodeset) {
            return;
        }

        this.selectedObject = this.selectObject(mouse.x, mouse.y);

        this.saveVersion();

        if(this.selectedObject === null) {
                this.selectedObject = new elements.Node(this, mouse.x, mouse.y);
                this.nodes.push(this.selectedObject);
                this.selectedObject.justMoved = true;
                this.resetCaret();
                this.draw();
        } else {
            if(this.selectedObject instanceof elements.Node && this.isFsm()) {
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
            closestPoint;

        if (this.readOnly) {
            return;
        }

        for (i = 0; i < this.buttons.length; i++){
            if (this.buttons[i].containsPoint(mouse.x, mouse.y)){
                this.buttons[i].highLighted = true;
            }else{
                this.buttons[i].highLighted = false;
            }
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
        if (this.movingGraph) {
            var nodes = this.movingNodes;
            for (var i = 0; i < nodes.length; i++) {
                 nodes[i].trackMouse(mouse.x, mouse.y);
                 this.snapNode(nodes[i]);
            }
            this.draw();
        } else if(this.movingText){
            this.selectedObject.textBox.setAnchorPoint(mouse.x, mouse.y);
            this.draw();
        } else if(this.movingObject) {
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
        this.movingGraph = false;
        this.movingText = false;

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
        for (i = 0; i < this.buttons.length; i++){
            if (this.buttons[i].containsPoint(x, y)){
                return this.buttons[i];
            }
        }
        var i;
        for(i = 0; i < this.nodes.length; i++) {
            if(this.nodes[i].containsPoint(x, y)) {
                return this.nodes[i];
            }
        }
        for(i = 0; i < this.links.length; i++) {
            if(this.links[i].containsPoint(x, y)) {
                return this.links[i];
            }else if ('textBox' in this.links[i] && this.links[i].textBox.containsPoint(x, y)){
                return this.links[i].textBox;
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

    /**
     * Add a new link (always 'this.currentLink') to the set of links.
     * If the link connects two nodes already linked, the angle of the new link
     * is tweaked so it is distinguishable from the existing links.
     * @param {object} newLink The link to be added.
     */
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
                /**
                 * Load up the student's previous answer if non-empty.
                 */
                var backup = JSON.parse(content), i;

                for(i = 0; i < backup.nodes.length; i++) {
                    var backupNode = backup.nodes[i];
                    var backupNodeLayout = backup.nodeGeometry[i];
                    var node = new elements.Node(this, backupNodeLayout[0], backupNodeLayout[1]);
                    node.isAcceptState = backupNode[1];
                    node.textBox = new elements.TextBox(backupNode[0].toString(), node);
                    this.nodes.push(node);
                }

                for(i = 0; i < backup.edges.length; i++) {
                    var backupLink = backup.edges[i];
                    var backupLinkLayout = backup.edgeGeometry[i];
                    var link = null;
                    if(backupLink[0] === backupLink[1]) {
                        /**
                         * Self link has two identical nodes.
                         */
                        link = new elements.SelfLink(this, this.nodes[backupLink[0]]);
                        link.anchorAngle = backupLinkLayout.anchorAngle;
                        link.textBox = new elements.TextBox(backupLink[2].toString(), link);
                        if (backupLink.length > 3) {
                            link.textBox.setAnchorPoint(backupLink[3].x, backupLink[3].y);
                        }
                    } else if(backupLink[0] === -1) {
                        link = new elements.StartLink(this, this.nodes[backupLink[1]]);
                        link.deltaX = backupLinkLayout.deltaX;
                        link.deltaY = backupLinkLayout.deltaY;
                    } else {
                        link = new elements.Link(this, this.nodes[backupLink[0]], this.nodes[backupLink[1]]);
                        link.parallelPart = backupLinkLayout.parallelPart;
                        link.perpendicularPart = backupLinkLayout.perpendicularPart;
                        link.lineAngleAdjust = backupLinkLayout.lineAngleAdjust;
                        link.textBox = new elements.TextBox(backupLink[2].toString(), link);
                        if (backupLink.length > 3) {
                            link.textBox.setAnchorPoint(backupLink[3].x, backupLink[3].y);
                        }
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
        var i;

        if(!JSON || (this.textArea.val().trim() === '' && this.nodes.length === 0)) {
            return;  // Don't save if we have an empty textbox and no graphic content.
        }

        for(i = 0; i < this.nodes.length; i++) {
            var node = this.nodes[i];

            var nodeData = [node.textBox.text, node.isAcceptState];
            var nodeLayout = [node.x, node.y];

            backup.nodeGeometry.push(nodeLayout);
            backup.nodes.push(nodeData);
        }

        for(i = 0; i < this.links.length; i++) {
            var link = this.links[i],
                linkData = null,
                linkLayout = null;

            if(link instanceof elements.SelfLink) {
                linkLayout = {
                    'anchorAngle': link.anchorAngle,
                };
                linkData = [this.nodes.indexOf(link.node), this.nodes.indexOf(link.node), link.textBox.text];
                if (link.textBox.dragged) {
                    linkData.push(link.textBox.position);
                }
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
                linkData = [this.nodes.indexOf(link.nodeA), this.nodes.indexOf(link.nodeB), link.textBox.text];
                if (link.textBox.dragged) {
                    linkData.push(link.textBox.position);
                }
            }
            if (linkData !== null && linkLayout !== null) {
                backup.edges.push(linkData);
                backup.edgeGeometry.push(linkLayout);
            }
        }
        this.textArea.val(JSON.stringify(backup));
    };

    Graph.prototype.saveVersion = function () {
        var curState = this.textArea.val();
        if (this.versions.length == 0 || curState.localeCompare(this.versions[this.versionIndex]) != 0){
            this.versionIndex++;
            while (this.versionIndex < this.versions.length){ //Clear newer versions that have been overwritten by this save
                this.versions.pop();
            }
            this.versions.push(curState);
            if (this.versions.length > this.MAX_VERSIONS){    //Limit the size of this.versions
                this.versions.shift();
                this.versionIndex--;
            }
        }
    };

    Graph.prototype.undo = function () {
        this.saveVersion();
        if (this.versionIndex > 0){
            this.versionIndex--;
            this.textArea.val(this.versions[this.versionIndex]);
            /**
             * Clear graph nodes and links
             */
            this.nodes = [];
            this.links = [];
            /**
             * Reload graph from serialisation
             */
            this.reload();
            this.draw();
        }
    };

    Graph.prototype.redo = function() {
        if (this.versionIndex < this.versions.length - 1){
            this.versionIndex++;
            this.textArea.val(this.versions[this.versionIndex]);
            /**
             * Clear graph nodes and links
             */
            this.nodes = [];
            this.links = [];
            /**
             * Reload graph from serialisation
             */
            this.reload();
            this.draw();
        }
    };

    Graph.prototype.clear = function () {
        this.saveVersion();
        this.nodes = [];
        this.links = [];
        this.save();
        this.draw();
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
            c = canvas.getContext('2d'),
            i;

        c.clearRect(0, 0, this.getCanvas().width, this.getCanvas().height);
        c.save();
        c.translate(0.5, 0.5);

        for (i = 0; i < this.buttons.length; i++){
            this.buttons[i].draw(c);
        }

        if (!this.helpBox.helpOpen) {  // Only proceed if help info not showing.

            for(i = 0; i < this.nodes.length; i++) {
                c.lineWidth = 1;
                c.fillStyle = c.strokeStyle = (this.nodes[i] === this.selectedObject) ? 'blue' : 'black';
                this.nodes[i].draw(c);
            }
            for(i = 0; i < this.links.length; i++) {
                c.lineWidth = 1;
                c.fillStyle = c.strokeStyle = (this.links[i] === this.selectedObject
                                              || this.links[i].textBox === this.selectedObject) ? 'blue' : 'black';
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

    /**
     * Allow fullscreen mode for the Graph UI.
     *
     * @return {Boolean} True if fullscreen mode is allowed, false otherwise.
     */
        Graph.prototype.allowFullScreen = function() {
            return true;
        };

    return {
        Constructor: Graph
    };
});
