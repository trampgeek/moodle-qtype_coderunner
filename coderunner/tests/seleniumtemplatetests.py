from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import Select
from selenium.webdriver.support.ui import WebDriverWait
from selenium.common.exceptions import NoSuchElementException
from selenium.webdriver.support import expected_conditions as EC
import unittest, time, re

class Seleniumtemplatetests(unittest.TestCase):
    def setUp(self):
        self.driver = webdriver.Chrome()
        self.driver.implicitly_wait(5)
        self.base_url = "http://csse-rjl83-l"
        self.verificationErrors = []
        self.accept_next_alert = True
    
    def test_seleniumtemplatetests(self):
        driver = self.driver
        driver.get(self.base_url + "/moodle/login/index.php")
        driver.find_element_by_id("username").send_keys("admin")
        driver.find_element_by_id("password").send_keys("1qazZAQ!")
        driver.find_element_by_id("loginbtn").click()
        driver.find_element_by_link_text("Question bank").click()
        driver.find_element_by_css_selector("input[type=\"submit\"]").click()
        driver.find_element_by_id("qtype_coderunner").click()
        driver.find_element_by_id("chooseqtype_submit").click()
        select = Select(driver.find_element_by_id("id_category"))
        opt = [o.text for o in select.options if o.text.startswith('Default for Front')][0]
        select.select_by_visible_text(opt)
        
        select2 = Select(driver.find_element_by_id('id_coderunner_type'))
        select2.select_by_visible_text('python3')

        driver.find_element_by_id("id_name").clear()
        driver.find_element_by_id("id_name").send_keys("Test_template")
        driver.find_element_by_id("id_customise").click()
        driver.find_element_by_id("id_per_test_template").clear()
        driver.find_element_by_id("id_per_test_template").send_keys("def sqr(n):\n    {{STUDENT_ANSWER}}\n\nprint({{TEST.testcode}})")
        driver.find_element_by_id("id_testcode_0").clear()
        driver.find_element_by_id("id_testcode_0").send_keys("sqr(-3)")
        driver.find_element_by_id("id_expected_0").clear()
        driver.find_element_by_id("id_expected_0").send_keys("9")
        driver.find_element_by_id("id_testcode_1").clear()
        driver.find_element_by_id("id_testcode_1").send_keys("sqr(11)")
        driver.find_element_by_id("id_expected_1").clear()
        driver.find_element_by_id("id_expected_1").send_keys("121")
        driver.find_element_by_id("id_useasexample_0").click()
        driver.find_element_by_link_text("Support files").click()
        driver.find_element_by_id("id_submitbutton").click()
        driver.find_element_by_xpath("//img[@alt='Preview']").click()
        element = WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "questionpreview")))
        Select(driver.find_element_by_name('questionpreview'))
        driver.find_element_by_css_selector("'.coderunner-answer'").send_keys("return n * n")
        driver.find_element_by_css_selector("'input.submit btn'").click()
        self.assertEqual("Passed all tests!", driver.find_element_by_tag_name("BODY").text)
    
    def is_element_present(self, how, what):
        try: self.driver.find_element(by=how, value=what)
        except NoSuchElementException, e: return False
        return True
    
    def is_alert_present(self):
        try: self.driver.switch_to_alert()
        except NoAlertPresentException, e: return False
        return True
    
    def close_alert_and_get_its_text(self):
        try:
            alert = self.driver.switch_to_alert()
            alert_text = alert.text
            if self.accept_next_alert:
                alert.accept()
            else:
                alert.dismiss()
            return alert_text
        finally: self.accept_next_alert = True
    
    def tearDown(self):
        self.driver.quit()
        self.assertEqual([], self.verificationErrors)

if __name__ == "__main__":
    unittest.main()
