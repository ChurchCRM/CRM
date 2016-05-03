# Structure

The following tables exist in ChurchCRM:

- **autopayment_aut** - this contains information for automatic electronic donations or payments for events
- **canvassdata_can** - this contains information about the results of canvassing families
- **deposit_dep** - this records deposits / payments
- **donationfund_fun** - this contains the defined donation funds
- **event_attend** - this indicates which people attended which events
- **family_fam** - this contains the main family data, including family name, family addresses, and family phone numbers
- **group_grp** - this contains the name and description for each group, as well as foreign keys to the list of group roles
- **groupprop_master** - this contains definitions for the group-specific fields
- **list_lst** - this table stores the options for most of the drop down lists in churchCRM, including person classifications, family roles, group types, group roles, group-specific property types, and custom field value lists.
- **note_nte** - contains all person and family notes, including the date, time, and person who entered the note
- **person2group2role_p2g2r** - this table stores the information of which people are in which groups, and what group role each person holds in that group
- **person2volunteeropp_p2vo** - this table indicates which people are tied to which volunteer opportunities
- **person_custom** - ??
- **person_custom_master** - this contains definitions for the custom person fields
- **person_per** - this contains the main person data, including person names, person addresses, person phone numbers, and foreign keys to the family table
- **pledge_plg** - this contains all pledge information
- **property_pro** - this contains the definition of all person, family, and group properties
- **property_prt** - this contains all the defined property types
- **query_qry** - this contains all the predefined queries that appear in the queries page
- **queryparameteroptions_qpo** - defines the values for the parameters for each query
- **queryparameters_qrp** - defines the parameters for each query
- **record2property_r2p** - this table indicates which persons, families, or groups are assigned specific properties and what the values of those properties are.
- **result_res** - contains the results of authorizations from electronic payments
- **user_usr** - this contains the login information and specific settings for each ChurchCRM user
- **volunteeropportunity_vol** - this contains the names and descriptions of volunteer opportunities
- **whycame_why** - this contains the comments related to why people came
