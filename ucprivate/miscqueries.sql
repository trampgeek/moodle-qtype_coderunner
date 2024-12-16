# List all courses and their contextids
SELECT crs.id, ctx.id as contextid, crs.shortname as name
FROM mdl_course crs
JOIN mdl_context ctx ON ctx.instanceid = crs.id
WHERE ctx.contextlevel = 50
ORDER BY name;

# List all questions in a given course
SELECT q.id, qv.version, q.name
FROM mdl_context ctx
JOIN mdl_course crs on crs.id = ctx.instanceid
JOIN mdl_question_categories qc on qc.contextid = ctx.id
JOIN mdl_question_bank_entries qbe on qbe.questioncategoryid = qc.id
JOIN mdl_question_versions qv on qv.questionbankentryid = qbe.id
JOIN mdl_question q on q.id = qv.questionid
WHERE crs.shortname='COSC131-24S1'
ORDER BY q.name;

# Change the text of questions in a given course
UPDATE mdl_question q
SET questiontext = REPLACE(q.questiontext, 'Pylint', 'Ruff')
WHERE q.id IN (
    SELECT subquery.id
    FROM (
        SELECT q.id
        FROM mdl_context ctx
        JOIN mdl_course crs ON crs.id = ctx.instanceid
        JOIN mdl_question_categories qc ON qc.contextid = ctx.id
        JOIN mdl_question_bank_entries qbe ON qbe.questioncategoryid = qc.id
        JOIN mdl_question_versions qv ON qv.questionbankentryid = qbe.id
        JOIN mdl_question q ON q.id = qv.questionid
        WHERE crs.shortname = 'COSC131-25S1'
    ) AS subquery
);

# Update coderunner options template parameters
UPDATE mdl_question_coderunner_options o
SET templateparams = REPLACE(o.templateparams, '["pylint"]', '["ruff"]')
WHERE o.questionid IN (
    SELECT subquery.id
    FROM (
        SELECT q.id
        FROM mdl_context ctx
        JOIN mdl_course crs ON crs.id = ctx.instanceid
        JOIN mdl_question_categories qc ON qc.contextid = ctx.id
        JOIN mdl_question_bank_entries qbe ON qbe.questioncategoryid = qc.id
        JOIN mdl_question_versions qv ON qv.questionbankentryid = qbe.id
        JOIN mdl_question q ON q.id = qv.questionid
        WHERE crs.shortname = 'COSC131-25S1'
    ) AS subquery
);

# All questions that match a particular template parameter string
SELECT q.id, q.name
FROM mdl_context ctx
JOIN mdl_course crs ON crs.id = ctx.instanceid
JOIN mdl_question_categories qc ON qc.contextid = ctx.id
JOIN mdl_question_bank_entries qbe ON qbe.questioncategoryid = qc.id
JOIN mdl_question_versions qv ON qv.questionbankentryid = qbe.id
JOIN mdl_question q ON q.id = qv.questionid
JOIN mdl_question_coderunner_options o on o.questionid = q.id
WHERE crs.shortname = 'COSC131-25S1'
AND o.templateparams like 'precheckers:';
