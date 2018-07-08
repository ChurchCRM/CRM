Feature: SystemSettings
  In order to modify system settings
  As a User
  I am able to enter data into the System Settings page

  Scenario: View system settings
    Given I am authenticated as "admin" using "changeme"
    And I am on "/SystemSettings.php"
    Then I should see "Church Information"
    And I should see "User Setup"
    And I should see "Email Setup"
    And I should see "People Setup"
    And I should see "System Settings"
    And I should see "Map Settings"
    And I should see "Report Settings"
    And I should see "Localization"
    And I should see "Financial Settings"
    And I should see "Integration"
    And I should see "Backup"

  Scenario: Enter Non-Text System Setting
    Given I am authenticated as "admin" using "changeme"
    And I am on "/SystemSettings.php"
    And I fill in "new_value[1003]" with "Test's test"
    And I click "#save"
    Then the "new_value[1003]" field should contain "Test's test"
    Then the "new_value[1003]" field should not contain "Test\'s test"