Feature: People Directory
  In order to create a people directory
  As a User
  I am able to visit the Directory reports page

  Scenario: Open the People Dashboard
    Given I am authenticated as "admin" using "changeme"
    And I am on "/DirectoryReports.php"
    And I wait for AJAX to finish
    Then I should see "Directory reports"
    And I should see "Select classifications to include"
    When I press "Create Directory"
    And I wait for AJAX to finish
    #Then I should see in the header "Content-Disposition:attachment;"
