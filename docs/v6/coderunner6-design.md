# CodeRunner 6: Separating questions from prototypes

Richard Lobb · 2 November 2026 (v2, prototype ids)

**Draft for comment.** I am investigating the idea that CodeRunner prototypes stop being stored as Moodle questions and become first-class objects created, edited and managed through a separate "prototype manager" interface. This document outlines how that might be achieved and how we might transition to the new design. I am inviting comments, criticisms and suggestions on the proposal.

## 1. Background

The concept of a question prototype emerged when CodeRunner was first developed by merging two separate Moodle plugins that respectively ran Python and C programs, with Java and MATLAB looming as potential needs. I wanted question authors to be able to edit the so-called "template" code that defined how different questions executed. Since I was writing a question-type plugin, I decided to use the existing Moodle question editor to edit the templates as well. I called the "thing" that was being edited - essentially the template plus supporting fields like template parameters, support files and sandbox controls - a "question prototype". But a question prototype is not a complete question: it can't be executed; some of its fields, such as the test cases and the answer, are completely ignored; while its question text field is not the text for a question but is the documentation to be displayed to a question author in the "Details" field.

Storing prototypes as Moodle questions has caused major headaches throughout CodeRunner's history. Moodle is not designed to have dependencies between questions, which has caused issues for us in maintaining the plugin as Moodle itself evolves. It has also been a constant source of problems for question authors plagued by missing or duplicate prototypes.

The aim of this proposal is to stop storing prototypes as Moodle questions and to manage them separately.

**A note on terminology.** A prototype is best thought of as an *uninstantiated question*. It defines how questions of its kind are displayed, run and graded, while each actual question supplies its own instance data: question text, test cases, answer and so on. In that sense the old name "question prototype" remains apt, and this document keeps it. The issues in the current design arise not from calling a prototype a question but from storing it in the question bank as a Moodle question, which Moodle assumes is always complete, self-contained and usable in quizzes.

## 2. Summary of the proposal

The proposal is to pull out the prototype management into a separate plugin through which question authors create, edit and manage their prototypes. Prototypes would no longer reside in the question bank, although during the transition period the old prototypes would remain as hooks into the prototype manager.

The redesign gives us the opportunity to support a richer UI. Inheritance can go deeper than today's two layers: a prototype can derive from another prototype, replacing only the fields it wants to change. A question then derives from its prototype in the same way, adding its own instance data such as question text, test cases and answer.

In the redesign, the question-authoring form no longer has a *Customise* checkbox with its associated semantics that a question either inherits from a prototype or doesn't. Instead, every prototype field starts out inherited: an author overrides only what they need to change and all other fields continue to be inherited. Most of the prototype fields remain hidden during normal question editing - the question author needs to expand a section of the form to edit prototype fields.

Questions and child prototypes refer to their prototype directly, by its identity, instead of finding it by name every time they run. This removes the "missing prototype" and "duplicate prototype" errors, keeps questions working when they or their banks are moved or shared, and lets a course use prototypes from other courses.

Existing questions must keep behaving exactly as they do now. The migration plan in section 10 converts existing prototypes to the new form.

## 3. How CodeRunner works today

Today a prototype is an ordinary CodeRunner question with an *is\_prototype* flag set. Every CodeRunner question names its prototype in the *Question type* field (for example python3), and at run time CodeRunner finds the prototype question with that name and fills in the fields the question has not set.

**Two kinds of prototype.** Built-in prototypes (19 of them, such as python3, c\_program, java\_class) ship with the plugin and are loaded into the site's system-level question bank. User-defined prototypes, such as python3\_scratchpad from the *ucquestiontypes* collection, are questions that an author has saved with the prototype flag on, in any question bank.

**Inherited and non-inherited fields.** The question's extra fields fall into two groups:

- *Inherited* (15 fields): the template, is-combinator flag, grader, language, Ace language, use-Ace, sandbox, CPU and memory limits, sandbox parameters, result columns, UI plugin, test-splitter regex, multiple-stdins flag and prototype extra. These come from the prototype unless the author ticks *Customise*. When customised, all the inherited fields are copied into the question and can be overridden by the question author, but the connection to the prototype is then essentially broken - subsequent changes to the prototype have no effect on the question, except for a few subtleties that don't concern us here.
- *Non-inherited* (about 25 fields): answer, answer preload, global extra, template parameters and their language and evaluation options, UI parameters, penalty regime, all-or-nothing, precheck and hide-check, answer box size, attachments settings, feedback display and give-up options. These always belong to the question. The prototype has no say in them, so a course cannot, for example, set a course penalty regime once or set the Ace editor to dark mode.

**Merging rules.** Three of the question fields are merged with the template fields regardless of whether the question is customised or not. Template parameters and UI parameters are merged key by key, the question winning. Support files are merged by filename, the question winning.

**Finding the prototype.** Prototypes are searched for by name, searching the system question bank and in every question bank of the question's own course. If it finds none, or more than one with the required name, the question is broken. Only the latest version of the prototype question is used, so editing a prototype instantly changes every question that depends on it, including questions in quizzes already attempted.

**Template parameters.** Template parameters allow a single prototype to serve many different but similar roles. python3\_scratchpad recognises about 75 parameters. Their defaults live in a KNOWN\_PARAMS table inside the template code, and a few defaults are switched by quiz tags. Authors set parameters as raw JSON and are dependent on the question text of the prototype to correctly document the types and semantics of all template parameters.

## 4. Problems with the current design

**For question authors**

- The *Customise* checkbox reveals a large panel of template and sandbox fields at once, which is daunting even for experienced users, let alone newcomers.
- There is no way to see which values came from the prototype and which are the question's own.
- To make a small variant of a prototype (say, scratchpad with Matplotlib functionality enabled) the choices are to copy the whole prototype, or to set the same template parameters by hand in every question.
- Settings that a course would want to fix once, such as the penalty regime, cannot be set by a prototype at all.
- Template parameters are undocumented JSON at the point of use. Typos and type errors are caught only if the template checks for them.

