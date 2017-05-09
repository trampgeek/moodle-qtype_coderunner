# UI PLUGIN

May 2017

Author: Emily Price, University of Canterbury, New Zealand.


## What is it?
UI Plugin is an in-progress endeavour to enable Coderunner question authors to use
any JavaScript plugin as the user interface for question answering.

## Motivation
The desire to create a JavaScript finite state machine drawing user input system
 for the COSC261 course at UC.

## Current state
At the moment, there are three hard coded options for JavaScript UIs: Ace (as developed
by Tim Hunt), MultiChoice (created by yours truly), and None - exactly what it 
sounds like.

### A note on "Use Ace"
With the introduction of changeable UI plugins, the meaning of "Use Ace" has 
been altered. It currently applies only to code areas displayed to the question 
author. To enable Ace for students, it must be selected from the UI plugin dropdown.

## Goals
The future goal is to allow any question author to create their own JavaScript 
user interface, with the ability for these to be 'plugged in' - that is, not 
hard coded in a list :) 