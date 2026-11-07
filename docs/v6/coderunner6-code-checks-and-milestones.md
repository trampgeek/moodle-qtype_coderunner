# Code checks and milestones

Richard Lobb · 2 November 2026

A companion to `coderunner6-design.md` and `coderunner6-implementation-notes.md`. It does three things:

1. Resolves every item the implementation notes marked *to check*, against the Moodle core code in a 5.2.1 checkout, and records what the code actually does.
2. Lists corrections to the design doc and the notes that came out of that reading.
3. Proposes a milestone sequence for 6.0, says where each piece should live, and records the decisions taken so far (section 6).

## Status, 5 November 2026

Built and verified on the development site, all uncommitted on the `Version6` branch of the question type plus the new `public/local/coderunner_manager/`:

- **M0** prototype health report — 6 checks, 9 tests.
- **M1** field registry and resolver — 37 tests.
- **M2** prototype storage, Site defaults, built-in installation, user-prototype snapshot, support files, read-only list page — 72 prototypes and 251 file records snapshotted from the live site.
- **M3** question schema and migration — 12,346 questions converted in 3.3 seconds, and **all 12,346 verified to resolve exactly as CodeRunner 5 resolves them**. 405 question versions left broken for want of a resolvable prototype, which matches the health report's own count.
- **M4** the run-time switch — **complete**, four increments.
- **M4a** the upgrade end to end — **complete**. A pre-6.0 clone of the development database upgrades, migrates on cron, and ends in a state identical to the live site, question for question.
- **M6** the prototype editor — **complete** but unexercised by an author. Everything the milestone lists is built and tested except the pre-publish bulk test, which is deferred with its reason. The UI has had one round of review and will need more. The two new columns are carried through save, import, export, backup and restore (decisions 17 and 18); and the 39 settings are no longer properties of `qtype_coderunner_question`, being reached through `__get`, `__set` and `__isset` with a generated `@property-read` block and a test that it matches the registry. And `get_question_options()` now resolves a question's inherited fields from `prototypeid` and `overrides` through the manager's chain, with `set_inherited_fields()` off the run-time path entirely. What remains is the collected fields.
- **M1a has dissolved into M3.** Its "faithful reimplementation of `set_inherited_fields()`" is unnecessary while the CodeRunner 5 code is still present: the verifier calls `get_prototype()` and `set_inherited_fields()` directly, which cannot drift from the thing it is checking. What remained of M1a — the question converter and the verification script — is in M3.
- **The collected fields are now merged along the chain, which completes M4's four increments.** `get_question_options()` records each layer's template parameters with their language, each layer's UI parameters with the UI plugin in force at that point, and the file areas the support files merge from. The attempt path reads all three off the options object, so it touches no prototype table — decision 16, enforced by a test that counts database reads rather than by review.

**The qt_var scheme is one combined signature, not one pair per layer** (decision 20). `question_attempt_step_data.name` is `char(32)` and CodeRunner 5's `_prototype__template_params_json` is *exactly* 32 characters, so a per-layer suffix will not fit at all; per-layer keys would have to be keyed by version id and would still be tight. The signature covers each layer's raw parameters and language and the chain's layer labels, so inserting a prototype that contributes no parameters of its own still counts as a change. In-flight attempts upgrade themselves: their stored values do not match the new key, the lookup misses, and the next page load re-evaluates and stores under it — the ordinary "changed" path, needing no backfill, and deterministic because the seed is kept in `_mtrandseed`.

Two bugs were found and fixed in the writing rather than by a test:

- **`evaluate_template_params()` memoised on the language and seed alone.** Two layers of a chain commonly share a language and always share the seed, so with more than one layer the memo would hand one layer another layer's evaluated parameters. The key now includes the parameters.
- **The saved evaluation is per layer, not per question.** `templateparamsevald` is a column, so only the question has one; reading `$this->templateparamsevald` while evaluating a *prototype's* parameters would have applied the question's saved copy to the prototype's source. It is passed in per layer instead, and a prototype's parameters are evaluated live.

**`get_prototype()` is off the attempt path but not gone.** The authoring form and the test helpers build a question without `get_question_options()`, so they have no chain; both the parameter merge and the file merge fall back to the CodeRunner 5 two-layer arrangement when `chainlayers` is absent, and `evaluate_question_for_display()` loads a prototype only then. That is what removes the five-table lookup from every page load of every attempt, which was the point. The fallbacks go with the form, in M7. `template_params_json()` survives with a narrower meaning — the question's own parameters, excluding what it inherits — which is what the form actually wants it for.

**Decision 10 is not implemented.** The legacy path by which a template parameter can act as a UI parameter is still in place, because removing it needs the fail-noisily half as well, and bundling that here would have made this increment harder to verify.

**Development site state at that date**, which is transient and recorded only so a fresh session knows what it is looking at:

- M0 to M3 are committed as `5ae9cb56`, "Up to milestone3 in the V6 rewrite". The M4 work described below is uncommitted. `public/local/coderunner_manager/` is still untracked in the Moodle repository and is not yet a repository of its own, so M2's output has no commit anywhere.
- `local_coderunner_manager` is installed and its tables are populated: 72 prototypes, 251 file records.
- `question_coderunner_options` has `prototypeid` and `overrides` added and filled for all 12,751 rows. Additive only; no pre-existing column was written.
- `qtype_coderunner` requires Moodle 5.0 and declares the manager as a dependency; `$plugin->release` is still `5.10.9` and wants deciding.
- `default_penalty_regime` is gone from the settings page; its config row is unset by the install task.
- `tests/fixtures/test-sandbox-config.php` points `jobe_host` at `localhost:4000`; it previously named the unresolvable Docker host `jobe`, which is why the whole suite appeared to fail.
- Use `~/.config/composer/vendor/bin/phpcs --standard=moodle` (3.13.6 with `moodlehq/moodle-cs`). The `moodle-local_codechecker` copy bundles PHP_CodeSniffer 3.5.8, which reports named arguments as goto labels and carried a high-severity CVE.
- Thirty-odd lint errors across the new code are all `enum` and `readonly class` blind spots in moodle-cs that Moodle core also trips; none is actionable.

Suites, after M4's second increment: 221 tests / 1395 assertions in `qtype_coderunner_testsuite` (19 skipped, all sandbox-dependent), 59 / 337 in `local_coderunner_manager_testsuite`. Both remain the pre-M4 208 / 1370 and 58 / 333 plus exactly the new tests, so no pre-existing assertion has changed.

One new lint error, in `question.php`: the class comment was `/*` and is now `/**`, which it has to be for the `@property-read` block to mean anything to an IDE, and moodle-cs reports any docblock after a `require_once` as an inline one. Every test file in the plugin already trips the same sniff.

Line numbers are from Moodle 5.2.1 and will drift; the surrounding function names are the durable reference. Paths are relative to Moodle's `public/` directory **except** `admin/cli/`, which in Moodle 5.x sits at the repository root, outside the document root (`public/admin/cli` does not exist). That matters for the comparison script in milestone 1.

## 1. The *to check* items, resolved

### 1.1 Precheck-mapped questions — the note is right

`restore_create_categories_and_questions::process_question()` (`backup/moodle2/restore_stepslib.php:5221`) has two branches. The create branch sets the `question_created` mapping at line 5329. The mapped-to-existing branch at line 5385 calls `set_mapping('question', ...)` and returns normally; it does **not** return `SKIP_ALL_CHILDREN`. So child paths, including the `local` plugin path registered at line 5080, are processed either way.

The skipping convention is entirely plugin-side: `restore_qtype_plugin::process_question_answer()` (`backup/moodle2/restore_qtype_plugin.class.php:162`) and CodeRunner's own `process_coderunner_options()` (`question/type/coderunner/backup/moodle2/restore_qtype_coderunner_plugin.class.php:111`) test `question_created` and bail. Nothing in core prevents the manager from restoring a prototype whether or not the question that carried it was created.

**Gotcha not in the note.** When there is no mapping for the question at all, lines 5228-5230 do a plain `return`, not `SKIP_ALL_CHILDREN`. The local plugin's handler still fires and `get_new_parentid('question')` will be false. The handler must not assume it has a question id.

### 1.2 Restore order — the note is right

`restore_create_categories_and_questions` is added in the **root task** (`backup/moodle2/restore_root_task.class.php:94`). `restore_plan_builder::build_plan()` adds the root task (line 105), then the type-specific plan (course, then sections, then activities), then the final task (line 124). The course-level `local` path is inside the course task's structure step (`backup/moodle2/restore_stepslib.php:1961`).

So restored questions always arrive before course-level prototypes, and a child prototype restored at the course point can have its parent at the question point, exactly as the note assumes.

### 1.3 Which `after_restore_*` method runs — answered: `after_restore_question()`, in every restore type

No extra final step is needed. The dispatch chain is:

- `restore_plan::execute_after_restore()` (`backup/util/plan/restore_plan.class.php:205`) iterates **every** task in the plan.
- `restore_task::execute_after_restore()` (`backup/util/plan/restore_task.class.php:115`) calls `launch_after_restore_methods()` on every step of every *executed* task.
- `restore_structure_step::launch_after_restore_methods()` (`backup/util/plan/restore_structure_step.class.php:423`) calls it once per distinct processing object, i.e. the `restore_local_coderunner_manager_plugin` instance.
- The method name comes from `basename($connectionpoint->get_path())` (`backup/moodle2/restore_plugin.class.php:113`), so the question connection point gives `after_restore_question()`.

Because the question step lives in the root task, which exists for `TYPE_1ACTIVITY` too, `after_restore_question()` runs on quiz duplication; `after_restore_course()` would not. It is scheduled by `restore_execute_after_restore` in the final task (`backup/moodle2/restore_final_task.class.php:105`), which core's own comment marks as the last step permitted to modify restored information. That is exactly where the linking stage belongs.

**Caveat.** It fires only if `define_question_plugin_structure()` returned at least one path, because the path elements are what gets iterated. The manager must therefore always declare its question-level path, even for a backup containing no prototypes, or linking silently never happens.

### 1.4 Mappings exist when attempt data is recoded — the note is right, but the wrong plugin does it

`recode_response` is reached from `restore_questions_attempt_data_trait::questions_recode_response_data()` (`backup/moodle2/restore_stepslib.php:6392`), called by `restore_question_attempt_step_worker()` (line 6371), and only ever on the **qtype** restorer (`get_qtype_restorer($qtype)`, line 6394). There is no `local` hook on that path.

So the `_prototypeversions` rewrite must live in `restore_qtype_coderunner_plugin::recode_response()`, calling into the manager. The note reads as though the manager does it itself; it cannot.

Timing is fine. Attempt steps are restored by `restore_quiz_activity_structure_step` in the activity task, after the root task created the questions and ran the question-level local paths that `set_mapping()` the prototypes. Cross-plugin lookup works because `restore_plugin::set_mapping()` (`backup/moodle2/restore_plugin.class.php:192`) does not namespace the item name, and `backup_ids_temp` is keyed by restore id, which both plugins share through `$this->task->get_restoreid()`. Make the item name a shared constant so the two plugins cannot drift apart.

Note also that this runs **before** `after_restore_question()`. The old-id to new-id decision must therefore be taken during the structure step, which is what the notes already require of the mapping stage. The two notes are consistent only because of that, so they should cross-reference each other.

### 1.5 Course deletion: callback or hook — both exist, and neither does what the note asks

Both fire, the callback first:

- `pre_course_delete` is still live and **not** deprecated. `delete_course()` dispatches it at `lib/moodlelib.php:4711-4718` through `get_plugins_with_function()`. Core still uses it (`tool_recyclebin`, `block_myoverview`, `block_recentlyaccesseditems`).
- The Moodle 5 replacement is `\core_course\hook\before_course_deleted` (`course/classes/hook/before_course_deleted.php:31`), dispatched at `lib/moodlelib.php:4720-4724`.

New code should use the hook.

**However, "after its questions" is not achievable with either.** Both run before `remove_course_contents()` (`lib/moodlelib.php:4734`). The only post-deletion point is an observer of `\core\event\course_deleted` (line 4749).

That is probably not wanted anyway: the test "is this prototype still used by another course" only has to ignore the dying course's own questions, which it can do perfectly well before deletion. Recommendation: do all of it in `before_course_deleted` and strike "after its questions" from the notes.

## 2. Other claims verified

These were not marked *to check*, but the design leans on them.

