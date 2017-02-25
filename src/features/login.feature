Feature: Login
  In order to login to ChurchCRM
  As a User
  I am able to authenticate with a username and a password

  Scenario: Login to a system
    Given I visit '/Login.php'
    Then I should see "Please Login"
    When I supply a username and password
    And I click submit
    Then I should see the Main Menu