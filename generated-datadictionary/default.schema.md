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

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|aut_ID|Id|YES|SMALLINT|9||
|aut_FamID|Familyid|NO|SMALLINT|9||
|aut_EnableBankDraft|EnableBankDraft|NO|BOOLEAN|1||
|aut_EnableCreditCard|EnableCreditCard|NO|BOOLEAN|1||
|aut_NextPayDate|NextPayDate|NO|DATE|||
|aut_FYID|Fyid|NO|SMALLINT|9||
|aut_Amount|Amount|NO|DECIMAL|6||
|aut_Interval|Interval|NO|TINYINT|3||
|aut_Fund|Fund|NO|SMALLINT|6||
|aut_FirstName|FirstName|NO|VARCHAR|50||
|aut_LastName|LastName|NO|VARCHAR|50||
|aut_Address1|Address1|NO|VARCHAR|255||
|aut_Address2|Address2|NO|VARCHAR|255||
|aut_City|City|NO|VARCHAR|50||
|aut_State|State|NO|VARCHAR|50||
|aut_Zip|Zip|NO|VARCHAR|50||
|aut_Country|Country|NO|VARCHAR|50||
|aut_Phone|Phone|NO|VARCHAR|30||
|aut_Email|Email|NO|VARCHAR|100||
|aut_CreditCard|CreditCard|NO|VARCHAR|50||
|aut_ExpMonth|ExpMonth|NO|VARCHAR|2||
|aut_ExpYear|ExpYear|NO|VARCHAR|4||
|aut_BankName|BankName|NO|VARCHAR|50||
|aut_Route|Route|NO|VARCHAR|30||
|aut_Account|Account|NO|VARCHAR|30||
|aut_DateLastEdited|DateLastEdited|NO|TIMESTAMP|||
|aut_EditedBy|Editedby|NO|SMALLINT|5||
|aut_Serial|Serial|NO|SMALLINT|9||
|aut_CreditCardVanco|Creditcardvanco|NO|VARCHAR|50||
|aut_AccountVanco|AccountVanco|NO|VARCHAR|50||
## Table: canvassdata_can<a name="canvassdata-can"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|can_ID|Id|YES|SMALLINT|9||
|can_famID|FamilyId|NO|SMALLINT|9||
|can_Canvasser|Canvasser|NO|SMALLINT|9||
|can_FYID|Fyid|NO|SMALLINT|9||
|can_date|Date|NO|DATE|||
|can_Positive|Positive|NO|LONGVARCHAR|||
|can_Critical|Critical|NO|LONGVARCHAR|||
|can_Insightful|Insightful|NO|LONGVARCHAR|||
|can_Financial|Financial|NO|LONGVARCHAR|||
|can_Suggestion|Suggestion|NO|LONGVARCHAR|||
|can_NotInterested|NotInterested|NO|BOOLEAN|1||
|can_WhyNotInterested|WhyNotInterested|NO|LONGVARCHAR|||
## Table: config_cfg<a name="config-cfg"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|cfg_id|Id|YES|INTEGER|||
|cfg_name|Name|NO|VARCHAR|50||
|cfg_value|Value|NO|LONGVARCHAR|||
## Table: deposit_dep<a name="deposit-dep"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|dep_ID|Id|YES|SMALLINT|9||
|dep_Date|Date|NO|DATE|||
|dep_Comment|Comment|NO|LONGVARCHAR|||
|dep_EnteredBy|Enteredby|NO|SMALLINT|9||
|dep_Closed|Closed|NO|BOOLEAN|1||
|dep_Type|Type|NO|CHAR|||
## Table: donateditem_di<a name="donateditem-di"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|di_ID|Id|YES|SMALLINT|9||
|di_item|Item|NO|VARCHAR|32||
|di_FR_ID|FrId|NO|SMALLINT|9||
|di_donor_ID|DonorId|NO|SMALLINT|9||
|di_buyer_ID|BuyerId|NO|SMALLINT|9||
|di_multibuy|Multibuy|NO|SMALLINT|1||
|di_title|Title|NO|VARCHAR|128||
|di_description|Description|NO|LONGVARCHAR|||
|di_sellprice|Sellprice|NO|DECIMAL|8||
|di_estprice|Estprice|NO|DECIMAL|8||
|di_minimum|Minimum|NO|DECIMAL|8||
|di_materialvalue|MaterialValue|NO|DECIMAL|8||
|di_EnteredBy|Enteredby|NO|SMALLINT|5||
|di_EnteredDate|Entereddate|NO|DATE|||
|di_picture|Picture|NO|LONGVARCHAR|||
## Table: donationfund_fun<a name="donationfund-fun"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|fun_ID|Id|YES|TINYINT|3||
|fun_Active|Active|NO|CHAR|||
|fun_Name|Name|NO|VARCHAR|30||
|fun_Description|Description|NO|VARCHAR|100||
## Table: egive_egv<a name="egive-egv"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|egv_egiveID|EgiveId|NO|VARCHAR|16||
|egv_famID|FamilyId|NO|INTEGER|||
|egv_DateEntered|DateEntered|NO|TIMESTAMP|||
|egv_DateLastEdited|DateLastEdited|NO|TIMESTAMP|||
|egv_EnteredBy|EnteredBy|NO|SMALLINT|||
|egv_EditedBy|EditedBy|NO|SMALLINT|||
## Table: email_message_pending_emp<a name="email-message-pending-emp"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|emp_usr_id|UsrId|NO|SMALLINT|9||
|emp_to_send|ToSend|NO|SMALLINT|5||
|emp_subject|Subject|NO|VARCHAR|128||
|emp_message|Message|NO|LONGVARCHAR|||
|emp_attach_name|AttachName|NO|LONGVARCHAR|||
|emp_attach|Attach|NO|BOOLEAN|1||
## Table: email_recipient_pending_erp<a name="email-recipient-pending-erp"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|erp_id|Id|NO|SMALLINT|5||
|erp_usr_id|UsrId|NO|SMALLINT|9||
|erp_num_attempt|NumAttempt|NO|SMALLINT|5||
|erp_failed_time|FailedTime|NO|TIMESTAMP|||
|erp_email_address|EmailAddress|NO|VARCHAR|50||
## Table: event_attend<a name="event-attend"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|attend_id|AttendId|YES|INTEGER|||
|event_id|EventId|NO|INTEGER|||
|person_id|PersonId|NO|INTEGER|||
|checkin_date|CheckinDate|NO|TIMESTAMP|||
|checkin_id|CheckinId|NO|INTEGER|||
|checkout_date|CheckoutDate|NO|TIMESTAMP|||
|checkout_id|CheckoutId|NO|INTEGER|||
## Table: event_types<a name="event-types"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|type_id|Id|YES|INTEGER|||
|type_name|Name|NO|VARCHAR|255||
|type_defstarttime|DefStartTime|NO|TIME|||
|type_defrecurtype|DefRecurType|NO|CHAR|||
|type_defrecurDOW|DefRecurDOW|NO|CHAR|||
|type_defrecurDOM|DefRecurDOM|NO|CHAR|2||
|type_defrecurDOY|DefRecurDOY|NO|DATE|||
|type_active|Active|NO|INTEGER|1||
|type_grpid|GroupId|NO|INTEGER|||
## Table: eventcountnames_evctnm<a name="eventcountnames-evctnm"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|evctnm_countid|Id|NO|INTEGER|5||
|evctnm_eventtypeid|TypeId|NO|SMALLINT|5||
|evctnm_countname|Name|NO|VARCHAR|20||
|evctnm_notes|Notes|NO|VARCHAR|20||
## Table: eventcounts_evtcnt<a name="eventcounts-evtcnt"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|evtcnt_eventid|EvtcntEventid|YES|INTEGER|5||
|evtcnt_countid|EvtcntCountid|YES|INTEGER|5||
|evtcnt_countname|EvtcntCountname|NO|VARCHAR|20||
|evtcnt_countcount|EvtcntCountcount|NO|INTEGER|6||
|evtcnt_notes|EvtcntNotes|NO|VARCHAR|20||
## Table: events_event<a name="events-event"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|event_id|Id|YES|INTEGER|||
|event_type|Type|NO|INTEGER|||
|event_title|Title|NO|VARCHAR|255||
|event_desc|Desc|NO|VARCHAR|255||
|event_text|Text|NO|LONGVARCHAR|||
|event_start|Start|NO|TIMESTAMP|||
|event_end|End|NO|TIMESTAMP|||
|inactive|InActive|NO|INTEGER|1||
|event_typename|TypeName|NO|VARCHAR|40||
|event_grpid|GroupId|NO|INTEGER|||
## Table: family_custom<a name="family-custom"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|fam_ID|FamId|YES|SMALLINT|9||
## Table: family_custom_master<a name="family-custom-master"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|fam_custom_Order|CustomOrder|NO|SMALLINT|||
|fam_custom_Field|CustomField|NO|VARCHAR|5||
|fam_custom_Name|CustomName|NO|VARCHAR|40||
|fam_custom_Special|CustomSpecial|NO|SMALLINT|8||
|fam_custom_Side|CustomSide|NO|CHAR|||
|fam_custom_FieldSec|CustomFieldSec|NO|TINYINT|||
|type_ID|TypeId|NO|TINYINT|||
## Table: family_fam<a name="family-fam"></a>
[Table of Contents](#TOC)

### Description:
Table of Families
### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|fam_ID|Id|YES|SMALLINT|9||
|fam_Name|Name|NO|VARCHAR|50||
|fam_Address1|Address1|NO|VARCHAR|255||
|fam_Address2|Address2|NO|VARCHAR|255||
|fam_City|City|NO|VARCHAR|50||
|fam_State|State|NO|VARCHAR|50||
|fam_Zip|Zip|NO|VARCHAR|50||
|fam_Country|Country|NO|VARCHAR|50||
|fam_HomePhone|HomePhone|NO|VARCHAR|30||
|fam_WorkPhone|WorkPhone|NO|VARCHAR|30||
|fam_CellPhone|CellPhone|NO|VARCHAR|30||
|fam_Email|Email|NO|VARCHAR|100||
|fam_WeddingDate|Weddingdate|NO|DATE|||
|fam_DateEntered|DateEntered|NO|TIMESTAMP|||
|fam_DateLastEdited|DateLastEdited|NO|TIMESTAMP|||
|fam_EnteredBy|EnteredBy|NO|SMALLINT|5||
|fam_EditedBy|EditedBy|NO|SMALLINT|5||
|fam_scanCheck|ScanCheck|NO|LONGVARCHAR|||
|fam_scanCredit|ScanCredit|NO|LONGVARCHAR|||
|fam_SendNewsLetter|SendNewsletter|NO|CHAR|||
|fam_DateDeactivated|DateDeactivated|NO|DATE|||
|fam_OkToCanvass|OkToCanvass|NO|CHAR|||
|fam_Canvasser|Canvasser|NO|SMALLINT|5||
|fam_Latitude|Latitude|NO|DOUBLE|||
|fam_Longitude|Longitude|NO|DOUBLE|||
|fam_Envelope|Envelope|NO|SMALLINT|9||
## Table: fundraiser_fr<a name="fundraiser-fr"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|fr_ID|Id|YES|SMALLINT|9||
|fr_date|Date|NO|DATE|||
|fr_title|Title|NO|VARCHAR|128||
|fr_description|Description|NO|LONGVARCHAR|||
|fr_EnteredBy|EnteredBy|NO|SMALLINT|5||
|fr_EnteredDate|EnteredDate|NO|DATE|||
## Table: group_grp<a name="group-grp"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|grp_ID|Id|YES|SMALLINT|8||
|grp_Type|Type|NO|TINYINT|||
|grp_RoleListID|RoleListId|NO|SMALLINT|8||
|grp_DefaultRole|DefaultRole|NO|SMALLINT|9||
|grp_Name|Name|NO|VARCHAR|50||
|grp_Description|Description|NO|LONGVARCHAR|||
|grp_hasSpecialProps|HasSpecialProps|NO|BOOLEAN|1||
|grp_active|Active|NO|BOOLEAN|1||
|grp_include_email_export|IncludeInEmailExport|NO|BOOLEAN|1||
## Table: groupprop_master<a name="groupprop-master"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|grp_ID|Id|NO|SMALLINT|9||
|prop_ID|Id|NO|TINYINT|3||
|prop_Field|Field|NO|VARCHAR|5||
|prop_Name|Name|NO|VARCHAR|40||
|prop_Description|Description|NO|VARCHAR|60||
|type_ID|TypeId|NO|SMALLINT|5||
|prop_Special|Special|NO|SMALLINT|9||
|prop_PersonDisplay|PersonDisplay|NO|CHAR|||
## Table: kioskassginment_kasm<a name="kioskassginment-kasm"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|kasm_ID|Id|YES|INTEGER|9||
|kasm_kdevId|KioskId|NO|INTEGER|9||
|kasm_AssignmentType|AssignmentType|NO|INTEGER|9||
|kasm_EventId|EventId|NO|INTEGER|9||
## Table: kioskdevice_kdev<a name="kioskdevice-kdev"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|kdev_ID|Id|YES|INTEGER|9||
|kdev_GUIDHash|GUIDHash|NO|VARCHAR|36||
|kdev_Name|Name|NO|VARCHAR|50||
|kdev_deviceType|DeviceType|NO|LONGVARCHAR|||
|kdev_lastHeartbeat|LastHeartbeat|NO|LONGVARCHAR|||
|kdev_Accepted|Accepted|NO|BOOLEAN|1||
|kdev_PendingCommands|PendingCommands|NO|LONGVARCHAR|||
## Table: list_lst<a name="list-lst"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|lst_ID|Id|YES|SMALLINT|8||
|lst_OptionID|OptionId|YES|SMALLINT|8||
|lst_OptionSequence|OptionSequence|NO|TINYINT|3||
|lst_OptionName|OptionName|NO|VARCHAR|50||
## Table: menuconfig_mcf<a name="menuconfig-mcf"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|mid|Id|YES|INTEGER|||
|name|Name|NO|VARCHAR|20||
|parent|Parent|NO|VARCHAR|20||
|ismenu|Menu|NO|BOOLEAN|1||
|content_english|ContentEnglish|NO|VARCHAR|100||
|content|Content|NO|VARCHAR|100||
|uri|URI|NO|VARCHAR|255||
|statustext|Status|NO|VARCHAR|255||
|security_grp|SecurityGroup|NO|VARCHAR|50||
|session_var|SessionVar|NO|VARCHAR|50||
|session_var_in_text|SessionVarInText|NO|BOOLEAN|1||
|session_var_in_uri|SessionVarInURI|NO|BOOLEAN|1||
|url_parm_name|URLParmName|NO|VARCHAR|50||
|active|Active|NO|BOOLEAN|1||
|sortorder|SortOrder|NO|TINYINT|3||
|icon|Icon|NO|VARCHAR|50||
## Table: note_nte<a name="note-nte"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|nte_ID|Id|YES|SMALLINT|8||
|nte_per_ID|PerId|NO|SMALLINT|8||
|nte_fam_ID|FamId|NO|SMALLINT|8||
|nte_Private|Private|NO|SMALLINT|8||
|nte_Text|Text|NO|LONGVARCHAR|||
|nte_DateEntered|DateEntered|NO|TIMESTAMP|||
|nte_DateLastEdited|DateLastEdited|NO|TIMESTAMP|||
|nte_EnteredBy|EnteredBy|NO|SMALLINT|8||
|nte_EditedBy|EditedBy|NO|SMALLINT|8||
|nte_Type|Type|NO|VARCHAR|50||
## Table: person2group2role_p2g2r<a name="person-group-role-p-g-r"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|p2g2r_per_ID|PersonId|YES|SMALLINT|8||
|p2g2r_grp_ID|GroupId|YES|SMALLINT|8||
|p2g2r_rle_ID|RoleId|NO|SMALLINT|8||
## Table: person2volunteeropp_p2vo<a name="person-volunteeropp-p-vo"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|p2vo_ID|Id|YES|SMALLINT|9||
|p2vo_per_ID|PersonId|NO|SMALLINT|9||
|p2vo_vol_ID|VolunteerOpportunityId|NO|SMALLINT|9||
## Table: person_custom<a name="person-custom"></a>
[Table of Contents](#TOC)

### Description:
Person custom fields
### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|per_ID|PerId|YES|SMALLINT|9||
## Table: person_custom_master<a name="person-custom-master"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|custom_Order|CustomOrder|NO|SMALLINT|||
|custom_Field|CustomField|NO|VARCHAR|5||
|custom_Name|CustomName|NO|VARCHAR|40||
|custom_Special|CustomSpecial|NO|SMALLINT|8||
|custom_Side|CustomSide|NO|CHAR|||
|custom_FieldSec|CustomFieldSec|NO|TINYINT|||
|type_ID|TypeId|NO|TINYINT|||
## Table: person_per<a name="person-per"></a>
[Table of Contents](#TOC)

### Description:
Table of people
### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|per_ID|Id|YES|SMALLINT|9||
|per_Title|Title|NO|VARCHAR|50||
|per_FirstName|FirstName|NO|VARCHAR|50||
|per_MiddleName|MiddleName|NO|VARCHAR|50||
|per_LastName|LastName|NO|VARCHAR|50||
|per_Suffix|Suffix|NO|VARCHAR|50||
|per_Address1|Address1|NO|VARCHAR|50||
|per_Address2|Address2|NO|VARCHAR|50||
|per_City|City|NO|VARCHAR|50||
|per_State|State|NO|VARCHAR|50||
|per_Zip|Zip|NO|VARCHAR|50||
|per_Country|Country|NO|VARCHAR|50||
|per_HomePhone|HomePhone|NO|VARCHAR|30||
|per_WorkPhone|WorkPhone|NO|VARCHAR|30||
|per_CellPhone|CellPhone|NO|VARCHAR|30||
|per_Email|Email|NO|VARCHAR|50||
|per_WorkEmail|WorkEmail|NO|VARCHAR|50||
|per_BirthMonth|BirthMonth|NO|TINYINT|3||
|per_BirthDay|BirthDay|NO|TINYINT|3||
|per_BirthYear|BirthYear|NO|INTEGER|4||
|per_MembershipDate|MembershipDate|NO|DATE|||
|per_Gender|Gender|NO|TINYINT|1||
|per_fmr_ID|FmrId|NO|TINYINT|3||
|per_cls_ID|ClsId|NO|TINYINT|3||
|per_fam_ID|FamId|NO|SMALLINT|5||
|per_Envelope|Envelope|NO|SMALLINT|5||
|per_DateLastEdited|DateLastEdited|NO|TIMESTAMP|||
|per_DateEntered|DateEntered|NO|TIMESTAMP|||
|per_EnteredBy|EnteredBy|NO|SMALLINT|5||
|per_EditedBy|EditedBy|NO|SMALLINT|5||
|per_FriendDate|FriendDate|NO|DATE|||
|per_Flags|Flags|NO|SMALLINT|9||
## Table: pledge_plg<a name="pledge-plg"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|plg_plgID|Id|YES|SMALLINT|9||
|plg_FamID|FamId|NO|SMALLINT|9||
|plg_FYID|Fyid|NO|SMALLINT|9||
|plg_date|Date|NO|DATE|||
|plg_amount|Amount|NO|DECIMAL|8||
|plg_schedule|Schedule|NO|CHAR|||
|plg_method|Method|NO|CHAR|||
|plg_comment|Comment|NO|LONGVARCHAR|||
|plg_DateLastEdited|Datelastedited|NO|DATE|||
|plg_EditedBy|Editedby|NO|SMALLINT|9||
|plg_PledgeOrPayment|Pledgeorpayment|NO|CHAR|||
|plg_fundID|Fundid|NO|TINYINT|3||
|plg_depID|Depid|NO|SMALLINT|9||
|plg_CheckNo|Checkno|NO|BIGINT|16||
|plg_Problem|Problem|NO|BOOLEAN|1||
|plg_scanString|Scanstring|NO|LONGVARCHAR|||
|plg_aut_ID|AutId|NO|SMALLINT|9||
|plg_aut_Cleared|AutCleared|NO|BOOLEAN|1||
|plg_aut_ResultID|AutResultid|NO|SMALLINT|9||
|plg_NonDeductible|Nondeductible|NO|DECIMAL|8||
|plg_GroupKey|Groupkey|NO|VARCHAR|64||
## Table: property_pro<a name="property-pro"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|pro_ID|ProId|YES|SMALLINT|8||
|pro_Class|ProClass|NO|VARCHAR|10||
|pro_prt_ID|ProPrtId|NO|SMALLINT|8||
|pro_Name|ProName|NO|VARCHAR|200||
|pro_Description|ProDescription|NO|LONGVARCHAR|||
|pro_Prompt|ProPrompt|NO|VARCHAR|255||
## Table: propertytype_prt<a name="propertytype-prt"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|prt_ID|PrtId|YES|SMALLINT|9||
|prt_Class|PrtClass|NO|VARCHAR|10||
|prt_Name|PrtName|NO|VARCHAR|50||
|prt_Description|PrtDescription|NO|LONGVARCHAR|||
## Table: queryparameters_qrp<a name="queryparameters-qrp"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|qrp_ID|Id|YES|SMALLINT|8||
|qrp_qry_ID|QryId|NO|SMALLINT|8||
|qrp_Type|Type|NO|TINYINT|3||
|qrp_OptionSQL|OptionSQL|NO|LONGVARCHAR|||
|qrp_Name|Name|NO|VARCHAR|25||
|qrp_Description|Description|NO|LONGVARCHAR|||
|qrp_Alias|Alias|NO|VARCHAR|25||
|qrp_Default|Default|NO|VARCHAR|25||
|qrp_Required|Required|NO|TINYINT|3||
|qrp_InputBoxSize|InputBoxSize|NO|TINYINT|3||
|qrp_Validation|Validation|NO|VARCHAR|5||
|qrp_NumericMax|NumericMax|NO|INTEGER|||
|qrp_NumericMin|NumericMin|NO|INTEGER|||
|qrp_AlphaMinLength|AlphaMinLength|NO|INTEGER|||
|qrp_AlphaMaxLength|AlphaMaxLength|NO|INTEGER|||
## Table: record2property_r2p<a name="record-property-r-p"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|r2p_pro_ID|PropertyId|YES|SMALLINT|8||
|r2p_record_ID|PersonId|YES|SMALLINT|8||
|r2p_Value|PropertyValue|NO|LONGVARCHAR|||
## Table: tokens<a name="tokens"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|token|Token|YES|VARCHAR|255||
|type|Type|NO|VARCHAR|255||
|valid_until_date|ValidUntilDate|NO|DATE|||
|reference_id|ReferenceId|NO|INTEGER|||
|remainingUses|RemainingUses|NO|INTEGER|||
## Table: user_usr<a name="user-usr"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|usr_per_ID|PersonId|YES|SMALLINT|9||
|usr_Password|Password|NO|VARCHAR|500||
|usr_NeedPasswordChange|NeedPasswordChange|NO|BOOLEAN|1||
|usr_LastLogin|LastLogin|NO|TIMESTAMP|||
|usr_LoginCount|LoginCount|NO|SMALLINT|5||
|usr_FailedLogins|FailedLogins|NO|TINYINT|3||
|usr_AddRecords|AddRecords|NO|BOOLEAN|1||
|usr_EditRecords|EditRecords|NO|BOOLEAN|1||
|usr_DeleteRecords|DeleteRecords|NO|BOOLEAN|1||
|usr_MenuOptions|MenuOptions|NO|BOOLEAN|1||
|usr_ManageGroups|ManageGroups|NO|BOOLEAN|1||
|usr_Finance|Finance|NO|BOOLEAN|1||
|usr_Notes|Notes|NO|BOOLEAN|1||
|usr_Admin|Admin|NO|BOOLEAN|1||
|usr_SearchLimit|SearchLimit|NO|TINYINT|||
|usr_Style|Style|NO|VARCHAR|50||
|usr_showPledges|ShowPledges|NO|BOOLEAN|1||
|usr_showPayments|ShowPayments|NO|BOOLEAN|1||
|usr_showSince|ShowSince|NO|DATE|||
|usr_defaultFY|DefaultFY|NO|SMALLINT|9||
|usr_currentDeposit|CurrentDeposit|NO|SMALLINT|9||
|usr_UserName|UserName|NO|VARCHAR|32||
|usr_EditSelf|EditSelf|NO|BOOLEAN|1||
|usr_CalStart|CalStart|NO|DATE|||
|usr_CalEnd|CalEnd|NO|DATE|||
|usr_CalNoSchool1|CalNoSchool1|NO|DATE|||
|usr_CalNoSchool2|CalNoSchool2|NO|DATE|||
|usr_CalNoSchool3|CalNoSchool3|NO|DATE|||
|usr_CalNoSchool4|CalNoSchool4|NO|DATE|||
|usr_CalNoSchool5|CalNoSchool5|NO|DATE|||
|usr_CalNoSchool6|CalNoSchool6|NO|DATE|||
|usr_CalNoSchool7|CalNoSchool7|NO|DATE|||
|usr_CalNoSchool8|CalNoSchool8|NO|DATE|||
|usr_SearchFamily|Searchfamily|NO|TINYINT|3||
|usr_Canvasser|Canvasser|NO|BOOLEAN|1||
## Table: userconfig_ucfg<a name="userconfig-ucfg"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|ucfg_per_id|PeronId|YES|SMALLINT|9||
|ucfg_id|Id|YES|INTEGER|||
|ucfg_name|Name|NO|VARCHAR|50||
|ucfg_value|Value|NO|LONGVARCHAR|||
|ucfg_type|Type|NO|CHAR|||
|ucfg_tooltip|Tooltip|NO|LONGVARCHAR|||
|ucfg_permission|Permission|NO|CHAR|||
|ucfg_cat|Cat|NO|VARCHAR|20||
## Table: version_ver<a name="version-ver"></a>
[Table of Contents](#TOC)

### Description:
History of all version upgrades applied to this database
### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|ver_ID|Id|YES|SMALLINT|9||
|ver_version|Version|NO|VARCHAR|50||
|ver_update_start|UpdateStart|NO|TIMESTAMP|||
|ver_update_end|UpdateEnd|NO|TIMESTAMP|||
## Table: volunteeropportunity_vol<a name="volunteeropportunity-vol"></a>
[Table of Contents](#TOC)

### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|vol_ID|Id|YES|INTEGER|3||
|vol_Order|Order|NO|INTEGER|3||
|vol_Active|Active|NO|CHAR|||
|vol_Name|Name|NO|VARCHAR|30||
|vol_Description|Description|NO|VARCHAR|100||
## Table: whycame_why<a name="whycame-why"></a>
[Table of Contents](#TOC)

### Description:
Not sure
### Columns:
|Column Name|PHP Name|Primary Key|Format|Length|Description|
|---|---|---|---|---|---|
|why_ID|Id|YES|SMALLINT|9||
|why_per_ID|PerId|NO|SMALLINT|9||
|why_join|Join|NO|LONGVARCHAR|||
|why_come|Come|NO|LONGVARCHAR|||
|why_suggest|Suggest|NO|LONGVARCHAR|||
|why_hearOfUs|HearOfUs|NO|LONGVARCHAR|||