- **Regrade detection is sound, and the two-part test is necessary for a reason the notes do not give.** `regrade()` passes the old step id (`question/engine/questionattempt.php:1433-1435`). `start()` calls `apply_attempt_state()` only when `$submitteddata` is non-empty (lines 1067-1070). On an ordinary load, `question_attempt_step::load_from_records()` (`question/engine/questionattemptstep.php:514-516`) builds a `question_attempt_step_read_only` **with `$step->id` set**, so the id test alone cannot discriminate; the `instanceof` test is what does. The id test is needed for a third case the notes do not mention: `start_based_on()` (`question/engine/questionattempt.php:1092`) calls `start()` with resume data and no existing step id, giving a writable step with a null id. That is the quiz's "each attempt builds on the last", a real feature. So `!($step instanceof question_attempt_step_read_only) && $step->get_id() !== null` is the correct test, and the `start_based_on` case belongs in the guard unit test.
- **Two different `apply_attempt_state()` methods share the name.** `start()` calls the behaviour's; `question_behaviour::apply_attempt_state()` (`question/behaviour/behaviourbase.php:379-382`) delegates to the question's. Ordinary loads reach the question's directly through `ensure_question_initialised()` (`question/engine/questionattempt.php:1706`). CodeRunner already overrides the question's (`question/type/coderunner/question.php:262`), which is the right hook; `qbehaviour_adaptive_adapted_for_coderunner` does not intercept it, inheriting through `question_behaviour_with_save`, which delegates.
- **Refreshed versions persist.** `notify_step_added()` moves an id-bearing step from the deleted list to the modified list (`question/engine/datalib.php:1439-1450`), and `update_question_attempt_step()` (lines 256-266) does `update_record` then deletes **all** `question_attempt_step_data` rows for the step and re-inserts from the step object. A qt_var set in `apply_attempt_state()` during a regrade is therefore saved.
- **`update_attempt_state_data_for_new_version()`** is reached only in the `else` branch of `get_attempt_state_data_to_regrade_with_version()` (`question/engine/questionattempt.php:1480-1495`), i.e. when the question version actually changed. It cannot see prototype-only changes.
- **Existing parameter caching** is as described (`question/type/coderunner/question.php:386-406`), and already uses `is_a($step, 'question_attempt_step_read_only')` as its write gate. Reuse that idiom rather than inventing another.
- **Local plugins get no backup settings.** `backup_local_plugin` is an empty subclass (`backup/moodle2/backup_local_plugin.class.php:34`); `backup_plugin::define_plugin_structure()` (lines 67-77) only dispatches to `define_<point>_plugin_structure()`; `add_plugin_structure()` (`backup/util/plan/backup_structure_step.class.php:144-171`) creates no UI setting. `get_setting_value()` (`backup/moodle2/backup_plugin.class.php:85`) can read existing settings such as `users`.
- **Attachment points.** Backup offers `course` (`backup/moodle2/backup_stepslib.php:586`), `section`, `module`, `grade_item`, `question` (line 2687) and three grading-area points. Restore has matching ones, with `question` registered **twice**: `backup/moodle2/restore_stepslib.php:5058` for pre-4.0 backups and line 5080 for modern ones. A single `define_question_plugin_structure()` serves both, because `get_pathfor()` builds the path from the connection point. Convenient for legacy import.
- **`LOG_WARNING` really is invisible.** `backup/restore.php:134` adds the HTML logger only when `$backupmode != backup::MODE_ASYNC`, and captures its HTML only `if ($CFG->debugdisplay)` (line 148). `enableasyncbackup` defaults to 1 (`admin/settings/courses.php:789-790`). `restore_prechecks_helper::execute_prechecks()` calls only `restore_dbops::*`, with no plugin dispatch. Notifications are the only route for renamed prototypes.
- **Shared-bank restore behaves as described.** `calculate_question_categories()` (`backup/util/dbops/backup_question_dbops.class.php:40-85`) emits `question_category_complete` for the backed-up context and `question_category_partial` for foreign categories holding annotated questions. `restore_move_module_questions_categories` (`backup/moodle2/restore_stepslib.php:5569-5637`) repoints `question_references` to the originals when same-site and `mod/qbank:view` holds on the original bank, otherwise creates a bank with `question_bank_helper::get_default_open_instance_system_type($course, true)`. The restored copies are indeed left behind.
- **Import refusal works as planned.** `qformat_default::$stoponerror` defaults to true (`question/format.php:51`); the gate at lines 369-372 sits after parsing and before any database write; the form default is 1 (`question/bank/importquestions/classes/form/question_import_form.php:101`).
- **Migration scale confirmed.** `questiontypebase::save_question_options()` (`question/type/questiontypebase.php:586-589`) keys the extension table on `questionid`, and every question version is its own `question` row. One `question_coderunner_options` row per version.

## 3. Corrections

### 3.1 `set_inherited_fields()` is looser than the notes say, and it matters for the comparison script

`question/type/coderunner/questiontype.php:486-521`: a field counts as an override only if it is set, non-null, **not the empty string**, and `!=` (loose comparison) the prototype's value. So a blank value means *inherited*; it is not "turned into null". The only fields actually nulled are `sandbox`, `grader` and `sandboxparams` (lines 510-520). The "blank becomes null" behaviour the notes describe is in `clean_question_form()` (lines 379-394), which nulls every inherited field that is blank, or every inherited field at all when `customise` is off.

The loose `!=` is the trap. Today `'5'` and `5`, `'0'` and `''`, `'1.0'` and `'1'` all compare equal, so a question storing any of those is treated as inheriting. A comparison script that compares strictly will report thousands of spurious differences on a real site. It must normalise per registry field type, which is a good reason to build the registry before the script.

### 3.2 There is no JSON column type in Moodle

Design section 5 describes the overrides document as "a single JSON document (a native DB column type)". XMLDB offers only `INTEGER`, `NUMBER`, `FLOAT`, `CHAR`, `TEXT`, `BINARY`, `DATETIME` and `TIMESTAMP` (`lib/xmldb/xmldb_constants.php:42-58`). The column must be `TEXT`, so there is no portable server-side JSON querying.

In practice this costs little, because every query the design describes keys on `prototypeid`, a real column. But "which questions override field X" becomes a PHP scan or needs a shadow column. The design wording should be fixed so nobody plans around a facility that does not exist.

### 3.3 `UPDATE ... JOIN` is not portable

MySQL and MariaDB use `UPDATE a JOIN b ... SET`; PostgreSQL uses `UPDATE a SET ... FROM b`; SQL Server uses `UPDATE a SET ... FROM a JOIN b`. `$DB->execute()` passes SQL straight through, so the migration needs per-family SQL or a portable correlated-subquery form.

Relatedly, `insert_records()` (`lib/dml/moodle_database.php:1844`) is overridden with a genuine multi-row insert only by the mysqli and pgsql drivers; on sqlsrv it loops `insert_record()`. The one-to-three-minute estimate in the notes should be measured on more than one driver family.

### 3.4 Plugin upgrade order is fixed, not dependency-ordered

`upgrade_noncore()` iterates `core_component::get_plugin_types()` in its declared order (`lib/upgradelib.php:1953-1957`). In this checkout `qtype` is index 4 and `local` is index 41, so **`qtype_coderunner`'s upgrade step always runs before `local_coderunner_manager`'s**, whatever either declares in `$plugin->dependencies`. Dependencies gate installability, not upgrade order.

`upgrade_plugins()` itself never looks at `$plugin->dependencies`; the only version check it makes is `$plugin->requires` against the Moodle core version (`lib/upgradelib.php:712-722`). All three consumers of `$plugin->dependencies` are elsewhere and none of them affects execution order: `other_plugins_that_require()` blocks uninstall, `are_dependencies_satisfied()` via `all_plugins_ok()` blocks the upgrade from starting, and `resolve_requirements()` builds the plugin-check page display (`lib/classes/plugin_manager.php:725, 790, 845`).

This holds however the plugin files arrive. The plugin installer resolves and downloads missing dependencies, but it only places files: `admin/plugins.php:160` then redirects to `admin/index.php`, which runs the whole upgrade through `upgrade_noncore()` in the fixed type order. Dependency resolution orders downloads, never database code.

**Confirmed empirically** on the development site's own upgrade history. In the course upgrade of 24 April 2026, the first `upgrade_log` entry for each plugin type appears in exactly the fixed order, with absent types skipped: `aiplacement` (log id 4015), `aiprovider` (4021), `antivirus` (4039), `availability` (4042), **`qtype` (4060)**, `mod` (4117), `auth` (4216) ... `qbank` (4754), `qbehaviour` (4812), `qformat` (4845), `tool` (4866) ... `theme` (5017), `h5plib` (5023). `local` sits between `theme` and `h5plib` in the fixed list, so a local plugin in that run would have been reached around log id 5020 — roughly a thousand entries after the question type.

Consequence: the qtype's upgrade step cannot reference the manager's tables, because on a fresh 6.0 install they do not exist yet. Together with sections 3.6 and 3.7 this splits the migration in a specific way, set out at the head of section 4.

### 3.5 Minor

- The comment at `question/type/coderunner/questiontype.php:419` refers to `restore_qtype_coderunner_plugin::after_restore_question()`, which does not exist in that class. Worth fixing, since the notes rely on that method name.
- `install.xml` carries four columns that are not current registry candidates: `enablecombinator`, `combinatortemplate` and `pertesttemplate` are legacy-only and absent from `extra_question_fields_static()`, while `answerboxcolumns` is marked defunct but is still in the extra fields list. The registry needs an explicit "legacy, not a registry field" list so the converter can neither silently drop nor silently carry them.


### 3.6 `db/install.php` runs *after* the version savepoint

`upgrade_plugins()` (`lib/upgradelib.php:740-761`) installs a brand-new plugin in this order: run `install.xml`, then `upgrade_plugin_savepoint(true, $plugin->version, ...)`, **then** call `xmldb_<component>_install()` from `db/install.php`.

So if a migration placed in `install.php` throws, the plugin's version is already recorded as current. On the next upgrade run the plugin falls into neither the install branch nor the upgrade branch, so nothing runs again and the failed migration is never retried. Moodle's provided escape is the `installrunning` config flag set around the call (line 756) plus the `xmldb_<component>_install_recovery()` hook (lines 727-733), which must be implemented if `install.php` does anything non-trivial.

This mattered while the migration was planned to run from `install.php`. It no longer does: under section 3.9 the migration runs from an adhoc task and `install.php` only creates tables and queues it, so there is nothing for a failure to leave half done. The constraint is recorded because it will apply again to anything substantial that is ever put in that step.

### 3.7 `$plugin->dependencies` guarantees presence, not ordering

`core_plugin_manager::are_dependencies_satisfied()` (`lib/classes/plugin_manager.php:743-756`) compares against `$otherplugin->versiondisk` — the version **on disk**, not the installed version. `all_plugins_ok()` is called before the upgrade begins, on both the web path (`admin/index.php:253, 538, 745`) and the CLI path (`admin/cli/upgrade.php:215`).

So a dependency declared by the question type on the manager guarantees the manager's code is present and new enough before any upgrade step runs, and blocks the upgrade with a clear plugin-check message if it is not. It does **not** make the manager install first: the fixed plugin-type order of section 3.4 still puts `qtype` at index 4 and `local` at index 41.

### 3.8 Things that turned out not to be constraints

- **Table name length.** The XMLDB limit is 53 characters, not the 28 of older Moodle releases: `xmldb_table::NAME_MAX_LENGTH = 63 - PREFIX_MAX_LENGTH` (`lib/xmldb/xmldb_table.php:40-49`). `local_coderunner_manager_prototypes` is 35 and fits comfortably. Generated index and key names are truncated to 30 characters automatically by `sql_generator::getNameForObject()` (`lib/ddl/sql_generator.php:128, 1080-1108`), so they need no manual shortening.
- **Underscores in the plugin name.** `core_component::is_valid_plugin_name()` (`lib/classes/component.php:1529-1553`) forbids underscores only for `mod` plugins. For every other type the pattern allows them, provided the name does not start or end with one and contains no doubled underscore. `local_coderunner_manager` is valid.
- **Running a plugin CLI script before the upgrade has been applied.** Nothing blocks it. `moodle_needs_upgrading()` is consulted only by cron (`lib/classes/cron.php:70`), the check API (`lib/classes/check/environment/upgradecheck.php:75`) and one debugging guard in `lib/classes/context.php:379`. Contexts instantiate normally. So a script shipped with 6.0 can be run against a 5.x database before anyone triggers the upgrade, provided it touches only columns that already exist.

### 3.9 Question banks do not exist when the question type upgrades

