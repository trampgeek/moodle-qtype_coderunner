# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **CodeRunner**, a Moodle question type plugin for programming questions. It allows educators to create coding questions where students submit code that is executed in sandboxed environments and automatically graded against test cases. The plugin supports multiple programming languages (Python, C/C++, Java, JavaScript, etc.).

**Key Components:**
- Moodle plugin architecture with PHP backend
- JavaScript UI components using AMD modules
- Sandbox integration for secure code execution
- Multiple grading strategies and UI types
- Twig templating system for question customization

## Essential Development Commands

### Running Tests
```bash
# Run all CodeRunner unit tests
sudo -u www-data vendor/bin/phpunit --verbose --testsuite="qtype_coderunner test suite"

# Alternative testsuite name (depending on Moodle version)
sudo -u www-data vendor/bin/phpunit --verbose --testsuite="qtype_coderunner_testsuite"

# Initialize PHPUnit environment (if needed)
sudo php admin/tool/phpunit/cli/init.php
```

### Behat Tests
```bash
# Run specific Behat features
sudo -u www-data vendor/bin/behat --config /var/www/html/moodle/behatdata/behat/behat.yml
```

### Development Scripts
```bash
# Bulk test questions against all available question types
php bulktest.php

# Test prototype questions
php bulktestall.php

# Find duplicate questions
php findduplicates.php

# Purge template cache
php cachepurge.php
```

## Core Architecture

### Main Plugin Files
- `questiontype.php` - Main question type class, handles question metadata and management
- `question.php` - Question instance class, handles question execution and grading
- `renderer.php` - Moodle renderer for question display
- `edit_coderunner_form.php` - Question authoring form

### Key Classes Directory Structure
```
classes/
├── sandbox.php          - Base sandbox class for code execution
├── jobesandbox.php      - Jobe sandbox implementation (primary)
├── grader.php          - Base grader class
├── equality_grader.php  - Standard output comparison
├── template_grader.php  - Custom template-based grading
├── twig.php            - Twig template engine integration
├── ui_plugins.php      - User interface plugin system
└── external/
    └── run_in_sandbox.php - Web service for external code execution
```

### Frontend Components
```
amd/src/
├── ui_ace.js           - ACE code editor integration
├── ui_graph.js         - Graph-based questions
├── ui_scratchpad.js    - Scratchpad UI for working area
├── textareas.js        - Enhanced textarea handling
├── authorform.js       - Question authoring interface
└── userinterfacewrapper.js - UI plugin wrapper system
```

### Template System
- Uses **Twig** templating engine for question customization
- Templates in `db/builtin_PROTOTYPES.xml` define built-in question types
- Custom templates can be created for specialized question types
- Template parameters allow dynamic question generation

### Sandbox Architecture
- **Primary**: Jobe server (external sandbox service)
- **Alternative**: Local sandbox (limited, for development)
- **Legacy**: Ideone API integration
- All code execution happens in isolated environments for security

### UI Plugin System
CodeRunner supports multiple UI types:
- **ACE**: Full-featured code editor with syntax highlighting
- **Gapfiller**: Fill-in-the-blanks style coding questions
- **Graph**: Visual graph-based questions
- **HTML**: Custom HTML interfaces
- **Scratchpad**: Working area with separate answer submission
- **Table**: Tabular data input

### Testing Infrastructure
- Comprehensive PHPUnit test suite in `tests/` directory
- Behat tests for browser-based testing in `tests/behat/`
- Each programming language has dedicated test files
- Template and grading system tests included

## Important Configuration
- Requires Moodle 4.3+ and PHP 8.1+
- Sandbox configuration in admin settings or `classes/` files
- Question prototypes loaded from XML files in `db/`
- Twig security policies defined in `classes/twig_security_policy.php`

## Development Notes
- This is a **defensive security** educational tool - all code execution is sandboxed
- Question authors can create custom templates but within security constraints
- Multi-language support through prototype system
- Extensive documentation in `Readme.md` and `authorguide.md`