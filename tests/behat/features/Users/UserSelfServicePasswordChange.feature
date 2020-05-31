Feature: User Self-Service Password Change

    Scenario: User initiated password change failes because new password is insufficient
        Given I am authenticated as "admin" using "changeme"
        And I click ".user-menu"
        And I click "#change-password"
        Then I should see "Enter your current password, then your new password twice"
        And I fill in "OldPassword" with "ILikePancakes" 
        And I fill in "NewPassword1" with "changeyou"
        And I fill in "NewPassword2" with "changeyou"
        And I press "Save"
        Then I should see "Incorrect password supplied for current user"
        And I fill in "OldPassword" with "changeme" 
        And I fill in "NewPassword1" with "password"
        And I fill in "NewPassword2" with "password"
        And I press "Save"
        Then I should see "Your password choice is too obvious. Please choose something else."
        And I fill in "OldPassword" with "changeme" 
        And I fill in "NewPassword1" with "Bob"
        And I fill in "NewPassword2" with "Bob"
        And I press "Save"
        Then I should see "Your new password must be at least"
        And I fill in "OldPassword" with "changeme" 
        And I fill in "NewPassword1" with "changeme"
        And I fill in "NewPassword2" with "changeme"
        And I press "Save"
        Then I should see "Your new password must not match your old one"
        And I fill in "OldPassword" with "changeme" 
        And I fill in "NewPassword1" with "changeyou"
        And I fill in "NewPassword2" with "changeyou"
        And I press "Save"
        Then I should see "Your new password is too similar to your old one"

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
 

# TODO: Implement test clauses to manipulate database
# TODO: Implement this test after fixing #5158
#Feature: User Password Expired 
#    Scenario: Force Change at Login

#    Scenario: Force Change During Active Session