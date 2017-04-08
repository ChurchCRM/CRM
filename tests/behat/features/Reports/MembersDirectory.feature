Feature: Member Directory
  In order to create a member directory
  As a User
  I am able to visit the Directory reports page

  Scenario: Open the Members Dashboard
    Given I am authenticated as "admin" using "changeme"
    And I am on "/DirectoryReports.php"
    Then I should see "Directory reports"
    And I should see "Select classifications to include"
    When I press "Create Directory"
    #Then I should see in the header "Content-Disposition:attachment;"