This is the constraint that decides where the migration runs, and it is stronger than it first appears.

`mod_qbank` is new in Moodle 5.0, and `mod/qbank/db/install.php` queues `\mod_qbank\task\transfer_question_categories` as an adhoc task from its own install step. The fixed plugin-type order puts `qtype` at index 4 and `mod` at index 5 (section 3.4). So on an upgrade from Moodle 4.5, at the moment the question type's upgrade step runs:

- `mod_qbank` is not yet installed;
- no question bank module instances exist;
- question categories are still in their pre-5.0 contexts;
- the transfer has not even been queued.

The data is absent, not merely uncached, so reading the contexts by direct SQL instead of through `question_bank_helper` does not help. Many sites will install CodeRunner 6 while upgrading from Moodle 4.5, so this is not a corner case.

**Prototype installation is unaffected, and already handles it.** `update_question_types()` defers to an adhoc task precisely because "if this is an install, the qbank module hasn't been installed yet", and `moodle5_top_category()` creates the site question bank itself when it finds none. Installing prototypes needs one context and makes it; so this has never failed in practice.

**The migration is affected.** It needs, for every question, the set of contexts to search for that question's prototype by name, which is every bank in that question's course. Before the transfer runs there are no course-level banks, so `prototype_contexts()` would return only the site bank and every question whose prototype lives in its own course would be recorded as broken — silently, and at scale.

**Adhoc task order does not save it.** Tasks are ordered `COALESCE(run.running,0) ASC, run.earliest DESC, q.nextruntime ASC, q.id ASC` (`lib/classes/task/manager.php:942`), so with equal `nextruntime` they run FIFO by id. Since `qtype` precedes `mod`, CodeRunner's task is queued *first* and would therefore run *before* the transfer. The migration task must check whether a `transfer_question_categories` row is still pending in `{task_adhoc}` and, if so, re-queue itself and exit.

## 4. Milestones for 6.0

Eleven milestones. M0 is the only one that ships in the 5.x line. M1 to M4 are strictly sequential, then M4a, and then the authoring UIs; M5 comes last of the 6.0 work. See decision 19 for why the order changed.

### Where the migration runs, and why it cannot be gated

An earlier draft put a before-and-after comparison in the question type's upgrade step, to throw before the first savepoint and leave the site on 5.x so the administrator could reinstate the previous code. That cannot work, and the reason is section 3.9: at the moment the question type's upgrade step runs, the question banks the comparison would have to search do not exist.

So the migration runs from an adhoc task, which is the mechanism CodeRunner already uses for the same reason:

1. **Question type upgrade step** (plugin type index 4). Add `prototypeid` and `overrides` as nullable columns. Nothing else. No contexts are needed and nothing is read.
2. **Manager install step** (plugin type index 41). Create the manager's tables and queue the migration adhoc task. Both are cheap and context-free, so `install.php` cannot leave a half-done job behind and needs no recovery hook.
3. **Migration task**, on cron. If `mod_qbank`'s `transfer_question_categories` task is still pending, re-queue and exit (section 3.9). Otherwise install the built-in prototypes, snapshot the user prototypes, and convert every question's options.
4. **Verification**, afterwards and on demand, by CLI. It compares each question's retained old columns against its new `prototypeid` and `overrides`.

**There is no gate, and no transitional fallback.** Between the upgrade finishing and the task running, questions have a null `prototypeid` and do not resolve. That window is bounded by cron latency rather than by the size of the migration, so it exists on every site whatever its question count, and the honest instruction is that cron must run before the site is used again. That is already true of CodeRunner today: since 4.6 the built-in prototypes have not existed until the post-upgrade task has run, so nothing new is being asked of administrators. A transitional fallback to the legacy name-based lookup was considered and rejected: it would put a second resolution path back on the run-time path, which decision 1's corollary removes, for a window measured in seconds on a site whose cron is working.

**Verification is stronger after the fact than it would have been as a gate.** As a gate it compared two in-memory reconstructions produced by the same converter. As a post-migration check it compares what was actually written against the retained old columns, can be re-run as often as wanted, and runs in an environment where the question banks exist. The migration is additive — no column is dropped and no question deleted until phase 4 — so a fault found later is corrected by fixing the converter and re-running from the retained data, which is what design section 10 already contemplates, rather than by restoring a backup and losing the work since.

**Schema ownership still decides the split.** `prototypeid` and `overrides` are columns of `question_coderunner_options`, which belongs to the question type, and a plugin's `install.xml` creates only its own tables. So the question type must add them whatever the plugin order, and the manager must fill them later. The fixed type order happens to agree.

A corollary worth noting: the registry, resolver and converter still belong in the question type, but no longer because a gate runs there. The reason is the one in decision 1 — the question type defines the schema of the questions it runs.

### M0. Prototype health report (5.x)

The design doc's phase 1, and the only thing that ships in the 5.x line. Finds missing, duplicate and ambiguous prototypes and lists them with the reason. It needs nothing from M1: today's name-based lookup in `qtype_coderunner::get_prototype()` already returns the array of candidates on a duplicate and null on a miss, so the report is a loop over the site's CodeRunner questions plus presentation. The existing `scripts/findduplicates.php` and `scripts/prototypeusage.php` are the obvious models.

Keeping M0 separate from M1 matters: a comparison script in the 5.x line would be a frozen snapshot of a registry that is still changing in 6.0, and a clean run against a stale registry is worse than no run at all, because it reassures falsely.

**Exit:** run on our production sites; every anomaly it reports is one we can explain.

### M1. Field registry and resolver

The new data model and nothing else. No database change, no legacy code, no migration. Pure functions over arrays, so it is wholly unit-testable and reviewable in one sitting.

1. **Field registry.** One entry per field: name, whether it is instance or inheritable, type, default, merge rule, form section and help string id. Plus the explicit legacy-column list of section 3.5. Only the inheritable flag, the merge rule and the default are consumed in M1; type, section and help string are recorded now because writing forty entries once is cheaper than revisiting them in M7.
2. **Resolver.** Resolves an ordered list of layers, root first, into effective settings **with provenance** — which layer each value came from. Provenance is built in from the start because the question form, the prototype editor's *Resolved* view and the usage report all need it. Absent means inherited; present, including the empty string, means override (design section 6), so the resolver tests key presence, not truthiness. Cycle and depth guards live here.

**Exit:** unit tests covering each merge rule at more than two levels, empty-string-as-override, the cycle and depth guards, and provenance correctness. Nothing else in the plugin calls it yet.

### M1a. Converter and migration check

Separated from M1 because it is migration machinery, not the data model, and because it cannot be meaningfully exercised until prototypes exist in their new form (M2). Scheduled immediately before M3, which consumes it.

1. **Legacy reader and converter.** A `question_coderunner_options` row to a `(prototype identity, overrides map)` pair, and today's name-based lookup extracted into an in-memory index keyed by name and the `prototype_contexts()` set.
2. **Legacy resolver.** A faithful reimplementation of `set_inherited_fields()` and the `evaluate_merged_parameters()` and `get_files()` merge rules, **including the loose comparison** of section 3.1. Development and migration scaffolding only: it is never on the run-time path, and it is deleted once the migration is retired.
3. **The standalone CLI verification script.** Streams `question_coderunner_options` with the single joined recordset the notes describe, compares each question's retained old columns against the new resolution, using the registry's normalisation, and reports totals, differing rows with field-level diffs, type-only differences counted separately, and already-broken questions grouped by prototype name rather than listed row by row. Progress output throughout. It is run after the migration task has completed, not as a gate before it; see *Where the migration runs*.

**What the comparison does and does not prove.** Migration makes every legacy prototype a *root* prototype (design section 10), so the chains it produces are always exactly two layers: prototype, then question. The comparison therefore exercises the converter's null, blank and loose-equality handling, the registry's completeness, the replace and merge-by-key rules at one level, and the name-lookup-to-identity step. It does **not** exercise multi-level inheritance, because the migration never produces any. Multi-level resolution is covered by M1's unit tests and exercised for real once authors build child prototypes in M6.

Support files are **not** compared per question. The merge rule is identical before and after, so a difference could only come from a chain difference, which the field comparison already catches, or from a basic bug that unit tests catch more cheaply.

Template parameters are compared as the raw per-layer strings plus their language and evaluation settings, never evaluated. That is sufficient, because evaluation is a pure function of (raw string, language, seed), but it is a weaker check than comparing merged output and should be read as such. `templateparamsevald` is a cache and is excluded, as it already is from the identity hash.

**Scale note.** On the development site, COSC131-25S1 has no broken current questions but 396 broken older versions, across 144 questions and only eight retired prototype names, 87% of them `python3_stage1`. Unresolvable rows are a bulk case on real data, not an edge case, which is why the comparison script groups them by name.

**Exit:** the script runs clean on our production sites, and the timing figures in the notes are replaced by measurements.

### M2. Prototype storage and the manager plugin skeleton

`local_coderunner_manager`: `version.php`, the prototypes and versions tables, the support-file area, a privacy provider, and a storage API (create, load, save, version history, name uniqueness enforced in a transaction, cycle and depth guards). The UI is a bare list page behind `qtype/coderunner:management`, enough to see state and no more.

Plus a re-runnable, idempotent **snapshot** of the existing user prototype questions, and installation of the built-ins, neither of which touches a question.

**Tables** are named `local_coderunner_manager_prototypes` and `local_coderunner_manager_versions`. The component name as prefix is **required, not a convention**: `drop_plugin_tables()` skips any table whose name does not begin with the component name (`lib/adminlib.php:379`), so a shorter prefix such as `local_crmanager_` would leave both tables behind on every uninstall, silently and permanently. A test asserts the prefix for exactly this reason.

At 35 and 33 characters they are well inside the 53-character XMLDB limit (section 3.8), and generated index and key names are truncated to 30 characters automatically, so the length costs nothing.

**Support files** are stored through the file API in the manager's own component, with the version row's id as the item id, in the context of the course that owns the prototype — so a course prototype's files travel with the course in a backup, and a site prototype's live in the front page course's context.

A version is a complete record, so a save that does not touch the files carries the previous version's forward. That costs a file record and no content, because the file API stores content by hash: on the development site, snapshotting 53 prototypes produced 251 file records holding 67 distinct contents, every one of them already stored by the question bank, so 32 MB of nominal support files — including a 30 MB zip — added nothing to disk.

Files merge by filename along the chain with the nearest layer winning, which is how a child prototype replaces one of its parent's support files without copying the rest. That merge is done by the repository rather than by the resolver, because files do not live in the overrides document; the registry's `supportfiles` entry records the rule, and both share one chain walk.

Uninstalling needs no hook of its own: `uninstall_plugin()` drops the tables from `install.xml`, unsets the settings, and calls `delete_component_files()` for the component (`lib/adminlib.php:225-251`).

**prototypes** — `id`, `name`, `courseid` (SITEID for site scope), `uuid`, `builtin`, `currentversionid`, `timecreated`, `timemodified`, `usercreated`, `usermodified`. Unique index on (`courseid`, `name`); non-unique on `uuid`, which a restore or import may legitimately duplicate (notes, *Storage*).

**versions** — `id`, `prototypeid`, `version`, `parentid`, `overrides`, `summary`, `documentation`, `commitmessage`, `timecreated`, `usermodified`. Unique index on (`prototypeid`, `version`).

The parent prototype is recorded on the version row only, never copied onto the prototype row. Resolution has to read each layer's overrides, which are in the version row, so every chain query joins to `versions` anyway and a denormalised copy would buy nothing while creating a second place for the parent to be wrong.

Summary and documentation are likewise versioned, so that the history shows documentation changes and an author can compare the current text against what it replaced. Documentation takes over the role a prototype question's question text plays today, and that is versioned today. The storage cost is negligible: a course with forty prototypes and ten versions each is a few megabytes of text, and the history pruning script of M8 bounds it.

**The Site defaults prototype is created here, not in M8.** Design section 6 puts a single *Site defaults* prototype at the root of every chain, holding the values CodeRunner 5 keeps in code and in admin settings. An earlier draft of this document scheduled it in M8, which is wrong: it is the root every other prototype is parented to, so it has to exist before anything is installed or snapshotted.

Section 10 appears to contradict this, calling migrated user prototypes "root prototypes (no parent)". The same paragraph settles it: the test case field switches "are all *on* in the Site defaults prototype, and migrated prototypes leave them unset", which a migrated prototype can only do if Site defaults is in its chain. "Root" there means directly under Site defaults rather than under a built-in, not parentless. So the built-ins and the migrated user prototypes alike are parented to it.