**For prototype authors**

- A prototype is edited in the ordinary question form, which was designed for questions. Its documentation must be written as question text. Some fields, like the answer and the test cases, have no meaning for a prototype.
- The large number of fields available to the prototype author is confusing, even to relatively experienced users. It can be very difficult to understand the implications of some of the fields. It is nearly impossible to infer from the current UI how to write a prototype that uses a combinator template grader, for example.
- Managing prototypes is error prone. Missing prototype or duplicate prototype errors are common/. Authors are often confused by the fact that changing the name of a prototype breaks all the questions that use it. The automatic prototype name changing that occurs when a prototype is duplicated is a similar source of confusion.

**For administrators and course coordinators**

- A question works only if exactly one prototype of its name is visible from its course. Moving or sharing a question bank, copying a course, or importing a second copy of a prototype can silently break hundreds of questions.
- Moodle 4.6 and 5.x moved question banks into activity modules and allowed sharing between courses. Prototype lookup had to change dramatically to accommodate the change and it is still not possible for one course to share prototypes in a different course in the way that questions can be shared.
- Prototypes appear in question lists, reports and bulk operations as if they were questions.

**For maintainers**

- There is lots of special-case code to switch behaviour depending on whether or not a “question” being edited, saved, duplicated or executed is a prototype.
- Changes to Moodle's question bank, versioning or sharing often impact CodeRunner in unexpected ways because some of the "questions" in a bank are really prototypes on which other questions depend.

## 5. Proposed architecture

The essential change is that prototypes stop being stored as Moodle questions. We use the term *prototype manager* for the code that handles the creation, editing and general management of prototypes. The question then is: where does the prototype manager code go?

We considered three options:

