Feature: Dashboard
  In order to manage the system
  As a User
  I am able to visit the dashboard

  Scenario: Dashboard
    Given I am authenticated as "admin" using "changeme"
    And  I am on "/v2/dashboard"
    And I wait for AJAX to finish
  
