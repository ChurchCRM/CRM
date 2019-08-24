Feature: Letters and Labels
  In order to create Letters and Mailing Labels
  As a User
  I am able to visit the Letters and Mailing Labels page

  Scenario: Open the Letters and Labels Page
    Given I am authenticated as "admin" using "changeme"
    And I am on "/LettersAndLabels.php"
    Then I should see "Letters and Mailing Labels"
    And I should see "People Reports"
    When I press "Newsletter labels"
    #Then I should see in the header "Content-Disposition:attachment;"
    Then I am on "/LettersAndLabels.php"
    When I press "Confirm data labels"
    #Then I should see in the header "Content-Disposition:attachment;"
    Then I am on "/LettersAndLabels.php"
    #When I press "Cancel"
    #Then I should see "Welcome to"
