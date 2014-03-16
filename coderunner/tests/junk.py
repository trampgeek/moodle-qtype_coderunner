from selenium import selenium
import unittest, time, re

class junk(unittest.TestCase):
    def setUp(self):
        self.verificationErrors = []
        self.selenium = selenium("localhost", 4444, "*chrome", "http://csse-rjl83-l")
        self.selenium.start()
    
    def test_junk(self):
        sel = self.selenium
        sel.open("/moodle/login/index.php")
        sel.click("id=username")
        sel.type("id=username", "admin")
        sel.click("id=loginbtn")
        sel.wait_for_page_to_load("30000")
        sel.click("link=Question bank")
        sel.wait_for_page_to_load("30000")
        sel.click("css=input[type=\"submit\"]")
        sel.click("id=qtype_coderunner")
        sel.click("id=chooseqtype_submit")
        sel.wait_for_page_to_load("30000")
        sel.select("id=id_category", "label=regexp:Default for Front page.*")
        sel.select("id=id_coderunner_type", "label=python3")
        sel.type("id=id_name", "Test_template")
        sel.click("id=id_customise")
        sel.type("id=id_per_test_template", "def sqr(n):\n    {{STUDENT_ANSWER}}\n\nprint({{TEST.testcode}})")
        sel.type("id=id_testcode_0", "sqr(-3)")
        sel.type("id=id_expected_0", "9")
        sel.type("id=id_testcode_1", "sqr(11)")
        sel.type("id=id_expected_1", "121")
        sel.click("id=id_useasexample_0")
        sel.click("link=Support files")
        sel.click("id=id_submitbutton")
        sel.wait_for_page_to_load("30000")
        sel.click("//img[@alt='Preview']")
        sel.wait_for_pop_up("questionpreview", "3000")
        sel.select_window("questionpreview")
        sel.type("css='.coderunner-answer'", "return n * n")
        sel.click("css='input.submit btn'")
        sel.wait_for_page_to_load("30000")
        self.failUnless(sel.is_text_present("Passed all tests!"))
    
    def tearDown(self):
        self.selenium.stop()
        self.assertEqual([], self.verificationErrors)

if __name__ == "__main__":
    unittest.main()
