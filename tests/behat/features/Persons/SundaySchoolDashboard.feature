Feature: Sunday School Dashboard
  In order to see Sunday School data at a glance
  As a User
  I am able to visit the Sunday School dashboard

  Scenario: Sunday School Dashboard
    Given I am authenticated as "admin" using "changeme"
    And I am on "/sundayschool/SundaySchoolDashboard.php"
    Then I should see "Sunday School Dashboard"
    And I should see "Sunday School Classes"
    And I should see "Students not in a Sunday School Class"
    And I should see "TEACHERS"
    And I should see "STUDENTS"