It differs from a built-in in one way that matters: a built-in is **rewritten by every release**, whereas Site defaults is **created once and never touched again**, because administrators edit it and an upgrade that overwrote it would discard their settings silently. It therefore carries its own `issitedefaults` flag rather than reusing `builtin`, and it cannot be deleted.

Its initial values are the registry defaults for every inherited field whose default is not null, plus the penalty regime taken from the `qtype_coderunner/default_penalty_regime` admin setting that supplies it today. A null registry default means "not set" — no CPU time limit, as against a limit of zero — so those fields are left unset rather than pinned.

This is also what makes the migration rule in M1a correct rather than merely cautious: the fields that become inheritable in CodeRunner 6 get their defaults *here*, so declining to carry whatever a CodeRunner 5 prototype question happened to store in them loses nothing.

**Redundant overrides are pruned as prototypes are created.** CodeRunner 5 stored a value for every inheritable field of a prototype question whether the author chose it or not: the editing form defaulted the grader to `EqualityGrader` and "use Ace" to true, and almost nobody touched either. Carrying those through would make each migrated prototype state preferences nobody expressed, and would stop it following a later edit to Site defaults, which is the whole point of having Site defaults at the root. So an override is dropped when the parent chain already resolves to that value, compared through `field::normalise_for_comparison()`. Collected fields are never pruned, since every layer contributes to them and a contribution is not redundant merely because it matches.

This is the *flatten* tool of design section 10, applied at migration time rather than offered afterwards, which is cheaper than creating the mess and then cleaning it up. On the development site it took the 72 prototypes from 11.2 stored fields each to 7.3, and left the built-ins stating only what they actually change — `python3` keeps its template, language and sandbox parameters and no longer claims a grader. Resolution is unaffected by construction and was checked against 1200 real questions, all of which resolved identically.

**Who installs the built-in prototypes.** Not the question type's install step: the fixed plugin-type order puts `qtype` at index 4 and `local` at index 41 (section 3.4), so at that moment the manager's tables do not exist. The adhoc task queued by the manager's install step does it, reading the definitions from the question type's `db/builtin_PROTOTYPES.xml`. One task installs the built-ins, snapshots the user prototypes and converts the questions, in that order, because they have to happen in that order anyway. The question type's existing `qtype_coderunner_setup_question_prototypes` task retires: in 6.0 prototypes are not installed as questions at all.

**Exit:** the snapshot runs twice on a real site and the second run creates no new versions; every snapshotted prototype's chain builds and resolves; for a sample of real questions the settings resolved from the snapshot match what `get_prototype()` and `set_inherited_fields()` produce today; name uniqueness including the clash with a site prototype, cycle rejection on save and the depth guard all have tests; and install and uninstall round-trip cleanly.

The earlier exit criterion, pointing M1's comparison script at the new tables, no longer applies: that script is now M1a's verifier and compares a question's old columns against its new ones, which do not exist until M3. The sampled check above is the substitute, and it is the first point at which chains deeper than two layers can be built by hand and resolved against real data.

### M3. Question schema and the migration

Built. The question type's upgrade step adds `prototypeid` and `overrides`, both nullable, with an index on the former; the manager's adhoc task converts every question after it has installed the prototypes; and a CLI script verifies the result afterwards.

**The two rules are opposites, and must be.** A field CodeRunner 5 inherited keeps the "null or empty means inherit" rule, so a question that inherited it goes on inheriting it. A field CodeRunner 5 did *not* inherit is pinned whether the column is null or not, because the question's value was effective. The prototype rule is the reverse — a prototype's value for those fields was ignored, so carrying it over would invent a preference — and a test asserts the two converters disagree in exactly that way.

Twenty fields are pinned per question. The consequence is that the new inheritance does nothing for existing questions until an author deliberately flattens them, which is the author's decision rather than the migration's.

Instance fields keep their own columns and stay out of the overrides document, which holds only what a question overrides of its prototype.

**Resolution is done from indexes built once**, not by calling `get_prototype()` per question: every version of every question has an options row, and one site has reported 410,000 questions. **Writes are batched** as a single `UPDATE ... CASE` statement per 500 rows, which avoids both `UPDATE ... JOIN` and a temporary table and so needs no per-database-family SQL (section 3.3).

**Verification calls CodeRunner 5's own code** for the "before" value rather than reimplementing it. It skips any field with no CodeRunner 5 column, since there is nothing to compare: on its first run it reported all 12,346 questions as differing in the four new test case field switches, which was the comparison's fault and not the data's.

**Measured on the development site:** 12,346 questions converted in 3.3 seconds, 0.26 ms a row, which extrapolates to about 105 seconds at 400,000 rows. Verification took 60 seconds for 12,751 rows because the old side queries per question; at 400,000 that is roughly 35 minutes, which is tolerable for a re-runnable diagnostic but would want the same index treatment to be comfortable on the largest sites.

**Correction found in M4, and it was the largest fault so far.** A migrated prototype carried only `legacy_converter::fields_inherited_in_v5()`, which is `extra_question_fields_static()` less `noninherited_fields()`. That subtraction looks like "what a prototype supplied" and is not: `noninherited_fields()` says which controls the authoring form hides behind its *Customise* checkbox. Template parameters, UI parameters and support files each reached a question by their own dedicated mechanism — `evaluate_merged_parameters()`, `evaluate_merged_ui_parameters()` and `get_files()` — so they are absent from that list while being fully effective at run time.

So migrated prototypes silently lost their template and UI parameters: on the development site, 12 prototypes' template parameters and 25 prototypes' UI parameters, reaching **11,883 of 12,346 questions**. The lost values were not decoration — `wrapper_src`, which tells the scratchpad UI where to find its wrapper, and the `isdirected` and `isfsm` flags of the graph UI.

**Why nothing caught it.** The fault was latent, not live: `evaluate_merged_parameters()` and `evaluate_merged_ui_parameters()` still read the legacy prototype *question*, so behaviour was correct and remained correct through M4's first three increments. It would have become live in the increment that moves the collected fields onto the chain, at which point 96% of the site's questions would have lost their UI configuration at once. The verifier could not see it either, by construction: decision 3 excludes support files from comparison, and the collected fields are compared as each layer's raw strings rather than as merged output, so a prototype contributing nothing where it should contribute something is invisible to it.

**The lesson is about the premise, not the code.** Three separate places had written down that a prototype's value for these fields "was ignored by CodeRunner 5". It was a plausible reading of `noninherited_fields()` and it was wrong, and no test could contradict it because every test encoded the same assumption. What found it was reading the live data before building on it — the migrated prototypes had zero template and UI parameters where the CodeRunner 5 rows had plenty.

Fixed by `legacy_converter::fields_supplied_by_v5_prototypes()`, with the language and per-try settings carried only alongside actual parameters, since alone they describe nothing. Three tests pin it. The existing data was repaired by re-running `install_builtins()` and `snapshot_user_prototypes()` with the corrected converter — 2 built-ins and 41 user prototypes gained a version 2 — which is exactly the recovery route decision 8 reserves for this case, and needed no backup.

**Exit, met:** the migration is idempotent, re-running converts nothing; it changes no existing column, which a test asserts field by field; an unresolvable question is left with a null `prototypeid` and still gets its overrides document, so it is recorded as broken rather than silently acquiring someone else's prototype; and verification reports zero differences across every question on the development site.

### M4. Switch the run-time path

`get_question_options()` and `qtype_coderunner_question::get_prototype()` resolve from `prototypeid` and `overrides`. Resolution stays inside `get_question_options()`, where the `questiondata` cache already covers it, and carries the per-layer data the collected fields need into `$options` with it, so that nothing on the per-attempt path reads a prototype table. There is no resolved-settings cache in 6.0. See decisions 15 and 16.

**There is one resolution path, not two.** A question whose `prototypeid` is null is broken and reports itself as broken, which is what the design already specifies for questions the migration could not resolve ("stored without a prototype and stay broken", design section 10) and is how a missing prototype presents today. Name-based lookup survives only in the migration and in legacy import and restore (M5) — boundary code that runs once per question, never on the run-time path. The legacy resolver of M1a is scaffolding and is not shipped as an alternative.

**Reaching the resolved settings from existing code.** **39** of the resolved settings are public properties of `qtype_coderunner_question` today — the registry's 44 fields less `supportfiles` and the four test case field switches, which are not columns — read as `$question->template` on **about 210 lines of non-test code** and a further 260 in the test suite. The non-test sites are concentrated: `question.php` 53, `renderer.php` 45, `classes/jobrunner.php` 23, `edit_coderunner_form.php` 12, `questiontype.php` 5. (An earlier draft said 42 properties and 319 lines; both were counted loosely and are corrected here.) In CodeRunner 6 those values live in a `question_settings` object, so the three options are to rewrite every call site, to keep the properties and copy them off the settings object at load time, or to delete the properties and delegate through `__get`.

M4 takes the third, staged towards the first:

- The 39 properties are removed and `__get` resolves the name through the registry, so an unknown name throws instead of evaluating to null as it does today. No call site changes.
- `@property-read` lines are **generated from the registry** into the class docblock, with a test asserting they match `field_registry::all()`. Without them `__get` costs IDE completion, type checking and static analysis; generating them means they cannot drift from the field list.
- `__get` gains a `debugging()` notice when convenient, call sites are converted to `$settings->get(...)` as each file is touched for other reasons, and `__get` is deleted when the count reaches zero. Completing that is V7 work.

The explicit form is the better end state. It is staged rather than done at once because those 210 sites sit almost entirely in code the prototype change does not otherwise touch, so rewriting them would put risk into the most stable part of the plugin during its largest release, and because a 210-site commit is not reviewable whereas file-by-file conversion is.

**Built so far.** The columns are carried everywhere a question is written or read as data, which had to come first because `get_question_options()` cannot resolve from columns it does not load:

- `prototypeid` and `overrides` added to `extra_question_fields()` **and** to `noninherited_fields()`. The second is not optional: without it `clean_question_form()` nulls both on every save, because it nulls any inherited field that arrives blank, and `set_inherited_fields()` copies a prototype question's values over them.
- `qtype_coderunner::set_prototype_reference()`, called from `save_question_options()` immediately after `clean_question_form()`, derives both columns from the question's CodeRunner 5 columns. It must run there and not earlier: cleaning is what makes a null column mean "inherited", which is precisely the converter's input contract. Its rules match `question_migrator` deliberately, and a test asserts a question saved after the upgrade needs no migration.
- `qtype_coderunner::resolve_prototype_id()` and `prototype_repository::find_by_name_for_course()` do the name lookup. A name is unique across a course's prototypes and all site prototypes, so it returns at most one and needs no tie-break.
- Moodle XML **import** needed nothing: `qformat_default::importprocess()` calls `save_question_options()` directly (`question/format.php:513`), after the question, bank entry and version rows exist, so an imported question resolves its prototype by name like any other save. Moodle XML **export** blanks both columns on the exported copy, since a local id in a shareable file is at best meaningless.
- Restore nulls `prototypeid` as the deliberate stub of decision 17, and `/options/prototypeid` is excluded from the identity hash.

Two things found while doing it. `clone $question` in `export_to_xml()` is shallow, so `options` was shared with the caller and every "export only" change leaked back into the live question; options is now cloned too. And the migrator's own tests had to be changed to null the two columns before migrating, because a fixture saved through the form now arrives already converted — a test of the migration would otherwise pass without the migration running.

**The three write prerequisites dissolve into `__set`, and that is the whole trick.** Built, and the plan here was wrong about what it would cost. The concern was that `__get` fires only for *undefined* properties, so any assignment to one of the 39 names would create a real property that shadowed `__get` for the rest of the request — making `twig_all()`, `make_question_from_form_data()` (`edit_coderunner_form.php:1762-1774`) and `initialise_question_instance()` blocking prerequisites, each needing to be rewritten before the properties could go.

Defining **`__set`** removes all three at once. A write to a field name is intercepted and lands in the field values, so there is no property to shadow with and no call site to change. A write to anything else keeps the dynamic-property behaviour the authoring form depends on — it assigns every submitted form field, most of which are not question fields at all — so `#[AllowDynamicProperties]` stays rather than being a prerequisite. Removing it is V7 tidying, not a condition of this milestone. `twig_all()` likewise needs no change: its assignments now land in the field values, so the mutation is contained without having to return a new settings object.

