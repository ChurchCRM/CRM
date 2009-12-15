<?php
// You must cross reference your defined pledge ID values from the names by looking at the database

// The fundID's below are from an edited, working example.  At one point, a fundID was deleted (3).
// This example has 4 funds: 1, 2, 4, and 5.  These funds correspond with 'names' that can be entered by an eGive giver, and as such, we can only guess at the fund names they will use.  We provide a default fundID if nothing matches.

// general offering, operating, plate gifts
$eGiveBreakoutNames2FundIds["general"] = 1;
$eGiveBreakoutNames2FundIds["operating"] = 1;
$eGiveBreakoutNames2FundIds["offering"] = 1;

// missions, Our Church's Wider Mission
$eGiveBreakoutNames2FundIds["ocwm"] = 2; //OCWM
$eGiveBreakoutNames2FundIds["mission"] = 2;

// Capital Campaign.  In this case, the capital campaing was called "Renew, Refresh, Rejoice".  The names below will be case-insensitive matched to anything specified in the eGive 'breakout' name
$eGiveBreakoutNames2FundIds["rrr"] = 4; //RRR
$eGiveBreakoutNames2FundIds["renew"] = 4;
$eGiveBreakoutNames2FundIds["rejoice"] = 4;
$eGiveBreakoutNames2FundIds["refresh"] = 4;
$eGiveBreakoutNames2FundIds["capital"] = 4;

// This example church has chosen 'Miscelaneous' gift catagory.  If you don't want this, specify this as one of the other specified funds, perhaps '1'
$defaultFundId = 5; // Misc

// 64 character 'alpha' key provided by eGive
//              0123456789012345678901234567890123456789012345678901234567890123
$eGiveApiKey = "6ebef5c8c0848a7091ba23f68d06770c1476ccc169f2ade3be2952fc03de4cd7";
$eGiveURL = "https://www.egive-usa.com";

// specific number for your church
$eGiveOrgID = 13821;
?>