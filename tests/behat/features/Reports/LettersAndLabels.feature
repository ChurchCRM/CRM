Feature: Letters and Labels
  In order to create Letters and Mailing Labels
  As a User
  I am able to visit the Letters and Mailing Labels page

  Scenario: Open the Letters and Labels Page
    Given I am authenticated as "admin" using "changeme"
    And I am on "/LettersAndLabels.php"
    And I wait for AJAX to finish
    Then I should see "Letters and Mailing Labels"
    And I should see "People Reports"
    When I press "Newsletter labels"
    And I wait for AJAX to finish
    #Then I should see in the header "Content-Disposition:attachment;"
    Then I am on "/LettersAndLabels.php"
    When I press "Confirm data letter"
    And I wait for AJAX to finish
    #Then I should see in the header "Content-Disposition:attachment;"
    Then I am on "/LettersAndLabels.php"
    When I press "Confirm data Email"
    And I wait for AJAX to finish
    #Then I should see in the header "Content-Disposition:attachment;"
    Then I am on "/LettersAndLabels.php"
    When I press "Confirm data labels"
    And I wait for AJAX to finish
    #Then I should see in the header "Content-Disposition:attachment;"
    Then I am on "/LettersAndLabels.php"
    #When I press "Cancel"
    #Then I should see "Welcome to"
