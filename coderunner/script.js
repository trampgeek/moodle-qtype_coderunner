// Prevent multiple submissions while a question is being graded.
// Done by adding the id attribute containing the word "grading",
// which is detected by the CSS and used to grey the text. Subsequent
// clicks are then ignored. [Seems complex but simply disabling the 
// button doesn't work as Moodle then won't correctly postprocess the
// click.]

// TODO Get the "Grading, please wait" message from the lang file somehow.

function submitClicked(e) {
	var evt = window.event || e  //cross browser event object
	if (!evt.target) // More cross browser hacks
		evt.target = evt.srcElement

	if (!evt.target.id) {
		evt.target.id = evt.target.name + " grading"
		evt.target.value = "Grading, please wait"
	}
	else {
		if (evt.preventDefault) { //supports preventDefault?
			evt.preventDefault()
		}
		else  {//IE
			return false
		}
	}
}


// Script for editing the textbox, ripped off codingbat.com
	
function insertNewline(ta) {
  if (ta.selectionStart != undefined) {  // firefox etc.
    var before = ta.value.substring(0, ta.selectionStart);
    var indent = figureIndent(before);
    var selSave = ta.selectionEnd;
    var after = ta.value.substring(ta.selectionEnd, ta.value.length)       

    // update the text field
    var tmp = ta.scrollTop;  // inhibit annoying auto-scroll
    ta.value = before + "\n" + indent + after;
    var pos = selSave + 1 + indent.length;
    ta.selectionStart = pos;
    ta.selectionEnd = pos;
    ta.scrollTop = tmp;
    return false;
  }
  else if (document.selection && document.selection.createRange) { // IE
     var r = document.selection.createRange()
     var dr = r.duplicate()
     dr.moveToElementText(ta)
     dr.setEndPoint("EndToEnd", r)
     var c = dr.text.length - r.text.length
     var b = ta.value.substring(0, c);
     var i = figureIndent(b);
     if (i == "") return true;  // let natural event happen
     r.text = "\n" + i;
     return false;
  }
     
  return true;
}

// Disabled tab capability for now as can't get it working well and
// questionable if that's a good interface in a browser anyway.
function insertTab(ta, e) {
  var pos
  if (ta.selectionStart != undefined) {  // firefox etc.
    var before = ta.value.substring(0, ta.selectionStart);
    var spaces = figureTab(before);
    var selSave = ta.selectionEnd;
    var after = ta.value.substring(ta.selectionEnd, ta.value.length)       

    // update the text field
    var tmp = ta.scrollTop;  // inhibit annoying auto-scroll
    ta.value = before + spaces + after;
    pos = selSave + 1 + spaces.length;
    ta.selectionStart = pos;
    ta.selectionEnd = pos;
    ta.scrollTop = tmp;
  }
  else if (document.selection && document.selection.createRange) { // IE
     var r = document.selection.createRange()
     var dr = r.duplicate()
     dr.moveToElementText(ta)
     dr.setEndPoint("EndToEnd", r)
     var c = dr.text.length - r.text.length
     var b = ta.value.substring(0, c);
     r.text = figureTab(b);

  }
  else return true  // Can't handle this browser
  
  startCursor2(ta, pos)
  e.preventDefault()
  return false
}

// given text running up to cursor, return spaces to put at
// start of next line.
function figureIndent(str) {
  var eol = str.lastIndexOf("\n");
  // eol==-1 works ok
  var line = str.substring(eol + 1);  // take from eol to end
  var indent="";
  for (i=0; i<line.length && line.charAt(i)==' '; i++) {
    indent = indent + " ";
  }
  return indent;
}


//given text running up to cursor, return spaces to insert on a tab
function figureTab(str) {
var eol = str.lastIndexOf("\n");
// eol==-1 works ok
var line = str.substring(eol + 1);  // take from eol to end
var len = line.length
var spaces="";
do {
	spaces = spaces + " "
 	len++
 } while (len % 4 != 0)
return spaces
}

function ignoreNL(e) {
  if (e.which !== undefined) { // FF etc
	    keynum = e.which
  }
  else if (e.keyCode !== undefined) {  // Safari, IE4+
    keynum = e.keyCode
  } 
  else { // ?
    keynum = window.e.keyCode
  }
  if (keynum==13) {
    e.preventDefault()
	return false
  }
  return true
}

function keydown(e, element) {
  if (e.which !== undefined) { // FF etc
    keynum = e.which
  }
  else if (e.keyCode !== undefined) {  // Safari, IE4+
    keynum = e.keyCode
  } 
  else { // ?
    keynum = window.e.keyCode
  }
  if (keynum==13) return insertNewline(element)
  // else if (keynum==9) return insertTab(element, e)
  else return true;
}

// Put cursor blinking at the end of the last code line or at pos if given
function startCursor(ta, pos) {

  if (pos === undefined) pos = ta.length - 1;
  if (ta.selectionStart != undefined ) {
	ta.focus()
    ta.selectionStart = pos;
    ta.selectionEnd = pos;
  }
  else if (ta.createTextRange) { // IE
    var range = ta.createTextRange();
    range.collapse(true);
    range.move('character', pos-2);
    range.select();
  }
}
