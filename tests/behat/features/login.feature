Feature: Login
  In order to login to ChurchCRM
  As a User
  I am able to authenticate with a username and a password

  Scenario: Login to a system
    Given I am on the homepage
    Then I should see "Please Login"
    When I fill in "UserBox" with "admin"
    When I fill in "PasswordBox" with "changeme"
    And I press "Login"
    Then I should see "Welcome to"
