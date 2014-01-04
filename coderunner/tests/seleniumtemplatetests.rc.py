from selenium import selenium
import unittest, time, re

class seleniumtemplatetestsrc(unittest.TestCase):
    def setUp(self):
        self.verificationErrors = []
        self.selenium = selenium("localhost", 4444, "*chrome", "http://csse-rjl83-l")
        self.selenium.start()
    
    def test_seleniumtemplatetestsrc(self):
        sel = self.selenium
        sel.open("/moodle/login/index.php")
        sel.type("id=username", "admin")
        sel.type("id=password", "1qazZAQ!")
        sel.click("id=loginbtn")
        sel.wait_for_page_to_load("5000")
        sel.click("link=Question bank")
        sel.wait_for_page_to_load("5000")
        sel.click("css=input[type=\"submit\"]")
        sel.click("id=qtype_coderunner")
        sel.click("id=chooseqtype_submit")
        sel.wait_for_page_to_load("5000")
        sel.select("id=id_category", "label=regexp:Default for Front page.*")
        sel.select("id=id_coderunner_type", "label=python3")
        sel.type("id=id_name", "Test_template_selenium")
        sel.click("id=id_customise")
        sel.type("id=id_per_test_template", "def sqr(n):\n    {{STUDENT_ANSWER}}\n\nprint({{TEST.testcode}})")
        sel.type("id=id_testcode_0", "sqr(-3)")
        sel.type("id=id_expected_0", "9")
        sel.type("id=id_testcode_1", "sqr(11)")
        sel.type("id=id_expected_1", "121")
        sel.click("id=id_useasexample_0")
        sel.click("link=Support files")
        sel.click("id=id_submitbutton")
        sel.wait_for_page_to_load("5000")
        sel.click("css=tr:contains('Test_template_selenium') td.iconcol.previewaction img")
        sel.wait_for_pop_up("questionpreview", "3000")
        sel.select_window("questionpreview")
        sel.type("css=textarea.coderunner-answer.edit_code", "return n * n")
        sel.click("css=.submit.btn")
        sel.wait_for_page_to_load("5000")
        self.failUnless(sel.is_text_present("Passed all tests!"))
        sel.close()
        sel.select_window("null")
        sel.click("css=tr:contains('Test_template_selenium') a[title=\"Delete\"] img")
        sel.click("css=input[type=\"submit\"]")
        sel.wait_for_page_to_load("5000")
        sel.click("link=logout")
        sel.wait_for_page_to_load("5000")
    
    def tearDown(self):
        self.selenium.stop()
        self.assertEqual([], self.verificationErrors)

if __name__ == "__main__":
    unittest.main()
