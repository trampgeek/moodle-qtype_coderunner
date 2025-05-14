SELECT DISTINCT t.id, t.name, t.rawname
FROM mdl_question_attempt_steps qas
JOIN mdl_question_attempts qa ON qa.id = qas.questionattemptid
JOIN mdl_quiz_attempts quiza ON quiza.uniqueid = qa.questionusageid
JOIN mdl_quiz quiz ON quiz.id = quiza.quiz
join mdl_course_modules cm on `instance` = quiz.id
JOIN mdl_tag_instance ti ON ti.itemid = cm.id 
JOIN mdl_tag t ON t.id = ti.tagid
WHERE ti.itemtype='course_modules'
AND qas.id = 17354;
