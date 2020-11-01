Feature: User Self-Service Password Change

    Scenario: User initiated password change failes because new password is insufficient
        Given I am authenticated as "admin" using "changeme"
        And I click ".user-menu"
        And I click "#change-password"


     Scenario: User initiated password change with sufficient new password
        Given I am authenticated as "admin" using "changeme"
        And I am on "v2/user/current/changepassword"
        And I fill in "OldPassword" with "changeme"
        And I fill in "NewPassword1" with "SomeThingsAreBetterLeftUnChangedJustKidding"
        And I fill in "NewPassword2" with "SomeThingsAreBetterLeftUnChangedJustKidding"
        And I press "Save"
        Then I should see "Password Change Successful"

    Scenario: Log in with new password and change back to default
        Given I am authenticated as "admin" using "SomeThingsAreBetterLeftUnChangedJustKidding"
        And I click ".user-menu"
        And I click "#change-password"
        Then I should see "Enter your current password, then your new password twice"
        And I fill in "OldPassword" with "SomeThingsAreBetterLeftUnChangedJustKidding"
        And I fill in "NewPassword1" with "changeme"
        And I fill in "NewPassword2" with "changeme"
        And I press "Save"
        Then I should see "Password Change Successful"
