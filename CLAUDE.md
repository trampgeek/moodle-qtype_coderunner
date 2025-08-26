# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CodeRunner is a Moodle question type plugin for creating programming questions. Students write code that gets executed in sandboxes to test correctness. This is a mature plugin (version 5.7.1+) supporting multiple programming languages with sophisticated templating and UI customization.

## Core Architecture

### Key Components
- **Question Type**: `questiontype.php` - Main Moodle question type implementation
- **Question Model**: `question.php` - Question instance representing a CodeRunner question
- **Sandboxes**: `classes/sandbox.php`, `classes/jobesandbox.php` - Code execution environments
  - JobeSandbox is the primary sandbox connecting to Jobe servers
  - Multiple sandbox implementations available (Ideone, local)
- **Templates**: Twig-based template system for test case generation and grading
- **UI Plugins**: JavaScript-based interfaces (Ace editor, scratchpad, table, graph, etc.)
- **Graders**: Different grading mechanisms (equality, regex, template-based)

### Data Flow
1. Student submits code through UI plugin
2. Question processes submission using templates
3. Code executed in sandbox against test cases  
4. Results graded and displayed to student

## Development Commands

### Testing
- **PHPUnit Tests**: `moodle-plugin-ci phpunit` (requires Moodle plugin CI setup)
- **Behat Tests**: `moodle-plugin-ci behat --profile chrome` (functional tests)
- **Individual Test**: Use Moodle's standard PHPUnit runner for specific test classes

### Code Quality
- **PHP Lint**: `moodle-plugin-ci phplint`  
- **Code Checker**: `moodle-plugin-ci codechecker --max-warnings 0`
- **PHP Mess Detector**: `moodle-plugin-ci phpmd`
- **Copy/Paste Detector**: `moodle-plugin-ci phpcpd`
- **PHPDoc Checker**: `moodle-plugin-ci phpdoc`

### Build Tools
- **Grunt**: `moodle-plugin-ci grunt` (for minifying AMD modules)
- **Mustache Lint**: `moodle-plugin-ci mustache`
- **Validation**: `moodle-plugin-ci validate`

### Local Development Setup
1. Requires Moodle 4.3+ with PHP 8.1+
2. Install companion plugin: `qbehaviour_adaptive_adapted_for_coderunner`
3. Set up Jobe server or JobeInABox for sandbox execution
4. Configure test sandbox in `tests/fixtures/test-sandbox-config.php`

## File Structure Highlights

### Core Files
- `version.php` - Plugin version and dependencies
- `settings.php` - Admin settings for sandbox configuration
- `db/` - Database schema, caches, tasks, and built-in prototypes
- `classes/` - Main PHP classes for sandboxes, graders, utilities

### UI Components  
- `amd/src/` - JavaScript AMD modules for UI plugins
- `templates/` - Mustache templates for rendering
- `styles.css` - Plugin styles

### Question Assets
- `samples/` - Example questions and prototypes
- `unsupportedquestiontypes/` - Community-contributed question types
- `ace/` - Ace editor integration files

### Testing
- `tests/` - PHPUnit test classes and Behat features
- `tests/fixtures/` - Test data and sandbox configuration

## Key Concepts

### Prototypes
Built-in question types are defined as prototypes in `db/builtin_PROTOTYPES.xml`. Custom prototypes can be created through the UI.

### Templates
Questions use Twig templates to:
- Generate test code from test cases
- Process student answers
- Grade submissions with custom logic
- Support per-test-case or combinator grading

### UI Plugins
Multiple UI options available:
- **Ace**: Syntax-highlighted code editor (default)
- **Scratchpad**: Ace + in-browser code execution
- **Table**: Spreadsheet-like interface
- **Graph**: Visual graph editing
- **HTML**: Custom HTML interfaces

### Sandboxes
Code execution environments:
- **Jobe**: Default HTTP-based sandbox server
- **Ideone**: Cloud-based execution (legacy)
- **Local**: Direct server execution (limited)

## Development Notes

- Follow Moodle coding standards
- Use dependency injection pattern for sandbox selection
- Template parameters support randomization for unique student questions  
- Extensive caching system for grading performance
- Web service API available for external integrations
- Comprehensive backup/restore support