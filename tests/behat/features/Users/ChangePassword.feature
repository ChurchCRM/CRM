Feature: User Self-Service Password Change
    Scenario: user-initiated self password change to new password
        Given I am authenticated as "admin" using "changeme"
        And I click ".user-menu"
        And I click "#change-password"

#    Scenario: user-initiated self password change to previously used password

#    Scenario: user-initiated self password change to new weak password    

#Feature: User Password Expired 
#    Scenario: Force Change at Login

#    Scenario: Force Change During Active Session

#Feature: Administrator Change Other User Password
#    Scenario: Administratively set other user's password