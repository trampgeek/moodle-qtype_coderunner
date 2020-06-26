        SELECT
            concat(quiza.uniqueid, qasd.attemptstepid, qasd.id) as uniquekey,
            quiza.uniqueid as quizattemptid,
            timestart,
            timefinish,
            u.firstname,
            u.lastname,
            u.email,
            qatt.slot,
            qatt.questionid,
            quest.name as qname,
            slot.maxmark as mark,
            qattsteps.timecreated as timestamp,
            FROM_UNIXTIME(qattsteps.timecreated,'%Y/%m/%d %H:%i:%s') as datetime,
            qattsteps.fraction,
            qattsteps.state,
            qasd.attemptstepid,
            qasd.name as qasdname,
            qasd.value as value

        FROM {user} u
        JOIN {quiz_attempts} quiza ON quiza.userid = u.id AND quiza.quiz = :quizid
        JOIN {question_attempts} qatt ON qatt.questionusageid = quiza.uniqueid
        JOIN {question_attempt_steps} qattsteps ON qattsteps.questionattemptid = qatt.id
        JOIN {question_attempt_step_data} qasd on qasd.attemptstepid = qattsteps.id
        JOIN {question} quest ON quest.id = qatt.questionid
        JOIN {quiz_slots} slot ON qatt.slot = slot.slot AND slot.quizid = quiza.quiz

        WHERE quiza.preview = 0
        AND (qasd.name NOT RLIKE '^-_' OR qasd.name = '-_rawfraction')
        AND (qasd.name NOT RLIKE '^_' OR qasd.name = '_testoutcome')
        AND quest.length > 0
        ORDER BY quiza.uniqueid, timestamp;
