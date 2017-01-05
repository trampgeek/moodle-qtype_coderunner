SELECT * FROM `mdl_question_attempt_steps` as step JOIN mdl_question_attempt_step_data as data on step.id = data.attemptstepid order by questionattemptid desc limit 40


SELECT * FROM `mdl_question_attempts` as attempt
JOIN mdl_question_attempt_steps as step ON attempt.id = step.questionattemptid
JOIN mdl_question_attempt_step_data as data ON step.id = data.attemptstepid
ORDER BY questionattemptid DESC LIMIT 40




SELECT
    quba.id AS qubaid,
    quba.contextid,
    quba.component,
    quba.preferredbehaviour,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.slot,
    qa.behaviour,
    qa.questionid,
    qa.variant,
    qa.maxmark,
    qa.minfraction,
    qa.maxfraction,
    qa.flagged,
    qa.questionsummary,
    qa.rightanswer,
    qa.responsesummary,
    qa.timemodified,
    qas.id AS attemptstepid,
    qas.sequencenumber,
    qas.state,
    qas.fraction,
    qas.timecreated,
    qas.userid,
    qasd.name,
    qasd.value

FROM      mdl_question_usages            quba
LEFT JOIN mdl_question_attempts          qa   ON qa.questionusageid    = quba.id
LEFT JOIN mdl_question_attempt_steps     qas  ON qas.questionattemptid = qa.id
LEFT JOIN mdl_question_attempt_step_data qasd ON qasd.attemptstepid    = qas.id

WHERE
    quba.id = 8270

ORDER BY
    qa.slot,
    qas.sequencenumber;
