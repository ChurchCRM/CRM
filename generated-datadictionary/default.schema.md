# Table of Contents <a name="TOC"></a>
1. [autopayment_aut](#autopayment-aut)
2. [canvassdata_can](#canvassdata-can)
3. [config_cfg](#config-cfg)
4. [deposit_dep](#deposit-dep)
5. [donateditem_di](#donateditem-di)
6. [donationfund_fun](#donationfund-fun)
7. [egive_egv](#egive-egv)
8. [email_message_pending_emp](#email-message-pending-emp)
9. [email_recipient_pending_erp](#email-recipient-pending-erp)
10. [event_attend](#event-attend)
11. [event_types](#event-types)
12. [eventcountnames_evctnm](#eventcountnames-evctnm)
13. [eventcounts_evtcnt](#eventcounts-evtcnt)
14. [events_event](#events-event)
15. [family_custom](#family-custom)
16. [family_custom_master](#family-custom-master)
17. [family_fam](#family-fam)
18. [fundraiser_fr](#fundraiser-fr)
19. [group_grp](#group-grp)
20. [groupprop_master](#groupprop-master)
21. [kioskassginment_kasm](#kioskassginment-kasm)
22. [kioskdevice_kdev](#kioskdevice-kdev)
23. [list_lst](#list-lst)
24. [menuconfig_mcf](#menuconfig-mcf)
25. [note_nte](#note-nte)
26. [person2group2role_p2g2r](#person-group-role-p-g-r)
27. [person2volunteeropp_p2vo](#person-volunteeropp-p-vo)
28. [person_custom](#person-custom)
29. [person_custom_master](#person-custom-master)
30. [person_per](#person-per)
31. [pledge_plg](#pledge-plg)
32. [property_pro](#property-pro)
33. [propertytype_prt](#propertytype-prt)
34. [queryparameters_qrp](#queryparameters-qrp)
35. [record2property_r2p](#record-property-r-p)
36. [tokens](#tokens)
37. [user_usr](#user-usr)
38. [userconfig_ucfg](#userconfig-ucfg)
39. [version_ver](#version-ver)
40. [volunteeropportunity_vol](#volunteeropportunity-vol)
41. [whycame_why](#whycame-why)
## Table: autopayment_aut<a name="autopayment-aut"></a>
[Table of Contents](#TOC)

### Description:
This contains information for automatic electronic donations or payments for events
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|aut_ID|Id| [PK]|SMALLINT|9||
|aut_FamID|Familyid||SMALLINT|9||
|aut_EnableBankDraft|EnableBankDraft||BOOLEAN|1||
|aut_EnableCreditCard|EnableCreditCard||BOOLEAN|1||
|aut_NextPayDate|NextPayDate||DATE|||
|aut_FYID|Fyid||SMALLINT|9||
|aut_Amount|Amount||DECIMAL|6||
|aut_Interval|Interval||TINYINT|3||
|aut_Fund|Fund||SMALLINT|6||
|aut_FirstName|FirstName||VARCHAR|50||
|aut_LastName|LastName||VARCHAR|50||
|aut_Address1|Address1||VARCHAR|255||
|aut_Address2|Address2||VARCHAR|255||
|aut_City|City||VARCHAR|50||
|aut_State|State||VARCHAR|50||
|aut_Zip|Zip||VARCHAR|50||
|aut_Country|Country||VARCHAR|50||
|aut_Phone|Phone||VARCHAR|30||
|aut_Email|Email||VARCHAR|100||
|aut_CreditCard|CreditCard||VARCHAR|50||
|aut_ExpMonth|ExpMonth||VARCHAR|2||
|aut_ExpYear|ExpYear||VARCHAR|4||
|aut_BankName|BankName||VARCHAR|50||
|aut_Route|Route||VARCHAR|30||
|aut_Account|Account||VARCHAR|30||
|aut_DateLastEdited|DateLastEdited||TIMESTAMP|||
|aut_EditedBy|Editedby||SMALLINT|5||
|aut_Serial|Serial||SMALLINT|9||
|aut_CreditCardVanco|Creditcardvanco||VARCHAR|50||
|aut_AccountVanco|AccountVanco||VARCHAR|50||
## Table: canvassdata_can<a name="canvassdata-can"></a>
[Table of Contents](#TOC)

### Description:
this contains information about the results of canvassing families
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|can_ID|Id| [PK]|SMALLINT|9||
|can_famID|FamilyId||SMALLINT|9||
|can_Canvasser|Canvasser||SMALLINT|9||
|can_FYID|Fyid||SMALLINT|9||
|can_date|Date||DATE|||
|can_Positive|Positive||LONGVARCHAR|||
|can_Critical|Critical||LONGVARCHAR|||
|can_Insightful|Insightful||LONGVARCHAR|||
|can_Financial|Financial||LONGVARCHAR|||
|can_Suggestion|Suggestion||LONGVARCHAR|||
|can_NotInterested|NotInterested||BOOLEAN|1||
|can_WhyNotInterested|WhyNotInterested||LONGVARCHAR|||
## Table: config_cfg<a name="config-cfg"></a>
[Table of Contents](#TOC)

### Description:
This table contains all non-default configuration parameter names and values
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|cfg_id|Id| [PK]|INTEGER|||
|cfg_name|Name||VARCHAR|50||
|cfg_value|Value||LONGVARCHAR|||
## Table: deposit_dep<a name="deposit-dep"></a>
[Table of Contents](#TOC)

### Description:
This records deposits / payments
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|dep_ID|Id| [PK]|SMALLINT|9||
|dep_Date|Date||DATE|||
|dep_Comment|Comment||LONGVARCHAR|||
|dep_EnteredBy|Enteredby||SMALLINT|9||
|dep_Closed|Closed||BOOLEAN|1||
|dep_Type|Type||CHAR|||
## Table: donateditem_di<a name="donateditem-di"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|di_ID|Id| [PK]|SMALLINT|9||
|di_item|Item||VARCHAR|32||
|di_FR_ID|FrId||SMALLINT|9||
|di_donor_ID|DonorId||SMALLINT|9||
|di_buyer_ID|BuyerId||SMALLINT|9||
|di_multibuy|Multibuy||SMALLINT|1||
|di_title|Title||VARCHAR|128||
|di_description|Description||LONGVARCHAR|||
|di_sellprice|Sellprice||DECIMAL|8||
|di_estprice|Estprice||DECIMAL|8||
|di_minimum|Minimum||DECIMAL|8||
|di_materialvalue|MaterialValue||DECIMAL|8||
|di_EnteredBy|Enteredby||SMALLINT|5||
|di_EnteredDate|Entereddate||DATE|||
|di_picture|Picture||LONGVARCHAR|||
## Table: donationfund_fun<a name="donationfund-fun"></a>
[Table of Contents](#TOC)

### Description:
This contains the defined donation funds
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|fun_ID|Id| [PK]|TINYINT|3||
|fun_Active|Active||CHAR|||
|fun_Name|Name||VARCHAR|30||
|fun_Description|Description||VARCHAR|100||
## Table: egive_egv<a name="egive-egv"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|egv_egiveID|EgiveId||VARCHAR|16||
|egv_famID|FamilyId||INTEGER|||
|egv_DateEntered|DateEntered||TIMESTAMP|||
|egv_DateLastEdited|DateLastEdited||TIMESTAMP|||
|egv_EnteredBy|EnteredBy||SMALLINT|||
|egv_EditedBy|EditedBy||SMALLINT|||
## Table: email_message_pending_emp<a name="email-message-pending-emp"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|emp_usr_id|UsrId||SMALLINT|9||
|emp_to_send|ToSend||SMALLINT|5||
|emp_subject|Subject||VARCHAR|128||
|emp_message|Message||LONGVARCHAR|||
|emp_attach_name|AttachName||LONGVARCHAR|||
|emp_attach|Attach||BOOLEAN|1||
## Table: email_recipient_pending_erp<a name="email-recipient-pending-erp"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|erp_id|Id||SMALLINT|5||
|erp_usr_id|UsrId||SMALLINT|9||
|erp_num_attempt|NumAttempt||SMALLINT|5||
|erp_failed_time|FailedTime||TIMESTAMP|||
|erp_email_address|EmailAddress||VARCHAR|50||
## Table: event_attend<a name="event-attend"></a>
[Table of Contents](#TOC)

### Description:
this indicates which people attended which events
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|attend_id|AttendId| [PK]|INTEGER|||
|event_id|EventId|[FK] [events_event](#events-event)|INTEGER|||
|person_id|PersonId|[FK] [person_per](#person-per)|INTEGER|||
|checkin_date|CheckinDate||TIMESTAMP|||
|checkin_id|CheckinId||INTEGER|||
|checkout_date|CheckoutDate||TIMESTAMP|||
|checkout_id|CheckoutId||INTEGER|||
## Table: event_types<a name="event-types"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|type_id|Id| [PK]|INTEGER|||
|type_name|Name||VARCHAR|255||
|type_defstarttime|DefStartTime||TIME|||
|type_defrecurtype|DefRecurType||CHAR|||
|type_defrecurDOW|DefRecurDOW||CHAR|||
|type_defrecurDOM|DefRecurDOM||CHAR|2||
|type_defrecurDOY|DefRecurDOY||DATE|||
|type_active|Active||INTEGER|1||
|type_grpid|GroupId|[FK] [group_grp](#group-grp)|INTEGER|||
## Table: eventcountnames_evctnm<a name="eventcountnames-evctnm"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|evctnm_countid|Id||INTEGER|5||
|evctnm_eventtypeid|TypeId||SMALLINT|5||
|evctnm_countname|Name||VARCHAR|20||
|evctnm_notes|Notes||VARCHAR|20||
## Table: eventcounts_evtcnt<a name="eventcounts-evtcnt"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|evtcnt_eventid|EvtcntEventid| [PK]|INTEGER|5||
|evtcnt_countid|EvtcntCountid| [PK]|INTEGER|5||
|evtcnt_countname|EvtcntCountname||VARCHAR|20||
|evtcnt_countcount|EvtcntCountcount||INTEGER|6||
|evtcnt_notes|EvtcntNotes||VARCHAR|20||
## Table: events_event<a name="events-event"></a>
[Table of Contents](#TOC)

### Description:
This contains events
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|event_id|Id| [PK]|INTEGER|||
|event_type|Type||INTEGER|||
|event_title|Title||VARCHAR|255||
|event_desc|Desc||VARCHAR|255||
|event_text|Text||LONGVARCHAR|||
|event_start|Start||TIMESTAMP|||
|event_end|End||TIMESTAMP|||
|inactive|InActive||INTEGER|1||
|event_typename|TypeName||VARCHAR|40||
|event_grpid|GroupId|[FK] [group_grp](#group-grp)|INTEGER|||
## Table: family_custom<a name="family-custom"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|fam_ID|FamId| [PK]|SMALLINT|9||
## Table: family_custom_master<a name="family-custom-master"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|fam_custom_Order|CustomOrder||SMALLINT|||
|fam_custom_Field|CustomField||VARCHAR|5||
|fam_custom_Name|CustomName||VARCHAR|40||
|fam_custom_Special|CustomSpecial||SMALLINT|8||
|fam_custom_Side|CustomSide||CHAR|||
|fam_custom_FieldSec|CustomFieldSec||TINYINT|||
|type_ID|TypeId||TINYINT|||
## Table: family_fam<a name="family-fam"></a>
[Table of Contents](#TOC)

### Description:
This contains the main family data, including family name, family addresses, and family phone numbers
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|fam_ID|Id| [PK]|SMALLINT|9||
|fam_Name|Name||VARCHAR|50||
|fam_Address1|Address1||VARCHAR|255||
|fam_Address2|Address2||VARCHAR|255||
|fam_City|City||VARCHAR|50||
|fam_State|State||VARCHAR|50||
|fam_Zip|Zip||VARCHAR|50||
|fam_Country|Country||VARCHAR|50||
|fam_HomePhone|HomePhone||VARCHAR|30||
|fam_WorkPhone|WorkPhone||VARCHAR|30||
|fam_CellPhone|CellPhone||VARCHAR|30||
|fam_Email|Email||VARCHAR|100||
|fam_WeddingDate|Weddingdate||DATE|||
|fam_DateEntered|DateEntered||TIMESTAMP|||
|fam_DateLastEdited|DateLastEdited||TIMESTAMP|||
|fam_EnteredBy|EnteredBy||SMALLINT|5||
|fam_EditedBy|EditedBy||SMALLINT|5||
|fam_scanCheck|ScanCheck||LONGVARCHAR|||
|fam_scanCredit|ScanCredit||LONGVARCHAR|||
|fam_SendNewsLetter|SendNewsletter||CHAR|||
|fam_DateDeactivated|DateDeactivated||DATE|||
|fam_OkToCanvass|OkToCanvass||CHAR|||
|fam_Canvasser|Canvasser||SMALLINT|5||
|fam_Latitude|Latitude||DOUBLE|||
|fam_Longitude|Longitude||DOUBLE|||
|fam_Envelope|Envelope||SMALLINT|9||
## Table: fundraiser_fr<a name="fundraiser-fr"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|fr_ID|Id| [PK]|SMALLINT|9||
|fr_date|Date||DATE|||
|fr_title|Title||VARCHAR|128||
|fr_description|Description||LONGVARCHAR|||
|fr_EnteredBy|EnteredBy||SMALLINT|5||
|fr_EnteredDate|EnteredDate||DATE|||
## Table: group_grp<a name="group-grp"></a>
[Table of Contents](#TOC)

### Description:
This contains the name and description for each group, as well as foreign keys to the list of group roles
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|grp_ID|Id| [PK]|SMALLINT|8||
|grp_Type|Type|[FK] [list_lst](#list-lst)|TINYINT|||
|grp_RoleListID|RoleListId|[FK] [list_lst](#list-lst)|SMALLINT|8||
|grp_DefaultRole|DefaultRole||SMALLINT|9||
|grp_Name|Name||VARCHAR|50||
|grp_Description|Description||LONGVARCHAR|||
|grp_hasSpecialProps|HasSpecialProps||BOOLEAN|1||
|grp_active|Active||BOOLEAN|1||
|grp_include_email_export|IncludeInEmailExport||BOOLEAN|1||
## Table: groupprop_master<a name="groupprop-master"></a>
[Table of Contents](#TOC)

### Description:
This contains definitions for the group-specific fields
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|grp_ID|Id||SMALLINT|9||
|prop_ID|Id||TINYINT|3||
|prop_Field|Field||VARCHAR|5||
|prop_Name|Name||VARCHAR|40||
|prop_Description|Description||VARCHAR|60||
|type_ID|TypeId||SMALLINT|5||
|prop_Special|Special||SMALLINT|9||
|prop_PersonDisplay|PersonDisplay||CHAR|||
## Table: kioskassginment_kasm<a name="kioskassginment-kasm"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|kasm_ID|Id| [PK]|INTEGER|9||
|kasm_kdevId|KioskId|[FK] [kioskdevice_kdev](#kioskdevice-kdev)|INTEGER|9||
|kasm_AssignmentType|AssignmentType||INTEGER|9|The kiosk's current role.|
|kasm_EventId|EventId|[FK] [events_event](#events-event)|INTEGER|9|Optional.  If the current role is for event check-in, populate this value|
## Table: kioskdevice_kdev<a name="kioskdevice-kdev"></a>
[Table of Contents](#TOC)

### Description:
This contains a list of all (un)registered kiosk devices
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|kdev_ID|Id| [PK]|INTEGER|9||
|kdev_GUIDHash|GUIDHash||VARCHAR|36|SHA256 Hash of the GUID stored in the kiosk's cookie|
|kdev_Name|Name||VARCHAR|50|Name of the kiosk|
|kdev_deviceType|DeviceType||LONGVARCHAR||Kiosk device type|
|kdev_lastHeartbeat|LastHeartbeat||LONGVARCHAR||Last time the kiosk sent a heartbeat|
|kdev_Accepted|Accepted||BOOLEAN|1|Has the admin accepted the kiosk after initial registration?|
|kdev_PendingCommands|PendingCommands||LONGVARCHAR||Commands waiting to be sent to the kiosk|
## Table: list_lst<a name="list-lst"></a>
[Table of Contents](#TOC)

### Description:
This table stores the options for most of the drop down lists in churchCRM, including person classifications, family roles, group types, group roles, group-specific property types, and custom field value lists.
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|lst_ID|Id| [PK]|SMALLINT|8||
|lst_OptionID|OptionId| [PK]|SMALLINT|8||
|lst_OptionSequence|OptionSequence||TINYINT|3||
|lst_OptionName|OptionName||VARCHAR|50||
## Table: menuconfig_mcf<a name="menuconfig-mcf"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|mid|Id| [PK]|INTEGER|||
|name|Name||VARCHAR|20||
|parent|Parent||VARCHAR|20||
|ismenu|Menu||BOOLEAN|1||
|content_english|ContentEnglish||VARCHAR|100||
|content|Content||VARCHAR|100||
|uri|URI||VARCHAR|255||
|statustext|Status||VARCHAR|255||
|security_grp|SecurityGroup||VARCHAR|50||
|session_var|SessionVar||VARCHAR|50||
|session_var_in_text|SessionVarInText||BOOLEAN|1||
|session_var_in_uri|SessionVarInURI||BOOLEAN|1||
|url_parm_name|URLParmName||VARCHAR|50||
|active|Active||BOOLEAN|1||
|sortorder|SortOrder||TINYINT|3||
|icon|Icon||VARCHAR|50||
## Table: note_nte<a name="note-nte"></a>
[Table of Contents](#TOC)

### Description:
Contains all person and family notes, including the date, time, and person who entered the note
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|nte_ID|Id| [PK]|SMALLINT|8||
|nte_per_ID|PerId|[FK] [person_per](#person-per)|SMALLINT|8||
|nte_fam_ID|FamId|[FK] [family_fam](#family-fam)|SMALLINT|8||
|nte_Private|Private||SMALLINT|8||
|nte_Text|Text||LONGVARCHAR|||
|nte_DateEntered|DateEntered||TIMESTAMP|||
|nte_DateLastEdited|DateLastEdited||TIMESTAMP|||
|nte_EnteredBy|EnteredBy||SMALLINT|8||
|nte_EditedBy|EditedBy||SMALLINT|8||
|nte_Type|Type||VARCHAR|50||
## Table: person2group2role_p2g2r<a name="person-group-role-p-g-r"></a>
[Table of Contents](#TOC)

### Description:
This table stores the information of which people are in which groups, and what group role each person holds in that group
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|p2g2r_per_ID|PersonId|[FK] [person_per](#person-per)|SMALLINT|8||
|p2g2r_grp_ID|GroupId|[FK] [group_grp](#group-grp)|SMALLINT|8||
|p2g2r_rle_ID|RoleId||SMALLINT|8||
## Table: person2volunteeropp_p2vo<a name="person-volunteeropp-p-vo"></a>
[Table of Contents](#TOC)

### Description:
This table indicates which people are tied to which volunteer opportunities
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|p2vo_ID|Id| [PK]|SMALLINT|9||
|p2vo_per_ID|PersonId||SMALLINT|9||
|p2vo_vol_ID|VolunteerOpportunityId||SMALLINT|9||
## Table: person_custom<a name="person-custom"></a>
[Table of Contents](#TOC)

### Description:
Person custom fields
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|per_ID|PerId| [PK]|SMALLINT|9||
## Table: person_custom_master<a name="person-custom-master"></a>
[Table of Contents](#TOC)

### Description:
This contains definitions for the custom person fields
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|custom_Order|CustomOrder||SMALLINT|||
|custom_Field|CustomField||VARCHAR|5||
|custom_Name|CustomName||VARCHAR|40||
|custom_Special|CustomSpecial||SMALLINT|8||
|custom_Side|CustomSide||CHAR|||
|custom_FieldSec|CustomFieldSec||TINYINT|||
|type_ID|TypeId||TINYINT|||
## Table: person_per<a name="person-per"></a>
[Table of Contents](#TOC)

### Description:
This contains the main person data, including person names, person addresses, person phone numbers, and foreign keys to the family table
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|per_ID|Id| [PK]|SMALLINT|9||
|per_Title|Title||VARCHAR|50||
|per_FirstName|FirstName||VARCHAR|50||
|per_MiddleName|MiddleName||VARCHAR|50||
|per_LastName|LastName||VARCHAR|50||
|per_Suffix|Suffix||VARCHAR|50||
|per_Address1|Address1||VARCHAR|50||
|per_Address2|Address2||VARCHAR|50||
|per_City|City||VARCHAR|50||
|per_State|State||VARCHAR|50||
|per_Zip|Zip||VARCHAR|50||
|per_Country|Country||VARCHAR|50||
|per_HomePhone|HomePhone||VARCHAR|30||
|per_WorkPhone|WorkPhone||VARCHAR|30||
|per_CellPhone|CellPhone||VARCHAR|30||
|per_Email|Email||VARCHAR|50||
|per_WorkEmail|WorkEmail||VARCHAR|50||
|per_BirthMonth|BirthMonth||TINYINT|3||
|per_BirthDay|BirthDay||TINYINT|3||
|per_BirthYear|BirthYear||INTEGER|4||
|per_MembershipDate|MembershipDate||DATE|||
|per_Gender|Gender||TINYINT|1||
|per_fmr_ID|FmrId||TINYINT|3||
|per_cls_ID|ClsId||TINYINT|3||
|per_fam_ID|FamId|[FK] [family_fam](#family-fam)|SMALLINT|5||
|per_Envelope|Envelope||SMALLINT|5||
|per_DateLastEdited|DateLastEdited||TIMESTAMP|||
|per_DateEntered|DateEntered||TIMESTAMP|||
|per_EnteredBy|EnteredBy||SMALLINT|5||
|per_EditedBy|EditedBy||SMALLINT|5||
|per_FriendDate|FriendDate||DATE|||
|per_Flags|Flags||SMALLINT|9||
## Table: pledge_plg<a name="pledge-plg"></a>
[Table of Contents](#TOC)

### Description:
This contains all payment/pledge information
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|plg_plgID|Id| [PK]|SMALLINT|9||
|plg_FamID|FamId|[FK] [family_fam](#family-fam)|SMALLINT|9||
|plg_FYID|Fyid||SMALLINT|9||
|plg_date|Date||DATE|||
|plg_amount|Amount||DECIMAL|8||
|plg_schedule|Schedule||CHAR|||
|plg_method|Method||CHAR|||
|plg_comment|Comment||LONGVARCHAR|||
|plg_DateLastEdited|Datelastedited||DATE|||
|plg_EditedBy|Editedby||SMALLINT|9||
|plg_PledgeOrPayment|Pledgeorpayment||CHAR|||
|plg_fundID|Fundid|[FK] [donationfund_fun](#donationfund-fun)|TINYINT|3||
|plg_depID|Depid|[FK] [deposit_dep](#deposit-dep)|SMALLINT|9||
|plg_CheckNo|Checkno||BIGINT|16||
|plg_Problem|Problem||BOOLEAN|1||
|plg_scanString|Scanstring||LONGVARCHAR|||
|plg_aut_ID|AutId||SMALLINT|9||
|plg_aut_Cleared|AutCleared||BOOLEAN|1||
|plg_aut_ResultID|AutResultid||SMALLINT|9||
|plg_NonDeductible|Nondeductible||DECIMAL|8||
|plg_GroupKey|Groupkey||VARCHAR|64||
## Table: property_pro<a name="property-pro"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|pro_ID|ProId| [PK]|SMALLINT|8||
|pro_Class|ProClass||VARCHAR|10||
|pro_prt_ID|ProPrtId|[FK] [propertytype_prt](#propertytype-prt)|SMALLINT|8||
|pro_Name|ProName||VARCHAR|200||
|pro_Description|ProDescription||LONGVARCHAR|||
|pro_Prompt|ProPrompt||VARCHAR|255||
## Table: propertytype_prt<a name="propertytype-prt"></a>
[Table of Contents](#TOC)

### Description:
This contains all the defined property types
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|prt_ID|PrtId| [PK]|SMALLINT|9||
|prt_Class|PrtClass||VARCHAR|10||
|prt_Name|PrtName||VARCHAR|50||
|prt_Description|PrtDescription||LONGVARCHAR|||
## Table: queryparameters_qrp<a name="queryparameters-qrp"></a>
[Table of Contents](#TOC)

### Description:
defines the parameters for each query
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|qrp_ID|Id| [PK]|SMALLINT|8||
|qrp_qry_ID|QryId||SMALLINT|8||
|qrp_Type|Type||TINYINT|3||
|qrp_OptionSQL|OptionSQL||LONGVARCHAR|||
|qrp_Name|Name||VARCHAR|25||
|qrp_Description|Description||LONGVARCHAR|||
|qrp_Alias|Alias||VARCHAR|25||
|qrp_Default|Default||VARCHAR|25||
|qrp_Required|Required||TINYINT|3||
|qrp_InputBoxSize|InputBoxSize||TINYINT|3||
|qrp_Validation|Validation||VARCHAR|5||
|qrp_NumericMax|NumericMax||INTEGER|||
|qrp_NumericMin|NumericMin||INTEGER|||
|qrp_AlphaMinLength|AlphaMinLength||INTEGER|||
|qrp_AlphaMaxLength|AlphaMaxLength||INTEGER|||
## Table: record2property_r2p<a name="record-property-r-p"></a>
[Table of Contents](#TOC)

### Description:
This table indicates which persons, families, or groups are assigned specific properties and what the values of those properties are.
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|r2p_pro_ID|PropertyId|[FK] [property_pro](#property-pro)|SMALLINT|8||
|r2p_record_ID|PersonId|[FK] [person_per](#person-per)|SMALLINT|8||
|r2p_Value|PropertyValue||LONGVARCHAR|||
## Table: tokens<a name="tokens"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|token|Token| [PK]|VARCHAR|255||
|type|Type||VARCHAR|255||
|valid_until_date|ValidUntilDate||DATE|||
|reference_id|ReferenceId||INTEGER|||
|remainingUses|RemainingUses||INTEGER|||
## Table: user_usr<a name="user-usr"></a>
[Table of Contents](#TOC)

### Description:
This contains the login information and specific settings for each ChurchCRM user
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|usr_per_ID|PersonId|[FK] [person_per](#person-per)|SMALLINT|9||
|usr_Password|Password||VARCHAR|500||
|usr_NeedPasswordChange|NeedPasswordChange||BOOLEAN|1||
|usr_LastLogin|LastLogin||TIMESTAMP|||
|usr_LoginCount|LoginCount||SMALLINT|5||
|usr_FailedLogins|FailedLogins||TINYINT|3||
|usr_AddRecords|AddRecords||BOOLEAN|1||
|usr_EditRecords|EditRecords||BOOLEAN|1||
|usr_DeleteRecords|DeleteRecords||BOOLEAN|1||
|usr_MenuOptions|MenuOptions||BOOLEAN|1||
|usr_ManageGroups|ManageGroups||BOOLEAN|1||
|usr_Finance|Finance||BOOLEAN|1||
|usr_Notes|Notes||BOOLEAN|1||
|usr_Admin|Admin||BOOLEAN|1||
|usr_SearchLimit|SearchLimit||TINYINT|||
|usr_Style|Style||VARCHAR|50||
|usr_showPledges|ShowPledges||BOOLEAN|1||
|usr_showPayments|ShowPayments||BOOLEAN|1||
|usr_showSince|ShowSince||DATE|||
|usr_defaultFY|DefaultFY||SMALLINT|9||
|usr_currentDeposit|CurrentDeposit||SMALLINT|9||
|usr_UserName|UserName||VARCHAR|32||
|usr_EditSelf|EditSelf||BOOLEAN|1||
|usr_CalStart|CalStart||DATE|||
|usr_CalEnd|CalEnd||DATE|||
|usr_CalNoSchool1|CalNoSchool1||DATE|||
|usr_CalNoSchool2|CalNoSchool2||DATE|||
|usr_CalNoSchool3|CalNoSchool3||DATE|||
|usr_CalNoSchool4|CalNoSchool4||DATE|||
|usr_CalNoSchool5|CalNoSchool5||DATE|||
|usr_CalNoSchool6|CalNoSchool6||DATE|||
|usr_CalNoSchool7|CalNoSchool7||DATE|||
|usr_CalNoSchool8|CalNoSchool8||DATE|||
|usr_SearchFamily|Searchfamily||TINYINT|3||
|usr_Canvasser|Canvasser||BOOLEAN|1||
## Table: userconfig_ucfg<a name="userconfig-ucfg"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|ucfg_per_id|PeronId|[FK] [user_usr](#user-usr)|SMALLINT|9||
|ucfg_id|Id| [PK]|INTEGER|||
|ucfg_name|Name||VARCHAR|50||
|ucfg_value|Value||LONGVARCHAR|||
|ucfg_type|Type||CHAR|||
|ucfg_tooltip|Tooltip||LONGVARCHAR|||
|ucfg_permission|Permission||CHAR|||
|ucfg_cat|Cat||VARCHAR|20||
## Table: version_ver<a name="version-ver"></a>
[Table of Contents](#TOC)

### Description:
History of all version upgrades applied to this database
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|ver_ID|Id| [PK]|SMALLINT|9||
|ver_version|Version||VARCHAR|50||
|ver_update_start|UpdateStart||TIMESTAMP|||
|ver_update_end|UpdateEnd||TIMESTAMP|||
## Table: volunteeropportunity_vol<a name="volunteeropportunity-vol"></a>
[Table of Contents](#TOC)

### Description:
This contains the names and descriptions of volunteer opportunities
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|vol_ID|Id| [PK]|INTEGER|3||
|vol_Order|Order||INTEGER|3||
|vol_Active|Active||CHAR|||
|vol_Name|Name||VARCHAR|30||
|vol_Description|Description||VARCHAR|100||
## Table: whycame_why<a name="whycame-why"></a>
[Table of Contents](#TOC)

### Description:
This contains the comments related to why people came
### Columns:
|Column Name|PHP Name|PK/FK|Format|Length|Description|
|---|---|---|---|---|---|
|why_ID|Id| [PK]|SMALLINT|9||
|why_per_ID|PerId|[FK] [person_per](#person-per)|SMALLINT|9||
|why_join|Join||LONGVARCHAR|||
|why_come|Come||LONGVARCHAR|||
|why_suggest|Suggest||LONGVARCHAR|||
|why_hearOfUs|HearOfUs||LONGVARCHAR|||