**`__isset` is required, not optional**, and this part was right. `initialise_question_instance()` copies an option onto the question only when `isset()` says it is absent, and `classes/jobrunner.php:81` tests `empty($question->prototype)`. It returns true exactly when the value is not null, which is what `isset()` reported when these were declared properties. `__get` delegates an unknown name to `question_definition::__get()` (`question/type/questionbase.php:156`), which serves `latestversion` and returns null for a name nothing knows — the same as reading an undefined property did before. It does not throw: the plan's "an unknown name throws" would have to be reconciled with core returning null there, and nothing yet needs it.

**Two of the fields had property initialisers** — `answerboxlines = ''` and `validateonsave = 1` — and code reads them before a question is loaded, so those values are kept in a constant and served when nothing has set the field. Without this the two silently become null.

**What `__get` cost, and it was not the call sites.** No call site needed changing, as predicted. What did break was code that iterates a question object's *properties*, because the 39 are no longer among them:

- `export_to_xml()` cleared an inherited field by iterating the prototype object with `foreach ($row as $field => $value)`. It now iterates the inherited field names and reads each through `__get`. The rewrite also fixed a live hazard beside it: `get_prototype()` returns an *array* of candidates when a name is ambiguous, and the guard tested only truthiness, so the loop ran over the candidate list; it now tests `is_object()`.
- Two test fixtures built a question with `make_question()` and handed it to `save_question()` as the editing form. Production never does that — the form is always plain form data, and `questiontypebase::save_question_options()` reads it with `property_exists()` — so the prototype was saved without its template, time limit or template parameters, and five tests failed for that one reason. The base test case gained `form_data_from_question()`, which flattens a question into form data, and both fixtures use it.

This is the general lesson for the rest of the conversion: `__get` is transparent to anything that *reads a named field*, and invisible to anything that *enumerates properties*. The second set is small and findable, but it is where the work is.

**Resolution switched, and the whole suite passed unchanged.** `get_question_options()` now calls `resolve_inherited_fields()`, which builds the chain through `prototype_repository::chain_for()` from `prototypeid` and the question's `overrides`, and writes every resolved inherited field into the options object. Downstream sees the same flattened options it always has, so no call site changed and 221 tests passed with no edit to a single assertion — which is the result M3's verifier predicted, since it had already shown all 12,346 questions resolving identically both ways.

Four details that were decisions rather than mechanics:

- **`prototypeid` is the only authority on whether a question resolves.** A null one means broken, and the CodeRunner 5 name lookup is deliberately not consulted as a fallback, since a second resolution path is what this release removes. A chain that throws — a missing prototype, or parent links forming a cycle, both of which bad restored data can produce — is treated the same way.
- **Only `INHERITED` fields with a column are written.** Instance fields keep their own columns, collected fields are merged per layer rather than resolved to one value, and a field with no column is not part of the options object.
- **`customise`, which the CodeRunner 5 form still needs, is computed from provenance** — whether the question's own layer supplied the value — but only over `legacy_converter::fields_inherited_in_v5()`. Over the whole field set it would report every question as customised, because the migration pins twenty fields on every one of them.
- **`$question->prototype` is still loaded by name**, and only to merge the collected fields. That is the last use of the legacy lookup on this path, and it goes with the next increment.

`set_inherited_fields()` now has no run-time caller. What is left is the CodeRunner 5 form (`edit_coderunner_form.php:1788`, M7), the migration verifier, which calls it deliberately as its "before" side, and the test helper.

Whether this milestone also pins attempts to prototype versions is an open decision; see section 6. If it does, it additionally carries the `_prototypeversions` qt_var, the regrade detection and refresh of section 2, and the guard unit test including the `start_based_on` case.

**Exit:** the existing PHPUnit suite's **assertions** pass unchanged — its *fixtures* must change, because `tests/helper.php:1356-1393` builds question objects by direct assignment to about 25 of the 39 names and would otherwise shadow `__get` in every test, hiding the resolver from the very suite meant to exercise it — plus a test that `apply_attempt_state()` reads no prototype table (decision 16), a test that a null-prototype question presents as broken, and a before-and-after measurement of exam-start database cost against 5.x (decision 15). The question form is still the old one, deliberately. This is the highest-risk milestone and every form change stays out of it.

### M4a. The upgrade, end to end

Short, and the thing that makes a real course testable. Everything the migration needs exists — the question type's upgrade step adds `prototypeid` and `overrides` at version `2026110600`, and the manager's `install.php` queues the task that fills them — but the *delivery* has never been exercised. On the development site the prototypes were installed by running the task directly, not by the path a real site takes.

Three faults stand in the way, all found by looking at the site rather than the code:

1. **Cron is not processing adhoc tasks there.** Nineteen copies of `qtype_coderunner_setup_question_prototypes` are queued and unrun, the oldest from 6 April 2026. So the queue-on-install, run-on-cron mechanism the whole migration depends on has never actually been observed working.
2. **`update_question_types()` was called unconditionally** — at the end of `db/upgrade.php`, outside every version guard — so every plugin upgrade queued another copy of that task. That is where the nineteen came from. It cannot simply be deleted: the CodeRunner 5 form resolves prototypes by name against the prototype *questions* it installs, so it has to survive until M7 replaces the form. **Fixed** by passing `$checkforexisting` to `queue_adhoc_task()`, so a duplicate is not queued while one is still pending.
3. **The manager has no re-queue path.** `install.php` queues the migration task only on a fresh install, so a site whose task was lost, or which installed the manager before the task existed, has no route back. An upgrade step that queues it when the prototype tables are empty gives one.

**Adding a column to `extra_question_fields()` requires a version bump, and this bit for real.** The `core/questiondata` application cache holds the options object, built from whatever that method listed at the time. `questiontypebase::initialise_question_instance()` then reads *every* field in the current list off it (`question/type/questionbase.php:1027` region), unconditionally. So a cache entry written before a field was added makes every subsequent question load warn `Undefined property: stdClass::$overrides`.

Only an upgrade purges that cache — `purge_all_caches()` runs at the end of `upgrade_plugins()` (`lib/upgradelib.php:1871` and nearby). Adding the two columns to the list without bumping `$plugin->version` therefore left a site whose schema was already current, so no upgrade ran, so the stale entries survived. The release is not exposed to this, because the schema step and the field-list change ship at one version and a 5.x site gets a single purge on the way through; but every development change to that list needs its own bump, and `admin/cli/purge_caches.php` is the manual remedy.

`$plugin->release` is set to `'6.0.0-dev'`. It had been left at `'5.10.9'`, which matters beyond tidiness: core compares release strings through `backup_release_compare()`, so a 6.0 backup claiming to be 5.10.9 would be mis-ordered against a real one.

**Done, and exercised on the development site's own 12,751 questions.** The fault above is fixed by `prototype_installer::queue_migration_if_needed()`, called from both the install step and every upgrade, which queues the task when the prototype tables are empty *or* a question still has a null `prototypeid` — a conversion that stopped part way, or a question restored from a pre-6.0 backup. Four tests cover the decision, because it is silent when wrong in either direction: too eager and every upgrade queues pointless work, too shy and a broken site stays broken.

Running the upgrade for real confirmed the whole chain, including both negative cases:

- the two plugin steps ran, and `moodle_needs_upgrading()` went false — which matters more than it sounds, because `\core\cron::run_main_process()` refuses to run *anything* while an upgrade is pending (`lib/classes/cron.php:70`), so a half-finished upgrade silently stops cron altogether;
- exactly **one** prototype-setup task was queued, not another nineteen, so the duplicate check holds;
- **no** migration task was queued, the recovery check correctly finding nothing to recover;
- cron then ran the queued task and drained the queue.

A real question loaded through the question bank resolves a three-layer chain, a 55,000-character inherited template, twelve support files merged along the chain, and — the test that matters for the correction recorded under M3 — a question declaring no UI parameters of its own receives all four of its prototype's, `wrapper_src` included, with the right UI plugin.

**Exit, met: a pre-6.0 clone upgrades to exactly the state the live site is in.** The development database was cloned, the two new columns and their index dropped, the manager's tables and its version rows removed, and the question type's version rolled back to `2026091501`. That clone then upgraded, ran cron, and migrated:

- the question type's `2026110600` step added the columns to 13,213 rows in **2.12 seconds**, so the step was exercised rather than skipped;
- the manager installed from scratch and its install step queued the migration task;
- cron ran it: **19 built-in prototypes created, 53 user prototypes created, 4 skipped** as same-name duplicates with the reason given, then **12,346 questions converted and 405 left broken** — the same counts as the live site, and the same prototype names behind the broken ones;
- comparing the two databases row by row, **not one of the 13,213 questions has a different overrides document**, none resolved in one and not the other, and both carry the collected fields on the same 18 and 38 prototypes.

So a migration run from scratch reproduces a migration run incrementally, which is the property the whole additive design rests on.

**One trap worth knowing for anyone repeating this.** Rolling back the versions recorded in `config_plugins` is *not* enough to make Moodle notice: `moodle_needs_upgrading()` compares `$CFG->allversionshash` against a hash of the **code's** `version.php` files (`lib/moodlelib.php:7797-7827`), and returns early without looking at the database at all. With the code unchanged the hash matches and the upgrade is declared unnecessary. The row has to be deleted from `config` as well. A real upgrade is unaffected, because there the code does change — this is purely an artefact of simulating one, and the reason the first attempt reported "No upgrade needed. Thanks for coming anyway!".

**Exit:** a clone of the development site, restored from a pre-6.0 database, upgrades through the plugin-check page with cron running normally, the migration task runs itself, the verification script reports zero differences, and a question in a real course renders and grades. That is the first point at which an author can be let near it.

### M5. Backup, restore, import and export

**Moved to last of the 6.0 work (decision 19).** Much of what made this urgent is already handled by M4's boundary code: Moodle XML import resolves a question's prototype by name through `set_prototype_reference()`, export carries no local id, and restore resolves by name too. What remains genuinely outstanding is a course backup carrying the prototypes themselves, and id- and uuid-based matching for the cases a name cannot settle.

**The identity hash has never included inherited values, which narrows this milestone.** The hash is built from `get_question_options()` output on the database side, but the caller sets `export_process = true` first (`backup/util/dbops/restore_dbops.class.php:728-730`), and CodeRunner skips `set_inherited_fields()` on exactly that flag (`questiontype.php:452`). So both sides of the comparison have always used the question's own raw columns, and the existing exclusion list already covers the two things `get_question_options()` adds, `customise` and `prototype`. The identity-hash work here is therefore confined to the new columns; see open question 17.

**The question backup needs a portable prototype identity, not just `prototypeid`.** The case that decides the format is a quiz backup from course A restored into course B on another site, where the prototype was never in the backup because it lives in course A's bank or at site level. There is no id mapping to look up, so the question element carries the prototype's **uuid** as well as its id: the id resolves a same-site restore directly, and the uuid finds an existing copy on another site through `find_by_uuid()`. The name is not carried as a new element, because `coderunnertype` already holds it for as long as it exists (decision 18).

The uuid is not a column of `question_coderunner_options`, so it cannot travel through `extra_question_fields()` the way `prototypeid` does. It is added by changing the options element from `set_source_table()` (`backup_qtype_coderunner_plugin.class.php:63`) to `set_source_sql()` with a left join to the prototypes table, which is a change to the backup format rather than to the column list. Because it is computed at backup time it is current by construction, which `coderunnertype` is not.

`backup_local_coderunner_manager_plugin` with the course and question structures and write-once recording through `backup_ids_temp`; `restore_local_coderunner_manager_plugin` with both structure methods and linking in `after_restore_question()` per section 1.3; identity-hash exclusions and legacy conversion in `convert_backup_to_questiondata()`; then Moodle XML reference-only, include-prototypes and flatten, and legacy prototype-question import. If attempts are pinned, this also carries the `recode_response()` extension of section 1.4 and backup of each prototype's whole version history.

**Exit:** one test per scenario: same-site course restore, cross-site course restore, quiz duplication, course copy, course import, pre-6.0 legacy backup restore, and a quiz borrowing from another course's shared bank.

### M6. Prototype editor UI

The registry-driven shared editor component, the library view, child-prototype creation, documentation, history with diff and restore, draft and publish, and the usage report extending `scripts/prototypeusage.php`. The push to attempts in progress is not built, because decision 11 does not pin attempts.

**Built.** In order: field labels; the library view with dependant counts; the shared `settings_form` component; the editor and its form; Ace on the code fields; splitting a built-in on first edit (decision 23); change history with a per-version diff and restore; documentation through Moodle's editor; support-file editing; the usage report; and draft and publish.

Several things were decided while building and are worth keeping:

