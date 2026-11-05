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
- **M1a has dissolved into M3.** Its "faithful reimplementation of `set_inherited_fields()`" is unnecessary while the CodeRunner 5 code is still present: the verifier calls `get_prototype()` and `set_inherited_fields()` directly, which cannot drift from the thing it is checking. What remained of M1a — the question converter and the verification script — is in M3.
- **Next is M4**, the run-time switch. Nothing reads `prototypeid` or `overrides` at run time yet.

**Development site state at that date**, which is transient and recorded only so a fresh session knows what it is looking at:

- Nothing is committed. 39 modified and 8 untracked files in the question type on `Version6`, of which 26 of the modifications are only the `@coversNothing` and `@group` conversion to PHPUnit attributes. `public/local/coderunner_manager/` is untracked in the Moodle repository and is not yet a repository of its own.
- `local_coderunner_manager` is installed and its tables are populated: 72 prototypes, 251 file records.
- `question_coderunner_options` has `prototypeid` and `overrides` added and filled for all 12,751 rows. Additive only; no pre-existing column was written.
- `qtype_coderunner` requires Moodle 5.0 and declares the manager as a dependency; `$plugin->release` is still `5.10.9` and wants deciding.
- `default_penalty_regime` is gone from the settings page; its config row is unset by the install task.
- `tests/fixtures/test-sandbox-config.php` points `jobe_host` at `localhost:4000`; it previously named the unresolvable Docker host `jobe`, which is why the whole suite appeared to fail.
- Use `~/.config/composer/vendor/bin/phpcs --standard=moodle` (3.13.6 with `moodlehq/moodle-cs`). The `moodle-local_codechecker` copy bundles PHP_CodeSniffer 3.5.8, which reports named arguments as goto labels and carried a high-severity CVE.
- Thirty-odd lint errors across the new code are all `enum` and `readonly class` blind spots in moodle-cs that Moodle core also trips; none is actionable.

Suites: 208 tests / 1370 assertions in `qtype_coderunner_testsuite`, 58 / 333 in `local_coderunner_manager_testsuite`.

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

Nine milestones. M0 is the only one that ships in the 5.x line. M1 to M5 are strictly sequential; M6 and M7 can proceed in parallel once M5 lands.

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

**Exit, met:** the migration is idempotent, re-running converts nothing; it changes no existing column, which a test asserts field by field; an unresolvable question is left with a null `prototypeid` and still gets its overrides document, so it is recorded as broken rather than silently acquiring someone else's prototype; and verification reports zero differences across every question on the development site.

### M4. Switch the run-time path

`get_question_options()` and `qtype_coderunner_question::get_prototype()` resolve from `prototypeid` and `overrides`, with the resolved-settings cache keyed by the chain of version ids.

**There is one resolution path, not two.** A question whose `prototypeid` is null is broken and reports itself as broken, which is what the design already specifies for questions the migration could not resolve ("stored without a prototype and stay broken", design section 10) and is how a missing prototype presents today. Name-based lookup survives only in the migration and in legacy import and restore (M5) — boundary code that runs once per question, never on the run-time path. The legacy resolver of M1a is scaffolding and is not shipped as an alternative.

**Reaching the resolved settings from existing code.** Roughly 42 of the resolved settings are public properties of `qtype_coderunner_question` today, read as `$question->template` on about 319 lines, most of them in `classes/jobrunner.php`, `renderer.php`, `edit_coderunner_form.php` and `questiontype.php`. In CodeRunner 6 those values live in a `question_settings` object, so the three options are to rewrite every call site, to keep the properties and copy them off the settings object at load time, or to delete the properties and delegate through `__get`.

M4 takes the third, staged towards the first:

- The 42 properties are removed and `__get` resolves the name through the registry, so an unknown name throws instead of evaluating to null as it does today. No call site changes.
- `@property-read` lines are **generated from the registry** into the class docblock, with a test asserting they match `field_registry::all()`. Without them `__get` costs IDE completion, type checking and static analysis; generating them means they cannot drift from the field list.
- `__get` gains a `debugging()` notice when convenient, call sites are converted to `$settings->get(...)` as each file is touched for other reasons, and `__get` is deleted when the count reaches zero. Completing that is V7 work.

The explicit form is the better end state. It is staged rather than done at once because those 319 sites sit almost entirely in code the prototype change does not otherwise touch, so rewriting them would put risk into the most stable part of the plugin during its largest release, and because a 319-site commit is not reviewable whereas file-by-file conversion is.

**Two prerequisites, not tidy-ups.** `__get` fires only for undefined properties, and `question.php` declares `#[AllowDynamicProperties]` while `twig_all()` assigns to those same field names. The first assignment therefore creates a real property that shadows `__get` for the rest of the request, silently and only for fields that were non-empty — six registry fields are exposed (`answer`, `answerpreload`, `globalextra`, `prototypeextra`, `penaltyregime`, `uiparameters`). So Twig expansion must return a new settings object instead of mutating in place, and `AllowDynamicProperties` must go. Neither is optional.

Whether this milestone also pins attempts to prototype versions is an open decision; see section 6. If it does, it additionally carries the `_prototypeversions` qt_var, the regrade detection and refresh of section 2, and the guard unit test including the `start_based_on` case.

**Exit:** the existing PHPUnit suite passes unchanged, plus new tests for cache keying and for a null-prototype question presenting as broken. The question form is still the old one, deliberately. This is the highest-risk milestone and every form change stays out of it.

### M5. Backup, restore, import and export

`backup_local_coderunner_manager_plugin` with the course and question structures and write-once recording through `backup_ids_temp`; `restore_local_coderunner_manager_plugin` with both structure methods and linking in `after_restore_question()` per section 1.3; identity-hash exclusions and legacy conversion in `convert_backup_to_questiondata()`; then Moodle XML reference-only, include-prototypes and flatten, and legacy prototype-question import. If attempts are pinned, this also carries the `recode_response()` extension of section 1.4 and backup of each prototype's whole version history.

**Exit:** one test per scenario: same-site course restore, cross-site course restore, quiz duplication, course copy, course import, pre-6.0 legacy backup restore, and a quiz borrowing from another course's shared bank.

### M6. Prototype editor UI

The registry-driven shared editor component, the library view, child-prototype creation, documentation, history with diff and restore, draft and publish, and the usage report extending `scripts/prototypeusage.php`. The opt-in push to attempts in progress belongs here only if attempts are pinned.

### M7. New question form

The prototype picker with its grouping, and the capability check on save as well as in the picker; registry-driven sections with inherited and overridden states, revert, and change counts; test case field switches; *Make prototype from this question*; the legacy prototype-question banner; removal of the *Customise* checkbox.

**Exit:** besides Behat coverage of override, revert and prototype-change, an automated test enumerating the registry against `extra_question_fields_static()` and the old form's elements, so the notes' "the field set comes from the current code" rule is enforced rather than remembered.

### M8. Site defaults and tidying

The `before_course_deleted` handler; the cleanup script for legacy prototype questions; the prune script for prototype history. Documentation.

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
| Resolved-settings cache | qtype | consumed on the question-load path |
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

Open:

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
