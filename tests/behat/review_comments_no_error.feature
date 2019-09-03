@mod @mod_review
Feature: Switch role does not cause an error message in reviewsubmission_comments

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username |
      | teacher1 |
    And the following "course enrolments" exist:
      | course | user     | role           |
      | C1     | teacher1 | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Review" to section "1" and I fill the form with:
      | Review name           | Test review              |
      | Description               | This is the description text |
      | Students submit in groups | Yes                          |

  Scenario: I switch role to student and an error doesn't occur
    When I follow "Switch role to..." in the user menu
    And I press "Student"
    And I follow "Test review"
    Then I should see "This is the description text"