- **The override control needs no JavaScript.** Each field gets its standard element, an `advcheckbox` beside it, and `disabledIf`, so core greys the input while the box is clear. Decision 21 cost nothing and saved a component.
- **An overridden field says what it would fall back to.** Clearing the override greys the control but leaves it showing the value being discarded, because nothing redraws; the note beneath names the inherited value instead, so the choice is never blind. Shown only when the field is overridden, since otherwise the control already shows it.
- **A restore writes a new version** rather than rewinding, so the history stays complete and the restore is itself in it. A draft, by contrast, is deleted when discarded: an abandoned draft is not history, and keeping it would make the version numbers jump for no reason an author could explain.
- **A draft is a version nothing resolves against.** The prototype keeps pointing at the published version, so an author can work on a prototype thousands of questions depend on without those questions moving under them. Saving a second draft replaces the first.
- **The usage report counts in SQL and maps contexts in PHP.** Grouping by question bank context rather than walking to the course in SQL keeps the query portable; there are only as many contexts as banks however many questions they hold. Site defaults, with all 72 descendants, reports in 234 ms.
- **Two Moodle traps**, recorded so they are not rediscovered: `MoodleQuickForm::getElementValue()` and `toHtml()` both fail under PHP 8.3 through PEAR code that calls a non-static method statically, so a form cannot be rendered or read that way in a test; and the `FORMAT_` constants are defined as *strings* while the columns holding them are integers.

**Not built, and deliberately:** the bulk tester is not run before publishing. The design asks for it, but the bulk tester lives in the question type and design section 5 anticipates moving it into the manager later; wiring the two together now would be work done twice. The publish banner shows the number of questions a publish would affect, which is the part of that feature that informs the decision.

### M7. New question form

The prototype picker with its grouping, and the capability check on save as well as in the picker; registry-driven sections with inherited and overridden states, revert, and change counts; test case field switches; *Make prototype from this question*; the legacy prototype-question banner; removal of the *Customise* checkbox.

**Exit:** besides Behat coverage of override, revert and prototype-change, an automated test enumerating the registry against `extra_question_fields_static()` and the old form's elements, so the notes' "the field set comes from the current code" rule is enforced rather than remembered.

**The increments.** The exit criterion comes first, because it is the checklist the rest of the milestone works from rather than an audit at the end of it.

1. **The control inventory.** Done, as `tests/v6/form_control_inventory_test.php`. Every one of the **85** named controls of `edit_coderunner_form.php` is recorded with what accounts for it in CodeRunner 6 — a registry field, a value of the question itself, CodeRunner 5 layout that the registry's sections replace, or a deliberate removal with its reason — and four checks hold the record and the code to each other in both directions: nothing the form defines may go unrecorded, and no registry field may exist that neither a CodeRunner 5 control nor a short declared-new list accounts for. The thirty-nine column-backed registry fields map one to one onto the thirty-nine fields the old form offered; the five that do not are `supportfiles`, which the old form calls `datafiles`, and the four test case field switches, which are new.
2. **The save path and the form's two sides.** `prototypeid` and `overrides` come from the form rather than being derived from the legacy columns, which are written from them instead. The inherited side is `chain_for($prototypeid)` with no question overrides; the question's own side is its `overrides` column. Exit: a question saved through the new path verifies clean.
3. **The prototype picker.** The manager API for the three groups, the used-in-this-course query of the notes, tree order with depth indentation in an `autocomplete`, the selected chain as a breadcrumb with its summary, *From another course…*, and the capability check in the picker and again on save.
4. **The form.** Registry-driven sections with change counts in place of the *Customise* and *Advanced customisation* panels, the question-specific controls and all of CodeRunner 5's validation kept, and the test case field switches deciding which test case columns appear.
5. ***Make prototype from this question***, the legacy prototype-question banner, and M7's share of the removal inventory below.
6. **Behat** over override, revert and prototype-change.

**One correction to the notes, found in building the inventory.** The notes say template debugging "is a per-session author option, not a stored setting, so it probably stays outside the registry". It is neither per-session nor outside the registry: the control labelled *Template debugging* is `showsource`, a column of `question_coderunner_options` and an instance field of the registry since M1. Nothing has to be done about it, which is the useful half of the finding; the notes are corrected so that nobody builds the per-session option it describes.

**`badquestionload` is dead in CodeRunner 5.** The form adds it as a hidden field and sets its type, nothing on the server ever gives it a value, and the only JavaScript that touches it hides it (`amd/src/authorform.js:593`). So the message it was to carry has never been shown to anyone, and it is recorded as a removal rather than as something to reproduce.

### M8. Site defaults and tidying

The `before_course_deleted` handler; the cleanup script for legacy prototype questions; the prune script for prototype history. Documentation.

**The removal inventory.** The CodeRunner 5 prototype machinery comes out in pieces, as each piece's last caller goes, which risks a remnant surviving because no single milestone owns it. So it is listed here, and M8 is not done while anything on the list is still present.

| To remove | Last caller goes in |
| --- | --- |
| `qtype_coderunner::get_prototype()` | the form and `ajax.php` in M7, `export_to_xml()` in M5, the verifier in phase 4 |
| `qtype_coderunner::get_all_prototypes()` | the form's type menu in M7, `scripts/prototypeusage.php` in M6 |
| `qtype_coderunner::prototype_contexts()` | with `get_prototype()`, except the health report, which is 5.x code |
| `qtype_coderunner::set_inherited_fields()` | already off the run-time path; the form in M7, the verifier in phase 4 |
| `qtype_coderunner_question::get_prototype()` and `$this->prototype` | the no-chain fallbacks in `question.php`, with the form in M7 |
| `$options->customise` | computed only for the CodeRunner 5 form's panel; M7 |
| `/prototype` identity-hash exclusion, and the restorer's `unset($questiondata->prototype)` | already defending against something that no longer happens; M5 |
| `update_question_types()` and `qtype_coderunner_setup_question_prototypes` | the form resolves by name against the prototype questions these install; M7 |
| `legacy_converter`, `migration_verifier`, the legacy columns of `field_registry::legacy_columns()` | phase 4, with the migration itself |

### M9. Revisit the built-in prototypes

Numbered M9 because that is the next free number; the suggestion called it M10.

The built-ins have not been meaningfully revised in about a decade, and CodeRunner 6 changes what they ought to be. Three things to do, of which only the first is small.

**They have no one-line summaries.** All 19 carry documentation, inherited from the question text of the prototype questions they were converted from, and **not one has a summary**. The summary is what the picker shows beside a prototype and what the library lists in its last column, so without it both are bare names and an author choosing between `python3_scratchpad` and `python3_scratchpad_w_files` has nothing to go on. Writing nineteen sentences is a morning's work, and it is content only the maintainer can write.

**The set itself wants rethinking, and will grow.** Much of what template parameters do today should become separate prototypes: writing a function and writing a program as two question types rather than one type with an `isfunction` parameter, matplotlib variants of their own, and so on. That is what cheap child prototypes are for, and it is why the picker was specified around *Used in this course* rather than around a list short enough to read (design section 7). Expect the library to end up two or three times its present size.

**Parameter definitions become worth having at the same time.** Design section 6 defers them to 6.x: a prototype declaring its template parameters by name, type and default, so the question form can offer labelled controls instead of a JSON box. A pass over the built-ins is the natural moment, because deciding which parameters survive the split into separate prototypes is the same exercise as deciding which deserve a declared definition.

This also settles open question 24, the format built-ins are defined in: a pass that rewrites them has to decide what it is rewriting them into, and by then M7 will have removed the CodeRunner 5 importer that is the only reason `db/builtin_PROTOTYPES.xml` is a question export.

**Not a blocker for 6.0.** Every built-in works; they are simply undescribed and shaped by a model that no longer applies.

## 5. Where each piece should live

| Piece | Plugin | Why |
| --- | --- | --- |
| Field registry | qtype | the fields are the columns of `question_coderunner_options` and the controls of the question form |
| Resolver and provenance | qtype | the run-time path, and the fields it resolves are the qtype's own columns |
| Legacy reader, name lookup, converter | qtype | reads the qtype's table and replicates its historical quirks (section 3.1); migration and legacy import/restore only, never the run-time path |
| Verification CLI script | qtype | reads the qtype's own old columns and new columns to compare them |
| Shared registry-driven form component | qtype | keeps the question form's dependencies local |
| Question form and picker rendering | qtype | |
| `prototypeid` and `overrides` columns | qtype | columns of the qtype's own table |
| Resolved-settings cache (deferred, decision 15) | qtype | behind the resolver on the question-load path, if measurement ever justifies it |
| `_prototypeversions` qt_var and pinning, if adopted | qtype | question-attempt state |
| `recode_response()` for `_prototypeversions`, if adopted | qtype | core calls only the qtype restorer there (section 1.4) |
| Prototype tables and version history | manager | the course-level backup point is the whole reason it exists |
| Prototype storage API, name uniqueness, cycle and depth guards | manager | |
| Prototype support files | manager | the file component must match the plugin owning the item id |
| Prototype backup and restore, both attachment points, linking | manager | |
| Prototype snapshot and the question write pass | manager install step | runs after the qtype has added its columns |
| Library, editor, history and diff, draft and publish, usage report | manager | |
| Cross-course prototype query and scope logic | manager API, called by the qtype form | |
| Site defaults prototype, built-in prototype installation | manager | they are prototypes |
| `before_course_deleted` handler | manager | |
| Prototype health report | qtype, in the 5.x line (M0) | needs only today's name lookup |
| Legacy prototype-question import | qtype's `import_from_xml()`, calling the manager's API | the qtype owns the file format |

This differs from design section 6, which puts the shared editor component in the manager. The argument for inverting it is decision 1: the question type defines the schema of the questions it runs, so the registry, resolver and converter belong to it, and the form component follows them rather than being split off alone.

**Dependency direction.** `qtype_coderunner` declares `$plugin->dependencies = ['local_coderunner_manager' => ANY_VERSION]`. One direction only; declaring it both ways would be a cycle. This makes Moodle refuse to begin the upgrade, with a named failure on the plugin-check page, if an administrator pulls the question type without the manager (section 3.7). That is the enforcement mechanism, in place of upgrade notes that nobody reads.

What it does **not** do is make the manager install or upgrade first. Dependencies gate whether the upgrade may start; the fixed plugin-type order decides what runs when (section 3.4). The split at the head of section 4 therefore stands, and is what schema ownership would require in any case.

## 6. Decisions

Taken:

1. **Registry, resolver and shared form component in the question type.** Settled, but none of the three arguments in the first draft is the real one. "Questions must resolve without the manager" is void: the question type declares the manager as a dependency, so it cannot even install without it. "M1 ships in 5.x" is void under decision 4. A third argument, that the upgrade gate had to run inside the question type's step, is void too: section 3.9 removed the gate. What stands on its own is that the question type defines the schema of the questions it runs — the registry describes the columns of `question_coderunner_options` and the controls of its own edit form.

   **Corollary, decided with it: one resolution path, not two.** No legacy resolver is shipped alongside the new one. See M4.
2. **Comparison strictness: normalise per registry field type**, matching today's loose comparison, with type-only differences reported as a separate count so the scale of the effect is visible.

   The normalisation must be **type-aware**, and the rule has three cases rather than two. A sampled comparison of real questions against the M2 snapshot found each of them:

   | Type | Is null the same as empty? | Why |
   | --- | --- | --- |
   | `BOOLEAN` | yes, null ≡ 0 | `allowmultiplestdins` is read only in a boolean `\|\|` (`classes/jobrunner.php:134`) |
   | `CHAR`, `TEXT`, `JSON` | yes, null ≡ `''` | `acelang` is read through `empty()` (`renderer.php:129`) and `resultcolumns` through a truthiness test (`question.php:1043`); CodeRunner 5's own `set_inherited_fields()` likewise treats `''` as not set |
   | `INTEGER` | **no** | `get_sandbox_params()` tests `isset($this->cputimelimitsecs)` (`question.php:1303`), so null is "no limit" and 0 is "a limit of zero" |

   Getting any of the three wrong is a real error in one direction or the other: a blanket string comparison invents differences for booleans and strings, and a blanket null-to-zero rule hides a real one for integers. The rule lives in `field::normalise_for_comparison()` so that there is one implementation rather than one per comparison. With it, 900 of 900 sampled questions resolved identically through the snapshot and through `get_prototype()`.