1. Within the question type plugin, as at present, but with major refactoring to manage prototypes in a separate subsystem of the plugin.
2. Within a separate *qbank* plugin (an existing but little used Moodle API for question bank management code).
3. Within a separate *local* plugin (an existing Moodle option for any plugin that doesn't fit any of the existing plugin APIs).

Each option had advantages and disadvantages, but Moodle's backup and restore API settles the choice. Question types and qbank plugins get called per question and can add data only to each question's backup. There is no course-level call that can be used to backup more-global data, such as a course's library of prototypes. Only local and admin tool plugins can also attach data to the course (see *Backup and restore* in section 9).

The proposal is therefore a local plugin alongside the question type plugin and the CodeRunner behaviour plugin. Authors would reach the manager via the existing *CodeRunner management* page, which every course already links to for users with the qtype/coderunner:management capability. Its prototype section already holds the prototype usage report. Later, some of the other support scripts on that page (bulk tester, question browser, attempt downloads, analytics, cache purge) might move into the manager plugin, leaving the question type plugin to deal only with questions.

**Storage.** Prototypes will live in the manager's own DB tables, which it will backup itself. The list of inheritable fields will be defined once, in code, as a registry giving each field's type, merge rule and editing-form section. Each prototype version will store its overrides as a single JSON document (a native DB column type), so adding or changing an inheritable field needs no schema change.

For questions, the question\_coderunner\_options table is restructured. Once the old columns are dropped in a later release, it holds just the question id, the id of its prototype, a few instance fields such as the sample answer, and an overrides JSON document. Test cases keep their own table. Although questions and prototypes live in different tables, their overrides are recorded in the same form – a JSON document of overriding values – so one piece of code handles both inheritance within the prototype chain and inheritance by a question from its prototype. There is a significant migration cost, however, since every question's options are converted to JSON, and existing SQL and scripts reading the option columns must query the JSON instead.

The upgrade risk is mitigated by making the migration additive. The JSON is added as a field to the question\_coderunner\_options table, and the old columns are not dropped until a later release. During migration this allows new resolved settings to be checked against old ones before switching over, and also provides redundancy to help with any migration issues or a need to revert settings.

We considered keeping the options table as it is, with a null column meaning inherited. The migration would be smaller, but most columns would be null, the field list would be held both in code and in the table, and questions and prototypes would record inheritance in two different ways.

## 6. The new prototype model

In the new model a prototype is an *uninstantiated question*. It holds the fields that define how a question is displayed, compiled, executed and graded, but none of the fields that make up a particular question instance. Thus a question's fields fall into two categories:

1. Instance fields, which belong to the question itself: question name, question text, general feedback, default mark, test cases, answer preload and sample answer.
2. Inheritable fields, which come from the prototype chain unless the question overrides them: template, template parameters, grader, sandbox, UI, UI parameters, global and prototype extra, which test case fields are used, etc.

Prototypes have an optional parent and multistep inheritance is allowed. The value of a particular question's prototype component is dynamically determined when the question is loaded by traversing the inheritance chain from the root to the question, with each layer being able to override any subset of the fields with its own values.

```text
Prototype chain (each layer sets only what it changes; the question gets the sum)

python3              built-in, site     template, grader, language, sandbox, time and memory limits
  ↓
python3_scratchpad   site               combinator template and grader, scratchpad UI, ~75 parameter defaults
  ↓
cosc131_scratchpad   course (example)   penalty regime, 2 s CPU limit, one parameter: pylint off
  ↓
Your question        question bank      question text, test cases, answer, one parameter override

Effective settings → comes from
  Template               python3_scratchpad
  Language, sandbox      python3
  Grader                 python3_scratchpad
  UI plugin              python3_scratchpad
  Penalty regime         cosc131_scratchpad
  CPU time limit         cosc131_scratchpad
  Template parameters    merged, key by key
  Question text          question
  Test cases             question
  Every other field      the nearest layer that set it
```

Read from the top: each prototype refers to its parent and overrides a few fields. The question defines the fields for its particular instance and can optionally override any of the prototype fields. The effective settings are what CodeRunner actually runs, with the layer each value came from. Today python3\_scratchpad is a standalone prototype; it is drawn here as a child of python3, which it could become.

**What a prototype holds**

- A name that no other prototype in its course or at site level has, and an optional parent prototype, which it refers to by id.
- A stable identifier (a random strings such as a UUID), created with the prototype and kept through export, backup and restore, so that copies of the same prototype in other courses or on other sites can be recognised.
- A scope: the course it belongs to, which for a site-wide prototype is the site (front page) course. Scope decides who can edit it, who is offered it and which backups carry it (see *Scope, sharing and versions*).
- Values for any subset of the inheritable fields (never instance fields such as question text or test cases). A field it leaves unset passes straight through from its parent.
- A one-sentence summary, shown in the prototype picker, and full documentation for authors, shown in the question form once the prototype is chosen. Together they replace the current method of using the prototype “question text” as documentation.
- Optionally, definitions of its template parameters (below).
- Support files.

**Referring to a prototype.** Each question stores the id of its prototype, and each child prototype the id of its parent, so running a question never involves looking anything up by name. A prototype's name is the label authors see, and what imports use to match prototypes from elsewhere; renaming a prototype changes nothing else. Because questions refer to their prototype directly, the manager can always tell which questions depend on a prototype, and refuses to delete one that is still in use.

**Extras.** *Global extra* and *prototype extra* are both general-purpose fields that a template can read. Global extra was initially added to CodeRunner for use as test data shared by all of a question's test; it was not inheritable. *Prototype extra* was added later to give more flexibility and allow inheritance from the prototype. In the new design both are inheritable, so they differ only by convention: prototype extra is normally set by a prototype and global extra by a question. Both keep their names, since existing templates use both. Each test case's own *extra* field remains instance data.

**Test case fields.** A prototype can say which test case fields its questions use: test code, standard input, expected output and extra template data. The question form shows only those, so a write-a-function prototype can hide standard input, a write-a-program prototype can hide the test code, and a prototype whose combinator grader computes its own results can hide the expected output. A question can still reveal a hidden field, and a field that already holds data is always shown. Like any other setting, these are inherited and can be overridden.

**Inherit or override.** Each field at each layer is either inherited or overridden. In storage, an absent value means inherited and any present value, including an empty string, is an override. Today a blank inherited field is turned into null, so an author cannot deliberately set a prototype's value to empty; under the new rules they can.

**How values combine (starting from the root of the prototype chain).**

| Field | Rule when a layer sets it |
| --- | --- |
| Template, grader, sandbox, language, limits, penalty regime and most others | Replaces the inherited value |
| Template parameters | Merged key by key, starting at the root, the later layer winning (today's rule, applied at every layer) |
| UI parameters | Merged key by key, the later layer winning |
| Support files | Merged by filename, the later layer winning |

**Instance, inheritable and prototype fields.** A question holds its instance fields and, optionally, overrides of any inheritable field. A prototype holds values for inheritable fields, plus a few fields that describe the prototype itself. Inherited values are resolved when a question is instantiated (i.e. when a student starts a quiz attempt that uses it), so a change to a prototype reaches all its questions when they are next instantiated. Once a question has been instantiated changes to the prototype have no effect unless a regrade is initiated. This is consistent with how Moodle treats question edits when a quiz question slot is set to *Always latest*, but if a slot is locked to a particular question version, its prototype is not locked with it.

Instance fields belong to questions alone. Today's prototypes carry test case and answer fields that are simply ignored; in the new design prototypes do not have them.

**Testing a prototype.** A prototype is tested the same way as at present: by running questions that use it. For a new prototype the author writes a question or two that use it and checks them in the question editor, as today. For a change to an existing prototype, the bulk tester runs over the questions that depend on it, directly or through children. We could consider allowing prototypes to have references to designated test questions, but they would remain ordinary questions in a question bank.

**One editor, two frames.** Because the inheritable fields are most of what both the prototype editor and the question editor show, their editor is defined once, as a component in the prototype manager. This component is embedded in both the editors. The question editor adds the instance fields; the prototype editor adds parent, scope, documentation, parameter definitions and change history.

A prototype's documentation takes over the role its question text plays today: help for question authors, shown in the question form.

**Help and language.** Text in the forms comes from three places. Labels and help for CodeRunner’s own fields remain Moodle language strings, shown with the usual help icon in both the question form and the prototype editor, as today. The summary, documentation and parameter descriptions of the built-in prototypes that ship with CodeRunner are also language strings, so they can be translated through AMOS like the rest of the plugin; copying a built-in turns them into ordinary text in the copy. Prototypes written by users have their summary, documentation and parameter descriptions written by the author, in their own language, just like question text.

**Interaction with Moodle's question versioning.** Moodle's question versioning system knows nothing of prototypes. Instance fields stay under Moodle's versioning; inherited fields (template, grader, sandbox, parameters and so on) do not.

With Moodle's question versioning, every edit results in a new question version. Although Moodle reloads a question definition on each request, the definition is that of the question version that was extant when the student started the attempt. Thus fixes to question wording do not propagate into live quizzes unless a regrade is performed.

Changes to a prototype behave in the same way in the proposed new system. When an attempt starts, CodeRunner records the version of each prototype in the question's chain; every later page load, Check or review of that attempt uses those versions. A prototype change therefore reaches new attempts immediately, but does not reach attempts already in progress, or finished ones, unless they are regraded. During a regrade CodeRunner refreshes the recorded versions to the current ones. It can recognise a regrade because Moodle rebuilds the attempt's first step as a writable step that keeps its existing database id, whereas ordinary page loads use read-only steps. That is an internal Moodle convention rather than a documented API, so a unit test must guard it.

**Parameter definitions (not in the ****i****nit****ial** **release**** ****- adde****d later****).** The new system will potentially offer a more type-safe approach to template parameters than just raw JSON, although the JSON is still there under the hood. A prototype may declare its template parameters: name, type (yes/no, number, text, choice, JSON), default, a one-line description and a display group. A child prototype can change a default, hide a parameter or add new ones. By default (for new question) the question form shows only the parameters this question overrides, as labelled controls instead of a JSON box. *Show all parameters* expands the full list, grouped, with inherited defaults greyed and a filter box. Unknown names are flagged on save.

For existing questions and prototypes, raw JSON remains available. Parameters evaluated by Twig or Python at run time, i.e. those with a Preprocessor language set, still work as at present but are shown as code, not as controls.

In existing prototypes, the meanings, types and defaults for template parameters are not formally specified, so cannot be extracted and converted into the new labelled controls during an upgrade. The above approach to parameter definitions is therefore optional and affects only the displayed question authoring form: with or without them, the controls' values become the same merged JSON at run time. Without definitions, parameters are edited as raw JSON, as now.

A prototype author who wants labelled parameter controls rather than a JSON box, will need to write the definitions by hand. When labelled parameters are being used, the generated JSON will include all merged parameters including their defaults. The prototype author will need to ensure that the default values in the parameter definitions match any defaults currently within the template code or remove the defaults from the code altogether.

**What multilevel inheritance offers.** Subtypes could give question authors a friendlier interface to a general-purpose prototype. The parent might still use template parameters so that one template could serve many purposes; a subtype might then set the parameters that suit one kind of question, so authors choose a type instead of having to explicitly set parameters. For example:

- Write-a-function and write-a-program subtypes of python3\_scratchpad. python3\_scratchpad\_function would show the test code and hide standard input; python3\_scratchpad\_program would do the reverse and set *isfunction* to false, so that authors no longer have to remember to do so. The two files variants would then derive from these.
- A matplotlib subtype of python3\_scratchpad that changes the defaults of all the plotting-related parameters.
- Graph questions, with directed graphs, undirected graphs and state machines as subtypes of one graph prototype, each presetting the graph UI parameters. Today directed\_graph and undirected\_graph are separate built-in prototypes.
- Variants that need different support files rather than different settings, which today can only be made by copying. For example, python3\_files\_function and python3\_files\_program were created as copies of the python3\_scratchpad prototype with their own \_\_resulttable.py and \_prefix.py support files. But this has created maintenance issues: bug fixes or extensions to the original prototype have sometimes not been propagated into the file question types. As subtypes they would override only the two support files that distinguish them from the python3\_scratchpad question type and bug fixes to the scratchpad question type would be automatically inherited.

A further possibility might be to use Twig's own inheritance mechanism ({% extends parent %}), which would give more flexibility in inheritance of templates, but this is not something to be included in the initial redesign.

**Limits.** Prototype inheritance chains have no tight depth limit, since nothing technical requires one: resolved settings are cached, so depth costs nothing at run time. Instead the prototype editor warns when a new prototype would make a chain deeper than about four levels, a threshold that could be a site-wide setting, and a generous hard limit, say 20, guards only against runaway data. Attempts to introduce inheritance cycles would be disallowed. Every screen that shows a resolved value also shows which layer it came from.

**Site defaults.** At the root of every chain sits a single *Site defaults* prototype. It holds values that today are scattered across code and admin settings, such as the default sandbox, penalty regime and answer box size. It can be edited only by site administrators and cannot be deleted. It is shown in a chain only when it is the source of a value. Built-in prototypes such as python3 derive from it.

## 7. What question authors will see

For most authors the job stays the same: pick a prototype, write the question text, write the tests. What changes is that every other setting is potentially visible and editable with all inherited values greyed, and labelled with where they came from.

**Creating a question.** The *Question type* menu becomes a prototype picker. By default it lists, in three groups, the prototypes **used in this course** already, then the rest of the question's own course and the site prototypes, then any prototypes from other courses that questions in this course already use, each labelled with its course. *Used in this course* comes first because it is the group that stays small however large the library grows, and the library is expected to grow: much of what template parameters do today should become separate prototypes, so that writing a function and writing a program are two question types rather than one type with an `isfunction` parameter. A Python course never touches the C or Java prototypes, and should not have to read past them. A final *From another course…* entry opens a course selector, listing only the courses where the author can manage CodeRunner questions, and then that course's prototypes. Keeping other courses behind that step keeps the list short for authors with rights in many courses, avoids showing different prototypes of the same name side by side, and makes depending on another course's prototype a deliberate choice (see *Prototypes from other courses*). A question that already uses another course's prototype shows it as the current choice, labelled with its course, for example *cosc122\_scratchpad (COSC122-26S1)*. Within each group the prototypes are listed in tree order and **indented by depth**, so a child sits under its parent and the shape of the library is visible without a bespoke control: a plain Moodle `autocomplete` element carries it, which brings a filter box and the accessibility behaviour with it and degrades to an ordinary select. The chain of the *selected* prototype is shown below the picker as a breadcrumb — *python3 › python3\_scratchpad › cosc131\_scratchpad* — with its one-sentence summary, rather than repeating every ancestor on every row. An earlier draft put the breadcrumb on each row, and a suggested alternative drew a full tree inside the dropdown; the first repeats ancestors once per descendant, and the second needs a custom combobox, which is the largest accessibility risk in the project for a view whose only job is to pick one item. The full tree belongs in the prototype library (see *What prototype authors will see*), where browsing the structure is the actual task and there is room for it.

How well indentation and *Used in this course* hold up as the library grows is better judged on a site with real child prototypes on it than in advance, and is worth revisiting then. The author then writes the question text, test cases and sample answer, as now. The full documentation for the prototype is available, collapsed by default, in the question form once a prototype has been chosen. The parent picker in the prototype editor works the same way.

**The form.** The question text, test cases, sample answer and answer preload are open at the top. Everything else sits in collapsible sections, closed by default:

1. Template parameters (open when the question overrides any)
2. Test case fields
3. Grading and penalties
4. User interface, including the answer box size
5. Template and grader
6. Sandbox, languages and limits
7. Extras (global extra and prototype extra)
8. Support files

Inside a section, an inherited field is shown greyed with a small label such as *from python3\_scratchpad*. Clicking it makes it an override: the field turns normal, is marked as changed, and gains a *Revert to inherited* button. A section header shows how many overrides it holds (for example *Template parameters · 3 changed*), so a closed section never hides a change.

**Template parameters** (in versions after 6.0) show only the ones this question overrides, as labelled controls with their descriptions, when the prototype defines them. *Show all parameters* expands the full list with inherited defaults greyed. The same rule applies to every section: overridden fields are shown, inherited ones are one click away. A *Show as JSON* switch gives today's text box for those who prefer it.

**Changing a question's prototype** keeps its overrides. The form highlights any override that now duplicates the new prototype's value or refers to a parameter the new prototype does not define.

**Promoting overrides to a prototype.** When an author has customised a question and wants to reuse it, *Make prototype from this question* creates a child prototype holding exactly those overrides and can re-point the question at it. This replaces today's practice of turning a question into a prototype by flipping a flag.

**What disappears.** The *Customise* checkbox, prototype questions in question lists and quizzes, and the "prototype missing" and "multiple prototypes" errors: questions refer to their prototype directly, and the manager refuses to delete a prototype still in use.

**Students** see no change. Questions render, run and grade exactly as before.

## 8. What prototype authors will see

Prototype authors get a workspace of their own instead of the question form.

- **Library view.** The current course's prototypes and the site prototypes, plus any from other courses that this course's questions use, drawn as a tree from the built-ins down, with each one's course, owner, version and number of dependent questions and child prototypes.
- **Editor.** The same field sections as the question form, with the same inherited and overridden states, plus tabs for documentation, template parameter definitions, support files and change history, and a usage summary: how many child prototypes, questions, courses and quizzes depend on the prototype, with a link to the usage report. A *Resolved* view lists every field's effective value and the layer it came from.
- **Usage report.** A separate page, extending today's *Prototype usage* script. It shows the tree of child prototypes and the questions that depend on the prototype, directly or through children, grouped by course and bank, with which fields each one overrides, and with counts, filtering, paging and download. It can also list quizzes with attempts that started before a given version, as candidates for a regrade, and run the bulk tester on any selection. Users see details only for courses they can access, and counts for the rest. Dependents are found by query when the page is opened; nothing is stored.
- **Make a child prototype.** One action creates an empty child of any prototype the author can use. The author then overrides only what differs. Building cosc131\_scratchpad on top of python3\_scratchpad means setting a handful of parameters, not copying a 185 KB prototype.
- **Test before publishing.** Edits are saved as a draft. Before publishing, the author can run the bulk tester over every question that depends on it, directly or through children, and over any designated test questions. The dependents count is shown beside the publish button.

Combinator template graders, per-test templates, custom graders and sandbox parameters all remain, but question authors meet them only when they open the advanced sections of a question.

**Permissions.** To start with, the existing qtype/coderunner:management capability would suffice to control who can view, create and edit course prototypes. Using a prototype from another course, for a question or as the parent of a child prototype, needs the same capability in that course. Site-level prototypes stay with site administrators. A finer-grained set of capabilities, for example separating viewing, creating and editing prototypes shared across courses, is a possible future change.

## 9. Scope, sharing and versions

**Scope.** Each prototype has a scope: the course it belongs to. A site-wide prototype belongs to the site (front page) course and is called a site prototype. All prototypes are stored in the prototype manager's own table; scope only records which course is responsible for each. Scope decides who can edit it (see *Permissions*), which authors are offered it in the prototype picker, and which backups carry it. It plays no part in running a question, because each question refers to its prototype by id.

In the current version of CodeRunner under Moodle 5, a question can use any prototype in any question bank of its course or in the front page's default shared bank. To keep that availability, a prototype belongs to a course rather than to one of its question banks, so an author never has to choose a bank for it, and the migration gives every existing prototype the scope of the course whose bank contains it.

**Names.** A name must be unique among a course's prototypes and the site prototypes taken together, so a course prototype can never shadow a site prototype, and an author never sees two prototypes of the same name for their own course. A new site prototype therefore needs a name that no course already uses. Different courses may use the same name, since the picker lists another course's prototypes under that course's name. Whenever a prototype would arrive with a name already taken (through restore, import, a new built-in in a CodeRunner release, or a move to site scope when its course is deleted), it is given a new name, such as cosc131\_scratchpad (2), and the user responsible, or for site prototypes the administrators, are notified. The migration does the same for any course prototype whose name matches a site prototype. Today such a pair already causes duplicate-prototype errors, so the health report lists them.

**Shared question banks.** A question refers to its prototype by id wherever it is used. So when a quiz in course A uses questions from a shared bank in course B, those questions use exactly the prototypes they use in B, and nothing needs to move.

**Prototypes from other courses.** An author can use a prototype from another course, for a question or as the parent of a child prototype, provided they can manage CodeRunner questions in that course. The same happens when a question is moved to a bank in another course: it keeps using the prototype it was written for. Such questions appear in the prototype's usage report like any other dependents, so its authors can see which courses an edit would affect, and the draft, bulk-test and publish steps cover them too. A prototype that other courses still use is never deleted with its course: when the course is deleted, the prototype is moved to site scope and site administrators are notified.

**Versions: a change history, with attempts pinned.** Prototypes do not use Moodle's question versioning. Instead they keep a git-style change history, and each attempt records its prototype versions (i.e. the versions of the prototypes in its prototype chain) current when it started (see *Interaction with Moodle's question versioning*). The history works like this:

- Every save is recorded automatically as a new version, with author and time. Each version is stored complete, as a separate database record; the field-by-field differences between versions are computed for display, not stored. The author of the change can optionally add a commit message.
- New question attempts use the latest version of each prototype in the prototype chain. Existing attempts keep the versions they started with until they are regraded.
- Any earlier prototype state can be viewed, compared with the current one, or restored. A restore is itself recorded as a new change, so nothing is lost.
- Because each attempt records its prototype versions, reviewing an old attempt shows the prototype as it stood when that attempt ran.

**Pushing a fix to attempts in progress.** When publishing a new version, the author can opt to apply it to attempts still in progress, for example to fix a template bug during a live test. The publish page shows how many attempts in which quizzes would be affected and lets the author choose the quizzes. CodeRunner then updates the prototype versions recorded in those attempts, so each student's next page load, Check or submission uses the fixed version; nothing is rerun, so it costs no sandbox load. Marks already earned are not changed: Checks made before the push keep the results and penalties they got, so one attempt may contain results graded under two versions. That is why this is opt-in, and why finished attempts are never changed this way: for them, a regrade remains the only route. Each push is recorded in the prototype's history.

**Storage and performance at scale.** Pinning an attempt to its prototype versions stores a reference, not a copy. Take a class of 1400 students, each sitting 20 quizzes of 30 CodeRunner questions: 840,000 question attempts. The costs are:

- *Per attempt:* one short step variable in the first step, holding the id and version number of each prototype in the chain (such as 412@7, 37@41). It sits alongside the \_mtrandseed, \_STUDENT, \_QUIZ and template-parameter values that CodeRunner already stores per attempt, so per-attempt storage barely changes.
- *Per prototype version:* one record in the manager's change history, stored once however many attempts use it. Most of it is the template, around 20 KB for python3\_scratchpad, so even hundreds of saves a year come to just a few megabytes per site.
- *Support files:* stored once per version through Moodle's file API, which de-duplicates by content hash. A version that leaves a file unchanged adds only a file record, not another copy of the file; a changed file is stored once, not per attempt.
- *Time:* each page load reads the attempt's version ids and fetches the resolved settings from a Moodle application cache keyed by those ids, so all attempts on the same versions share one entry. A cache miss resolves the chain once, reading a handful of rows. Today get\_prototype() already runs a database query on every page load, so this should be no slower.

The one cost that grows over time is the change history itself, and it grows with the number of prototype edits, not with the number of students or attempts. It is assumed that courses will have relatively few prototypes – say at most 20 or so – so even if prototypes are edited many times the total storage cost is still only of the order of tens of megabytes. All the same, to restrict growth over time there will be a *Prune prototype history* script on the CodeRunner management page, like the existing script for removing old question versions, that prunes prototype change history back to the last N versions plus any that are still in use.

**Export and import.** Moodle XML export of a question writes its own overrides and its prototype's name and stable identifier, since database ids mean nothing on another site. Three export choices would be offered:

- *Reference only* (the default): the receiving site must have the prototype. On import, the question is linked to an available prototype with the same identifier if there is one, otherwise to the prototype with that name in the question's course or at site level (names are unique across the two, so there is at most one). If there is none, the import reports the missing prototype as an error. With Moodle's default Stop on error setting, the import then stops before anything is saved, so no broken questions are created. The author can import the prototype first, or ask for an export with include prototypes.
- *Include prototypes*: the export carries the prototype chain. On import, an available prototype with the same identifier and identical content is used; otherwise the incoming prototype is added, under a new name if its name is taken, with a warning.
- *Flatten*: every field resolved into the question itself, at the cost of losing the link to the prototype. **This does not by itself make a question run on CodeRunner 5**, which was claimed here and is wrong: `jobrunner.php` refuses any question with no prototype object before it looks at a single field, so a fully flattened question still needs *a* prototype question of its name to exist on the receiving site. `set_inherited_fields()` is tolerant of a missing prototype, so such a question authors and displays; it is running that fails. Carrying a question to a CodeRunner 5 site therefore needs a prototype question as well — see *CodeRunner 5 compatible export* below.

**CodeRunner 5 compatible export.** A site may stay on CodeRunner 5 for a while and still want a question type being developed here — RoboTurtle is the case that raised it. The transformation belongs on the CodeRunner 6 side, as one more export flavour, rather than in a stand-alone script shipped to CodeRunner 5 sites. Four reasons, in order of weight. The recipient needs **no new code at all**: an ordinary Moodle question import, where a script would have to be shipped to, trusted by and run on each receiving site — and a site still on 5 is the one least likely to run an ad-hoc script. The knowledge exists only here: resolving a chain, telling instance fields from inherited ones, reading an overrides document are all CodeRunner 6 notions, and reimplementing them outside is the second resolution path decision 1 forbids. The code is already written — `prototype_chain::resolve()` and `legacy_converter::question_columns()` are this transformation, and `export_to_xml()` already resolves by name and nulls inherited fields, so what is missing is only emitting the prototype as a prototype question. And it is deletable in one piece in phase 4, where a separate script would be a second artifact with its own versioning against several CodeRunner 5 releases.

The flavour writes the question's chain resolved into a **single prototype question** (`prototypetype` 2, `coderunnertype` the frozen name, every inherited field set), plus the selected questions referencing it by name, with `prototypeid` and `overrides` omitted. That hands the recipient the question *type* and not merely runnable questions, which is the point. **Prototype-only export comes free from the same emitter**, with no questions attached: a single prototype question, which is how ucquestiontypes is distributed today. Two properties to state plainly: it is **one-way**, since CodeRunner 5 XML has nowhere to carry the uuid or the chain, so such a file re-imported here arrives as a legacy prototype question and is converted as one; and flattening loses the chain, so the recipient's copy diverges as soon as a parent is edited. Belongs in M5.

Prototype libraries would be distributed as prototype export files rather than as prototype questions, and imported through the prototype manager. Importing a newer release of a library updates each prototype with a matching identifier by adding the incoming content as a new version, after showing the author what will change.

**Backup and restore.** Today, course prototypes are included in course backups automatically because they are questions. In the new version the prototype manager must implement Moodle's backup and restore API itself. A local\_ plugin cannot add its own checkbox to the backup form; instead its data is attached automatically to parts of the backup. The manager uses two of these attachment points:

- *Course:* all of the course's own prototypes, whether or not any question in the backup uses them, together with any ancestors they have outside the course, apart from built-ins.
- *Question:* any other prototype that a question in the backup needs, apart from the built-in ones that every site has. This covers user-written prototypes at site scope, such as a site-wide copy of python3\_scratchpad, which the receiving site may not have, and prototypes belonging to other courses, needed either by questions a quiz borrows from another course's shared bank or by questions that use another course's prototype directly. Each such prototype is written into the backup once, however many questions need it.

The question attachment point is needed because Moodle backs up the course before its activities, so at that stage it cannot know which questions the backup will contain. The questions are written last, once that is known.

**Questions from another course's shared bank.** When course A's quizzes use questions from a shared bank in course B, a backup of A contains copies of just those questions, not B's bank. Restored on another site, Moodle puts the copies into a new default bank in the restored course A and points A's quizzes at them, even if B has been restored there too, so the sharing is lost. (On the same site, if the user can view B's bank, Moodle points the quizzes back at the originals.) This is existing Moodle behaviour that CodeRunner cannot change, but the question attachment point ensures that the copies carry the prototypes they need from B and so keep working.

**Restoring prototypes.** Restored questions and child prototypes record their prototype by id, so the restore maps each prototype in the backup to one on the receiving site:

- If a prototype with the same stable identifier is already available where the restored questions will live, for example the original when a course is restored on its own site, the restored questions and child prototypes are pointed at it and the incoming copy is discarded. Any incoming versions it lacks are added to its history, so restored attempts still find theirs.
- Otherwise the prototype is added to the prototypes table. A prototype belonging to the course being restored has its course id mapped to the new one in the normal Moodle way. Any other prototype is given the scope of the restored course: one belonging to another course has no mapping, and one with site scope is not restored as a site prototype, because restoring a course should not create prototypes for the whole site. If its name is already taken in that course or at site level, it is given a new name, such as cosc131\_scratchpad (2), and the user who ran the restore is notified. Because the restored questions refer to it by id, they still get exactly the prototype they were written for.

Restoring a backup made before the upgrade does not create duplicate questions. Moodle recognises questions that already exist by comparing a fingerprint of their stored data, so CodeRunner converts legacy backups to the new storage form before that comparison. Also, when restoring legacy backups, prototype questions will be passed to the prototype manager to be added to its own database, rather than being inserted into the question bank as at present.

**Linking after restore.** In a legacy backup, questions and prototype questions arrive in any order. In a new backup the manager writes parents before children within each attachment point, but a question can still be restored before the prototypes it uses, and a child at the course attachment point can have its parent (another course's prototype, say) at the question attachment point. So during the restore CodeRunner only records each restored question's and child prototype's reference: the old prototype id for a new backup, the prototype name for a legacy one. It links them all in Moodle's after-restore stage, once everything has been loaded. New backups use the id mapping above. Legacy backups use today's name rules, applied to the prototypes now in the course, including those just restored, and the site prototypes. The prototype reference is excluded from the fingerprint Moodle uses to recognise existing questions, so this late linking does not affect duplicate detection.

**Student attempts.** Because attempts record the prototype versions they started with (see *Versions*), a backup carries each included prototype's whole version history. Since each version is a complete record, no diffs need replaying, and the history view survives a restore. When the backup includes user data, the restore rewrites the prototype ids recorded in each attempt to those of the prototypes they were mapped to; version numbers are kept.

**Quiz duplication.** Moodle also uses backup and restore to duplicate quizzes. The copy lands in the same course, and its questions keep the ids of the prototypes they use, so nothing changes.

## 10. Migration: every existing question keeps its behaviour

The migration converts both the prototypes and each question’s stored options to the new form, but at run time every question must end up with exactly the same settings as now: template, template parameters, UI parameters and so on. Any difference would be a bug in the migration. The migration will be tested on development sites and then on our own production sites, and released first as an alpha or beta on GitHub for other sites to try, before release through the Moodle plugins directory. Whether the release should also guard against bugs that show up only on sites with unusual data is to be determined. One option is a Moodle environment check that compares every question’s old and new settings before the upgrade and blocks it if any differ (in which case the administrator would need to reinstall the previous 5.x version of CodeRunner to continue the upgrade).

**Questions.** Each question's prototype is found once, by today's name rules, and its id is stored with the question. Its existing option columns are converted mechanically into its overrides JSON. Every non-null inherited column becomes a key; a null one is left out, so that field inherits as it does today. Instance fields such as the sample answer and answer preload simply stay with the question. The other non-inherited columns, such as penalty regime, all-or-nothing grading, answer box size and global extra, hold fields that become inheritable, so each becomes a key whether null or not, with a null stored as an explicit value that matches today's behaviour, usually an empty string. These fields have never been inherited, so storing each question’s own value keeps the question exactly as it is now, unaffected by its prototype or by the Site defaults. Template parameters keep their evaluation language and options at each layer, so Twig- or Python-generated parameters evaluate as before. The old columns are kept until a later release.

**Built-in prototypes** become site-level, read-only prototypes in the manager. As now, each CodeRunner release updates them. Defaults now held in code or in CodeRunner's admin settings move into the Site defaults prototype.

**User-defined prototypes** become root prototypes (no parent) holding all their own fields, at site scope if they live in the front page's question bank, and otherwise at the scope of the course whose bank contains them (see Scope). Their question text becomes their documentation and their support files move with them. Their test case and answer fields are not carried over; they are normally empty anyway, since a prototype can never be run as a question. A prototype question's own global extra has never had any effect, so it is not carried over; any that are non-empty are listed in the prototype health report (see *Suggested phase 1*), so their authors can move them deliberately. The new test case field switches that determine which test case fields a question displays are all *on* in the Site defaults prototype, and migrated prototypes leave them unset, so the question form shows every test case field, as it does today.

**The old prototype questions** are left in place as ordinary questions, which keeps the migration non-destructive. Nothing resolves to them any more. The migration points each one at the prototype it became, so it simply becomes an unused question of that prototype. Because CodeRunner owns its edit form, opening one shows a banner saying the prototype now lives in the manager, with a link to it, and that edits here affect no other question. Moodle has no read-only questions, so the banner is the safeguard. A clean-up script on the *CodeRunner management* page can delete these legacy questions in a later release, once an administrator is satisfied.

**Questions that are already broken** (no prototype, or more than one) are stored without a prototype and stay broken; the prototype health report lists them with the reason.

**Legacy imports.** Moodle XML files containing prototype questions, including the existing ucquestiontypes exports and old course archives, will always import. Each prototype question is created as a prototype in the manager, at the scope of the course whose bank the file is imported into, or at site scope for the front page's bank, as in the migration, and the other questions in the file are linked to it. An identical prototype already available is used instead of creating a copy; a different one with the same name is created under a new name, as for restore. Questions that name a prototype not in the file are linked by name, as for reference-only imports.

**Optional tidying tools in the prototype manager** (never run automatically)**.** Many user prototypes are copies of a built-in with a few changes. A *rebase* tool could offer to turn such a prototype into a child of the built-in holding only the differences. Its behaviour is unchanged at the moment of rebasing, but it will follow later changes to the built-in, so this is the author's decision. Likewise a *flatten* tool could remove question overrides that merely repeat the prototype's value.

**Suggested phases**

1. Current 5.x line: add a prototype health report, so sites can find missing, duplicate and ambiguous prototypes before upgrading.
2. Version 6.0: the prototype manager, the migration with its before-and-after check, the new question form, legacy import.
3. Version 6.x: parameter definitions (labelled parameter controls in the question form; until then template parameters are edited as JSON, as now), rebase and flatten tools; possibly moving the CodeRunner management scripts into the manager plugin.
4. Version 7.0: remove the legacy prototype questions and the code that supports prototypes as questions.

The migration is additive: it drops no columns and deletes no questions until phase 4. If a fault in the migration is found after a site has been running 6.0 for a while, a corrected migration can be re-run from the retained data, without restoring a pre-upgrade backup and losing the work done since.

## 11. Implications by user group

| Who | What gets better | What gets worse or must be learned |
| --- | --- | --- |
| Students | Nothing visible; questions behave as before | Nothing, provided migration is clean |
| New question authors | One form with no *Customise* step; inherited values visible and labelled; parameters as documented controls | A new prototype picker; the idea that a prototype can have a parent |
| Experienced question authors | Can change any field without copying a prototype; can set course-wide defaults such as penalty regime once | A very different UI, e.g. familiar fields get moved into collapsed sections. |
| Prototype authors | A real editor, child prototypes, usage report, draft and test before publish, parameter definitions | Prototypes leave the question bank; new permissions; deep chains need discipline |
| Course coordinators | Course-level house style in one place; no breakage when questions or banks are moved or shared; can use another course's prototypes | Must decide which prototypes live at course or site level |
| Moodle administrators | No more missing or duplicate prototypes after restores, moves and bank sharing; a health report | A larger upgrade with a migration step to review; a third plugin to install (after the question type and behaviour). |
| Sites sharing questions with other sites | *Include prototypes* and *Flatten* exports make questions portable | Questions exported by reference still need the prototype on the receiving site |
| Developers of related code | A single API that returns a question's resolved settings | Scripts that read CodeRunner's database tables directly must change |

## 12. Risks

- **Rebuilding what question banks give for free.** Versioning, permissions, backup and restore, course copy, import and export all come with questions today. Each must be rebuilt for prototypes, and a gap in backup and restore would break restored courses.
- **Wider blast radius.** An edit to a base prototype reaches every descendant. Drafts, dependents counts and bulk testing before publish are the mitigation, with the change history for backing a change out, and they must exist in the first release, not later.
- **Cross-course dependencies.** A prototype used by other courses can still be changed by its own course's authors. The usage report, drafts and bulk testing before publishing show and limit the effect, but a course that relies on another course's prototype depends on that course's care. Making a copy is always possible for a course that wants independence.
- **Multiple layers can be confusing.** For example, one layer may override template parameters set by its ancestors. The depth warning and the label on each field showing where its value came from should mitigate this.
- **A long transition.** Sites upgrade Moodle and CodeRunner slowly, so legacy import and the old storage must be supported for several years.
- **Cost.** This is a large change to a mature plugin, and most of the user benefit lands in the form and the prototype editor.

## 13. Open questions

1. Attempts are pinned to the prototype versions current when they started, and pick up later changes only through a regrade. Is that the right default, given the opt-in push to attempts in progress (see Versions)?
2. Which sections of the question form should be open by default for a new author?
3. The first release would let anyone with the existing qtype/coderunner:management capability in a course create and edit that course's prototypes (see Permissions). Is that enough, or do some sites need separate capabilities from the start, for example to let teachers use prototypes while only coordinators create or edit them? Should using another course's prototypes need a capability of its own?

## Sources

- [CodeRunner source, release 5.10.8](https://github.com/trampgeek/moodle-qtype_coderunner): questiontype.php (field lists, inheritance and prototype lookup), question.php (parameter and file merging), db/builtin\_PROTOTYPES.xml (built-in prototypes).
- ucquestiontypes, python3\_scratchpad/template.py (the KNOWN\_PARAMS table) and its prototype export.
