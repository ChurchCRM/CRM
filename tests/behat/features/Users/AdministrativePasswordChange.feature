Feature: Administrator Change Other User Password
   
    Scenario: Administratively set other user's password
        Given I am authenticated as "admin" using "changeme"
        And I am on "/UserList.php"
        And I click "#user-listing-table > tbody > tr:nth-child(2) > td:nth-child(6) > a:nth-child(1)"
        Then I should see "Change Password: Tony Campbell"
        And I should see "Administratively set passwords are not subject to length or complexity requirements"
        And I fill in "NewPassword1" with "password"
        And I fill in "NewPassword2" with "password"
        And I press "Save"
        Then I should see "Password Change Successful"

    Scenario: Log in as User with invalid password
        Given I am authenticated as "tony.wade@example.com" using "notpassword"
        Then I should see "Invalid Login or Password"

    Scenario: Log in as User with newly changed password
        Given I am authenticated as "tony.wade@example.com" using "password"
        Then I should see "Welcome to"