3. **Support files are not compared per question**; the merge-by-filename rule is covered by unit tests only. The comparison's scope and limits are spelled out under M1.
4. **Nothing but the health report (M0) ships in the 5.x line.** The comparison script ships with 6.0 and can still be run before the upgrade is triggered, because a plugin CLI script runs against an un-upgraded database (section 3.8). That keeps the pre-upgrade check without carrying a frozen copy of an evolving registry on the stable branch.
5. **The plugin is `local_coderunner_manager`** — general enough for the bulk tester, question browser, attempt downloads, analytics and cache purge to move into it later, as design section 5 anticipates. The name is valid and imposes no table-naming constraints (section 3.8).
6. **Keep `qtype/coderunner:management`**; do not add a manager capability in 6.0. A local plugin can check a capability declared by another component without restriction, and the dependency declaration prevents the question type being uninstalled from under it, so the coupling concern raised in the first draft does not hold. Adding `local_coderunner_manager:manage` later is a backward-compatible addition. One detail: the capability is declared at `CONTEXT_COURSE` (`db/access.php:43-51`), which governs where it can be overridden, not where it can be tested; site prototypes should be gated on a system-context check or `is_siteadmin()`, as design section 8 intends.
8. **The migration runs from an adhoc task**, not from any upgrade or install step, and is not gated. The question type's upgrade step adds its two columns; the manager's install step creates its tables and queues the task; the task guards on `mod_qbank`'s category transfer; verification is a separate CLI run afterwards. Section 3.9 is why: the question banks the migration must search do not exist when the question type upgrades, and no SQL recovers data that has not been created yet. The earlier plan to gate the upgrade on a full comparison is abandoned — it could not run where it needed to, and as a post-migration check the same comparison is stronger anyway. No upgrade notes are relied on for the two-plugin requirement; the dependency declaration is that mechanism.

   **Accepted consequence:** between the upgrade finishing and cron running the task, questions do not resolve. The window is cron latency, not migration size, so it is the same on a small site as a large one. This is already how CodeRunner behaves — the built-in prototypes have not existed until the post-upgrade task ran since 4.6 — so the instruction "run cron before using the site" is not new.

9. **`twigall` is inherited**, not instance-only. `uiparameters` is itself twigable, and under CodeRunner 6 it is collected from every layer, so each layer's contribution must be Twig expanded before it can be parsed as JSON and merged. That needs a `twigall` value available while the chain is being resolved, not only at the question. The decision is also a strict improvement rather than a change: today `twig_all()` expands only the question's own `uiparameters` and never the prototype's (`question/type/coderunner/question.php:542`), and `qtype_coderunner_ui_parameters::merge_json()` silently skips anything that is not valid JSON, so a prototype with Twig in its UI parameters currently contributes nothing at all, silently. That silent skip is a candidate for the same fail-noisily treatment as decision 10.

10. **The legacy path that lets a template parameter act as a UI parameter is removed**, and a question relying on it fails noisily rather than silently losing the parameter. The M0 health report lists affected questions; on the development site that is one question, `directed_graph` setting `isfsm` and `isdirected`.

11. **Attempts are not pinned to prototype versions in 6.0.** Editing a prototype therefore reaches attempts in progress and finished attempts, which is exactly today's behaviour, so nobody can report a regression. Pinning can be added in a later release without a backfill: the rule for an attempt with no recorded versions changes from "treat as version 1" to "resolve against the current versions", so 6.0-era attempts keep behaving as they did and only new attempts pin. The one cost is that 6.0 backups carry each prototype's current version rather than its whole history, so a later release must extend the backup format and handle restoring a 6.0 backup that lacks it — one versioned format change, and the format is already versioned through `backup_release_compare()`. This removes from 6.0: the `_prototypeversions` qt_var and the regrade detection it rests on (section 2), the `recode_response()` rewriting in M5, the push-to-attempts-in-progress feature in M6, and the prune script's scan of attempt step data in M8.

12. **The manager is `local_coderunner_manager`**, in both its component name and its table prefix, so the two agree. The name is long but new users will mostly meet it only as a dependency of the question type. One-way dependency only: the question type declares the manager, the manager declares nothing. The manager installed alone is inert, since nothing can call it, so a mutual declaration would buy nothing. Note that the declaration does not make the manager install first — it installs last (section 3.4), which is why the built-in prototypes are installed by the manager's adhoc task rather than by either install step.

13. **Privacy follows core's question provider.** The two userid columns on each table are declared as metadata and exported, and on user deletion they are anonymised to 0 rather than the prototypes being deleted. Core does exactly this for questions (`question/classes/privacy/provider.php:515`): a question outlives its author, and so does a prototype, which other courses may depend on.

14. **Where an administrator configures what.** A field in the registry is configured in the Site defaults prototype, through the prototype manager. Everything else stays on the question type's admin settings page. The boundary is exact rather than a matter of taste: of the fifteen settings in `settings.php`, fourteen are infrastructure — Jobe hosts and API keys, the grade cache, the sandbox enable flags, the web service limits — and none of them is a question field.

    The one exception is `qtype_coderunner/default_penalty_regime`, which today supplies a question field. It has exactly one functional use, at `edit_coderunner_form.php:551`, where it seeds the penalty regime of a new question. Design section 10 already says such defaults "move into the Site defaults prototype", and leaving both in place would be worse than either alone: an administrator editing the setting would not change Site defaults, so the two would disagree silently, with which one wins depending on which code path created the question.

    Resolved by removing the setting and resolving the new-question default from Site defaults instead, falling back to the registry default if the install task has not yet run. Keeping the two in step with a `set_updatedcallback` was considered and rejected: it synchronises only from the setting to Site defaults, so editing Site defaults would leave the settings page displaying a stale value, which is worse than no synchronisation because it looks consistent.

15. **The resolved-settings cache is deferred until it can be measured.** The notes' *Resolved settings cache* assumes a Moodle application cache keyed by the chain of version ids. 6.0 ships without one.

    `questiondata` is `MODE_APPLICATION` with a datasource (`lib/db/caches.php:85-91`), so it is shared across requests and users rather than being per-request. Resolution therefore happens once per question per cache lifetime, not once per attempt: an exam with 500 students on 30 questions resolves about 30 times. A three-to-five layer chain walk over two small indexed tables, thirty times, is not measurable against the cost of initialising 500 students' attempts, which is where the database pressure at exam start actually comes from.

    The one part of resolution that can go stale is the prototype-to-current-version map, and the manager purges that alongside the `questiondata` purge it must do anyway when a prototype is saved. So deferring the cache removes an invalidation path rather than creating one. Prototype saves are rare — days with none, and at most one every ten minutes while a prototype is being developed — so the existing purge-the-children mechanism is adequate and is kept, including the bulk-purge threshold added for the 400,000-question site.

    Nothing is foreclosed: the cache would sit behind the resolver and needs no data migration, so it can be added whenever a measurement asks for it. Decision 7's retained analysis predates this and refers to the cache as a given.

16. **No prototype or version table access on the per-attempt path.** This is the condition that makes decision 15 safe, and it is a requirement on M4 rather than an optimisation.

    `apply_attempt_state()` runs every time an attempt is loaded for rendering or grading, and calls `evaluate_question_for_display()` and so `get_prototype()` (`question/type/coderunner/question.php:279-281`). In CodeRunner 5 that reaches the uncached five-table join with a correlated `MAX(version)` subquery at `questiontype.php:576`. Measured warm on the development site for `python3_scratchpad`, which has 8,304 children, across all contexts: 5.5 ms. At exam start that is 500 executions per question, in the window where the database is already under the most pressure.

    So `get_question_options()` must produce not only the resolved single-value fields but everything the collected fields need — each layer's template parameters with that layer's language and per-try flag, each layer's UI parameters, and the support-file chain — and put them in `$options`, where `questiondata` holds them. The per-attempt path then does no prototype database access at all, and 6.0 is cheaper than 5.x on the one path that runs once per student per page.

    Getting this wrong inverts it: resolving on the per-attempt path would replace one join with three to five queries, 500 times, making exam start worse and then needing a cache to hide it.

    Enforced by a test that counts database reads across `apply_attempt_state()` and asserts that none touches `local_coderunner_manager_prototypes` or `local_coderunner_manager_versions`, rather than by review, so that a lookup added later fails loudly.

    **Consequence for what goes in `$options`.** It is serialised into the `questiondata` store, so the per-layer data is stored as plain arrays and the `question_settings` object is rebuilt from them per attempt, without database access. A serialised object whose class shape changes across a plugin upgrade is a failure mode worth not having.

17. **`prototypeid` and `overrides` go into `extra_question_fields()`.** They are absent today; M3 added the columns only. `get_question_options()` now has to read both, and `parent::get_question_options()` loads exactly the columns that method names, so they belong in the list by the same ownership argument as decision 1. There is no counter-argument worth the name: the alternative is custom load-and-save code in the question type, written only to be deleted. An earlier draft of this document put the choice as an open question, which overstated it.

    What the choice does carry is two consequences, both of which are real work.

    **The identity hash.** `/options/prototypeid` joins `define_excluded_identity_hash_fields()` beside the existing `/options/testcases/questionid`; both are local ids that differ between sites and would otherwise make an identical question fail to match. `overrides` is content rather than an id, so it stays in the hash.

    **Restore must not carry the raw id.** Adding a field to `extra_question_fields()` also adds it to the backup, because the backup element list is built from that method (`backup_qtype_coderunner_plugin.class.php:49-55`). That is a trap in M4 rather than a benefit, because **Moodle's id remapping is explicit, not automatic.** A reference is declared on the backup side with `backup_nested_element::annotate_ids()` and resolved on the restore side with `get_mappingid()`, which reads `backup_ids_temp` by restore id and item name (`restore_structure_step::get_mapping()`, `backup/util/plan/restore_structure_step.class.php:218`). Nothing inspects column names or types. CodeRunner's own restorer shows it plainly: `process_coderunner_options()` inserts `$data` from the XML wholesale and remaps exactly one field, `questionid`, through `get_new_parentid('question')` (`restore_qtype_coderunner_plugin.class.php:113,142`).

    An unmapped `prototypeid` would therefore survive a same-site course copy or quiz duplication *by accident*, and on a cross-site restore would point at whatever prototype happens to hold that id on the target site: wrong, but plausible-looking, where a null is visibly broken. The accidental same-site success is what makes it dangerous, since that is the case a developer tests first.

    M4 could not do the real mapping even if it tried: the mapping entry a question would look up is created by the manager's restore step, which is M5, and until that exists `get_mappingid()` returns false for every question. So M4's restorer nulls `prototypeid` as a deliberate one-line stub, and M5 replaces it with real linking. A restored question is then broken and says so, which is what the design already specifies for an unresolvable question.

    Because `get_mapping()` is keyed only by restore id and item name, a mapping set by one plugin's restore step is readable by another's, which is what makes the M5 plan — the manager's step maps the prototypes, the question type links in `after_restore_question()` (section 1.3) — work at all.

**Three identifiers, one authority.** A prototype is named three ways in 6.0, which looks like redundancy and is not: each is authoritative over a different scope, and exactly one of them is read at run time.

