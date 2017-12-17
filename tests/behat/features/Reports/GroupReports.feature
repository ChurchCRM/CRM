Feature: Group Reports
  In order to report on group membership
  As a User
  I am able to visit the Group Reports page

  Scenario: Open the Group Reports Page
    Given I am authenticated as "admin" using "changeme"
    And I am on "/GroupReports.php"
    Then I should see "Group reports"
    And I should see "Select the group you would like to report"
    When I press "Next"
    Then I should see "Select which information you want to include"
    When I press "Create Report"
    #Then I should see in the header "Content-Disposition:attachment;"
