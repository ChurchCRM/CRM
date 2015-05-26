You can import data from a comma-separated value (.csv) file. Every line in this file must contain information about one person. Family relationships are inferred by matching up the last name and address.

The “CSV Import” feature is in the Admin menu. Once a file is chosen and uploaded, there will be a page which allows you to tell ChurchInfo which field of your file corresponds to specific fields in ChurchInfo. The list of possible fields that ChurchInfo will import is shown below. You do not need to import data into every field.

- Title
- First Name
- Middle Name
- Last Name
- Suffix
- Gender
- Donation Envelope
- Address1
- Address2
- City
- State
- Zip
- Country
- Home Phone
- Work Phone
- Mobile Phone
- Email
- Work/Other Email
- Birth Date
- Membership Date
- Wedding Date

Dates can be formatted as YYYY-MM-DD, MM-DD-YYYY, or DD-MM-YYYY. The date separator (dash, slash, etc.) or lack thereof does not matter.

It is best to import the data first, before you invest time into tweaking the database. This way you can use the feature of the import screen which deletes all of the Person and Family records so you can try importing again. It is usually easier to do most of the necessary clean-up in your data file rather than navigating the ChurchInfo interface to make corrections.

Once you are satisfied that the import has done what it can, you can make corrections using the ChurchInfo pages. You can move someone to a different family by editing the Person record. You can also specify family roles such as “child”, which helps ChurchInfo identify the adults for address labels.

Another area which may require attention is the “classification” field, which keeps track of which people are members. This information becomes important when ChurchInfo is used to generate the list of voting members. By creating separate import files by membership type (member, guest, regular attender, etc.), you can assign a classification during the import process that will apply to each person in that file. Example: Create an import file that contains only “Members” so the classification of “Member” can be assigned during the import process. This way each person in that file will be classified as a “Member” in ChurchInfo. Then, import another file that contains only “Guests” so the classification of “Guest” can be assigned during the import process…and so on.

*Note that if you are running this on shared hosting then you may not have write access to the default area needed for import and the import script will show a file with 0 rows and not import anything. In this case, edit lines 138 and 305 of the CSVImport.php file so that they point to a writable location, e.g. $system_temp=”../tmp”;*