| | Where it lives | Scope | Read by |
| --- | --- | --- | --- |
| `prototypeid` | `question_coderunner_options` | this site only | resolution, at run time — the only identifier on that path (decision 1's corollary) |
| `uuid` | `local_coderunner_manager_prototypes` | the prototype's lineage, anywhere it has been copied | restore and import matching, at boundaries only |
| `coderunnertype` | `question_coderunner_options` | a human-readable name, frozen at migration or save | the verification script's "before" side, the broken-question message, the restore name fallback, and a re-run of the migration |

This is the same three-way split core uses for questions themselves — `id` local, `stamp` portable and used for cross-site restore matching (`backup/util/dbops/restore_dbops.class.php:620,624`), `name` human — so it is the established pattern rather than a CodeRunner invention. Phase 4 drops `coderunnertype` and leaves the usual two.

**`coderunnertype` can be a frozen record or a live label, not both.** Frozen, it is a dependable migration input and verifier baseline, and its name may be out of date after a prototype is renamed. Live, it is a useful denormalised type label and M6's rename has to update every child question, which reintroduces the bulk-update problem that renaming a prototype causes in CodeRunner 5. 6.0 takes frozen, for the verifier. If the label is wanted for its own sake beyond phase 4, that is the point to switch it to live and accept the rename cost.

18. **`coderunnertype` is not replaced by `prototypeid` in 6.0.** The two look redundant — `coderunnertype` is the prototype's identifier in CodeRunner 5, and `field_registry::legacy_columns()` already records that it is superseded — but it retires in phase 4 with the other legacy columns, not now. Three reasons, strongest first.

    **`prototypeid` is not a portable identity and `coderunnertype` is.** The hard restore case is not the course backup that contains the prototype; it is the quiz backup from course A restored into course B on another site, where the prototype lives in course A's bank or at site level and was never in the backup at all. There is no mapping to look up, so the question can only be linked by a durable identifier. Replacing a portable identifier with a non-portable one would be a step backwards.

    `coderunnertype` carries the prototype's **name**, which is the only thing the question has to go on when its chain cannot be resolved at all — which is exactly when no chain is available to read a name from. CodeRunner 5 already works this way: `missingprototypewhenrunning` is given `['crtype' => $question->coderunnertype]` (`classes/jobrunner.php:88`). So no new name element is needed in 6.0; one becomes necessary only in phase 4, when `coderunnertype` goes.

    A name is not a sufficient identity on its own, which is why M5 adds the prototype's **uuid** to the backup alongside it. Prototype names are unique only per `(courseid, name)` plus the site set, so two courses may each have a `python3_mine`, and a name lookup scoped to the target course and site can legitimately match a *different* prototype of the same name — the CodeRunner 5 ambiguity that CodeRunner 6 exists to remove, reintroduced at the restore boundary. The uuid identifies the same lineage wherever it has been copied, which is what `find_by_uuid()` is for. Restore therefore tries the id, then the uuid, then the name with CodeRunner 5's semantics, then reports the question broken.

    **`coderunnertype` is a frozen snapshot, not a live name.** Nothing updates it when a prototype is renamed in the manager, and decision 18's third reason wants it frozen, since it is the verification script's "before" side. So it is a reliable migration input and an unreliable current name, and the uuid in the backup is computed fresh at backup time rather than being derived from it.

    **Backward portability across the version boundary, and it costs nothing.** `insert_record()` silently drops keys that are not columns of the table (`lib/dml/mysqli_native_moodle_database.php:1487-1489`). So a 6.0 backup restored into a 5.x site has `prototypeid` and `overrides` dropped on the way in and still carries `coderunnertype`, so 5.x's name lookup finds the prototype and the question works. Stop writing `coderunnertype` and that restore yields a question with no prototype identity at all. Restoring a newer backup into an older site is not officially supported, but CodeRunner courses do move between sites running different versions.

    **It is what makes the migration re-runnable.** Decision 8's accepted consequence and design section 10 both rest on correcting the converter and re-running from retained data. `coderunnertype` is the input to the name lookup, and it is the "before" side of the verification script. Stop writing it and both are gone.

    They are also not strictly redundant, because they can disagree, and the disagreement is load-bearing: a question with a null `prototypeid` and a `coderunnertype` of `python3` is a migration failure, and that is exactly what the verifier and the health report read. Collapsing them into one field would remove the ability to detect it.

19. **The authoring UIs come before backup and restore, and an upgrade milestone comes before both.** The original order put M5 before M6 and M7 on the principle of finishing the data layer first. Nothing in M6 or M7 depends on M5: the editor needs M1 and M2, the question form needs M1, M2 and M4's resolution, and neither reads anything M5 produces. The order was tidiness, not a constraint.

    What the order has to serve instead is the first useful milestone for the person building the thing: being able to author and run CodeRunner 6 questions in a real course. That needs M4, a working upgrade, and the two UIs — and not backup. So: finish M4, then **M4a** the upgrade end to end, then M7, then M6, then M5.

    This reorders work and reduces nothing. M5 must still land before 6.0 ships, because until it does a course backup carries no prototypes at all, and restoring such a backup into a site that does not already have them by name leaves questions visibly broken.

    The risk accepted in the meantime is bounded and visible rather than silent: between now and M5, a restore or duplication resolves a question's prototype by name, which succeeds whenever the target scope has one of that name and otherwise leaves the question reporting itself broken.

20. **Template parameters are cached in one combined qt_var pair, not one pair per layer.** `question_attempt_step_data.name` is `char(32)`, and CodeRunner 5's own `_prototype__template_params_json` is exactly 32 characters, so there is no room for a per-layer suffix; keying by version id would fit only while ids stay short. One signature over every layer's raw parameters and language, plus the chain's layer labels, detects any change that can alter the merged result, including a layer inserted that contributes nothing itself.

    The cost is that a change anywhere re-evaluates every layer rather than only the changed one. That is negligible for Twig, and for a layer using a Jobe language it is one run, only when something actually changed. It also upgrades itself: an in-flight attempt's old keys simply miss, and the next page load re-evaluates and stores under the new one.

21. **The forms are built from standard Moodle form elements and Boost styling, and departures are argued for in writing.** This had not been recorded anywhere, in any of the three documents, which is how the prototype picker nearly acquired a custom combobox before anyone asked whether it needed one.

    In practice: controls are `MoodleQuickForm` elements added through `moodleform`, not hand-written `<input>` markup; markup comes from Mustache templates rendered through the renderer, not from strings concatenated in PHP; styling uses Boost's existing classes in preference to new CSS; behaviour goes in AMD modules built with grunt; and all text goes through `get_string()`. Where a standard element will not do, the milestone says so and says why, rather than reaching for custom code as the default.

    The reason is not uniformity for its own sake. A standard element arrives with keyboard handling, ARIA attributes, screen-reader labelling, right-to-left support, theme compatibility and translation already done and already maintained. Rebuilding any of that is work that has to be redone on every Moodle release, and CodeRunner has had accessibility fixes contributed for exactly these reasons.

    **The known exception, so that it is argued once rather than rediscovered.** There is no core element for "a field that is either inherited or overridden, with a control to revert it". That affordance has to be built. It is built as a *thin wrapper* around standard elements — the input for each field stays whatever `MoodleQuickForm` offers for its registry type, and the wrapper adds the provenance label, the revert control and the inherited styling around it. What is not acceptable is reimplementing the inputs themselves in order to decorate them.

    **Reverting a field to inherited drops the override immediately**, rather than keeping the value in case the author changes their mind. An overrides document that carries values which are not in effect is a second, invisible state, and the saving is only retyping; an author who wants to keep a value before reverting can copy it, and undo is undo.

22. **The manager uses the question type's Ace editor, and that coupling cannot be declared.** Template code, template parameters and the other JSON fields are unreadable in a plain textarea, and the question type already ships Ace together with the machinery to attach it. The manager uses both: `qtype_coderunner_util::load_ace()`, which registers the scripts once per page, and the `qtype_coderunner/userinterfacewrapper` AMD module's `newUiWrapper`. Moodle's AMD loader is global, so a module of one plugin loads on another's page with nothing special done.

    **The coupling runs the wrong way and cannot be written down.** Decision 12 has the question type declaring the manager and the manager declaring nothing, so that the manager installed alone is inert. Calling into the question type makes that false at run time, and the manager cannot declare the dependency back, because Moodle refuses a cycle in plugin dependencies. The coupling is therefore real, deliberate and invisible to the plugin system, which is why it is recorded here: `load_ace()` and `newUiWrapper` are API the question type does not get to break quietly, and the manager breaks if either moves.

    It is safe in practice only because the question type declares the manager, so the two are always installed together, and the manager's pages are reached from CodeRunner's own management page. It would not be safe for a plugin that could be installed on its own.

    **Two details worth keeping.** The route is `userinterfacewrapper`, not `textareas::setupAllTAs`: the latter selects `div.edit_code textarea`, while the question form puts the class on the textarea itself, so it is not what gives the author form its editor. And template parameters and UI parameters are attached with an *empty* language, as `authorform.js` does, because they may contain Twig and a JSON mode would report errors that are not there.

23. **An administrator customises a built-in by editing it, which splits it.** Built-ins are rewritten by `install_builtins()` on every upgrade, so an edit to one would be discarded. Rather than refusing the edit, the first save splits the prototype:

    1. A new prototype is created holding the stock content, flagged `builtin`, named `<name> (stock)`.
    2. The edited prototype **keeps its id**, loses its `builtin` flag, becomes a child of the stock one, and retains only the administrator's overrides.

    Every question still refers to the same id, so nothing is re-pointed and no migration runs. Later releases rewrite the stock prototype and the overrides continue to apply on top. The split is lazy: a site that customises nothing never grows a row.

    CodeRunner 5's equivalent is to clone the prototype question and rename it, dropping the `BUILT_IN_PROTOTYPE_` prefix, which requires every question wanting the customisation to be moved to the clone. The split needs no such move, because inheritance is what the new model is for.

    **Built-ins are matched by a uuid derived from their name, not by the name itself.** After a split the administrator's prototype holds the name, so an installer matching by name would find it and overwrite the customisation. The stock prototype must be findable whatever it is called.

    The uuid is **computed**, not stored in a file: a version 5 (name-based, SHA-1) UUID over a fixed namespace and the built-in's canonical name. `python3` therefore has the same uuid on every CodeRunner site, forever, and `db/builtin_PROTOTYPES.xml` needs no new element — which matters, because that file is still read by the CodeRunner 5 question importer, and a foreign element in a Moodle XML export is asking for trouble. Moodle's `core\uuid` generates version 4 only, so the algorithm is implemented in the manager.

    This also earns something for M5: the `uuid` column exists so that "copies of the same prototype elsewhere can be recognised", and a deterministic built-in uuid means a cross-site restore recognises built-ins automatically, with no mapping.

    **Falling behind upstream is accepted.** When a release changes a field an administrator has overridden, their value silently wins and they are not told the stock value moved. Built-in changes are rare enough — the development site's own built-ins have not been meaningfully revised in about a decade — that a notification mechanism is not worth building. The better answer is to discourage editing built-ins at all in favour of a site prototype built on one, which needs the reparenting tool below.

Open:

24. **A better format for defining built-in prototypes.** `db/builtin_PROTOTYPES.xml` is a Moodle XML *question* export, because built-ins used to be questions. In 6.0 they are not, and the file is read twice for different purposes: by the CodeRunner 5 importer to create the legacy prototype questions, and by the manager to create the real prototypes. Once M7 removes the first, the format should be something that describes a prototype rather than a question, possibly maintained through the manager itself. Not urgent, and not worth doing while both readers exist.

25. **A reparenting tool, to move every question from one prototype to another.** The advice to build a site prototype on a built-in rather than editing the built-in is only practical if questions can be moved onto it afterwards. The operation is a single `UPDATE` over `question_coderunner_options` plus a cache purge, but it needs a UI that shows what will move and a check that the target resolves compatibly. It would also give administrators a way out of a built-in they have already customised.

Decision 7, below, is retained for its analysis.

7. **~~Does 6.0 pin attempts to prototype versions?~~** Settled as decision 11: no.

   The original analysis is kept below because it records what deferring costs and what adding it later involves. The cost of pinning is spread across four milestones, not just M4, which is why deferring it saves more than its description suggests:

   | Milestone | Exists only because attempts are pinned |
   | --- | --- |
   | M4 | `_prototypeversions` written in `start_attempt()`; read in `apply_attempt_state()`; resolution against recorded rather than current versions; regrade detection and refresh on the undocumented convention of section 2; the guard unit test including `start_based_on`; the "missing value means version 1" rule for pre-6.0 attempts |
   | M5 | `recode_response()` rewriting prototype ids inside `_prototypeversions`; backup carrying each prototype's whole version history rather than just its current version; restore merging missing versions into an existing prototype's history |
   | M6 | the entire push-to-attempts-in-progress feature: the publish page's affected-attempts count and quiz chooser, the bulk `question_attempt_step_data` update, and the test that an attempt picks up the new version on its next action |
   | M8 | the prune script's scan of `question_attempt_step_data` for every version named in any `_prototypeversions` |

   What does **not** change either way: the versions table and change history, which design section 9 wants for viewing, comparing and restoring earlier versions regardless; and the resolved-settings cache, whose key is the chain of version ids in both cases, with the same never-goes-stale property.

   The behavioural cost of deferring is nil against today: without pinning, editing a prototype immediately affects in-progress and finished attempts, which is exactly current behaviour (design section 3), and reviewing an old attempt already shows the current prototype.

   The forward path is clean. The notes already establish that a missing `_prototypeversions` means the earliest version; in 6.1 the rule becomes "missing means resolve against current", so 6.0-era attempts keep behaving as they did and only new attempts pin. No backfill.

   The one real cost of deferring: 6.0's backup would carry only each prototype's current version, so 6.1 would have to extend the backup format to carry history and handle restoring a 6.0 backup that lacks it. That is one versioned format change, and the format is already versioned through `backup_release_compare()`.
