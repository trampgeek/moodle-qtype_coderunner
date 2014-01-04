<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head profile="http://selenium-ide.openqa.org/profiles/test-case">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="selenium.base" href="http://csse-rjl83-l" />
<title>seleniumtemplatetests</title>
</head>
<body>
<table cellpadding="1" cellspacing="1" border="1">
<thead>
<tr><td rowspan="1" colspan="3">seleniumtemplatetests</td></tr>
</thead><tbody>
<tr>
	<td>open</td>
	<td>/moodle/login/index.php</td>
	<td></td>
</tr>
<tr>
	<td>click</td>
	<td>id=username</td>
	<td></td>
</tr>
<tr>
	<td>type</td>
	<td>id=username</td>
	<td>admin</td>
</tr>
<tr>
	<td>clickAndWait</td>
	<td>id=loginbtn</td>
	<td></td>
</tr>
<tr>
	<td>clickAndWait</td>
	<td>link=Question bank</td>
	<td></td>
</tr>
<tr>
	<td>click</td>
	<td>css=input[type=&quot;submit&quot;]</td>
	<td></td>
</tr>
<tr>
	<td>click</td>
	<td>id=qtype_coderunner</td>
	<td></td>
</tr>
<tr>
	<td>clickAndWait</td>
	<td>id=chooseqtype_submit</td>
	<td></td>
</tr>
<tr>
	<td>select</td>
	<td>id=id_category</td>
	<td>label=regexp:Default for Front page.*</td>
</tr>
<tr>
	<td>select</td>
	<td>id=id_coderunner_type</td>
	<td>label=python3</td>
</tr>
<tr>
	<td>type</td>
	<td>id=id_name</td>
	<td>Test_template</td>
</tr>
<tr>
	<td>click</td>
	<td>id=id_customise</td>
	<td></td>
</tr>
<tr>
	<td>type</td>
	<td>id=id_per_test_template</td>
	<td>def sqr(n):<br />&nbsp;&nbsp;&nbsp;&nbsp;{{STUDENT_ANSWER}}<br /><br />print({{TEST.testcode}})</td>
</tr>
<tr>
	<td>type</td>
	<td>id=id_testcode_0</td>
	<td>sqr(-3)</td>
</tr>
<tr>
	<td>type</td>
	<td>id=id_expected_0</td>
	<td>9</td>
</tr>
<tr>
	<td>type</td>
	<td>id=id_testcode_1</td>
	<td>sqr(11)</td>
</tr>
<tr>
	<td>type</td>
	<td>id=id_expected_1</td>
	<td>121</td>
</tr>
<tr>
	<td>click</td>
	<td>id=id_useasexample_0</td>
	<td></td>
</tr>
<tr>
	<td>click</td>
	<td>link=Support files</td>
	<td></td>
</tr>
<tr>
	<td>clickAndWait</td>
	<td>id=id_submitbutton</td>
	<td></td>
</tr>
<tr>
	<td>click</td>
	<td>css=tr:contains('Test_template_selenium')</td>
	<td></td>
</tr>
<tr>
	<td>waitForPopUp</td>
	<td>questionpreview</td>
	<td>30000</td>
</tr>
<tr>
	<td></td>
	<td></td>
	<td></td>
</tr>
<tr>
	<td>click</td>
	<td>//img[@alt='Preview']</td>
	<td></td>
</tr>
<tr>
	<td>waitForPopUp</td>
	<td>questionpreview</td>
	<td>3000</td>
</tr>
<tr>
	<td>type</td>
	<td>css='.coderunner-answer.edit_code'</td>
	<td>return n * n</td>
</tr>
<tr>
	<td>clickAndWait</td>
	<td>css='input.submit btn'</td>
	<td></td>
</tr>
<tr>
	<td>assertTextPresent</td>
	<td>Passed all tests!</td>
	<td></td>
</tr>
</tbody></table>
</body>
</html>
