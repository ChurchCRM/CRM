/* JS for People List moved from template
   This file contains the initialization and filter wiring previously inline
   in src/v2/templates/people/person-list.php
*/
import $ from "jquery";
import "tom-select/dist/css/tom-select.bootstrap5.css";
import TomSelect from "tom-select";

// Expose TomSelect to global scope for inline template initialization
window.TomSelect = TomSelect;

// Expose a global initializer for server-rendered variables
window.initializePeopleListFromServer = function (serverVars) {
  const {
    RoleList,
    PropertyList,
    CustomList,
    GroupList,
    filterByGender,
    filterByClsId,
    filterByFmrId,
    familyActiveStatus,
  } = serverVars;

  // Classification - use DOM APIs to escape server values and prevent XSS
  if (Array.isArray(serverVars.ClassificationList)) {
    for (let i = 0; i < serverVars.ClassificationList.length; i++) {
      $("<option>").val(i).text(serverVars.ClassificationList[i]).appendTo(".filter-Classification");
    }
  }

  // Populate Role select
  if (Array.isArray(RoleList)) {
    for (let i = 0; i < RoleList.length; i++) {
      $("<option>").val(i).text(RoleList[i]).appendTo(".filter-Role");
    }
  }

  // Properties
  if (Array.isArray(PropertyList)) {
    for (let i = 0; i < PropertyList.length; i++) {
      $("<option>").val(i).text(PropertyList[i]).appendTo(".filter-Properties");
    }
  }

  // CustomList can be an object (mapping) - use the keys for the dropdown
  const CustomListKeys = Array.isArray(CustomList) ? CustomList : Object.keys(CustomList || {});
  for (let i = 0; i < CustomListKeys.length; i++) {
    $("<option>").val(i).text(CustomListKeys[i]).appendTo(".filter-Custom");
  }

  // Family Status (use server-provided, localized list when available)
  const FamilyStatusList = Array.isArray(serverVars.FamilyStatusList)
    ? serverVars.FamilyStatusList
    : ["Active", "Inactive"];
  for (let i = 0; i < FamilyStatusList.length; i++) {
    $("<option>").val(FamilyStatusList[i]).text(FamilyStatusList[i]).appendTo(".filter-FamilyStatus");
  }

  // Groups
  if (Array.isArray(GroupList)) {
    for (let i = 0; i < GroupList.length; i++) {
      $("<option>").val(i).text(GroupList[i]).appendTo(".filter-Group");
    }
  }

  // Note: TomSelect initialization and initial filter value setting is handled inline in person-list.php
  // This webpack bundle only handles populating the select options
};

export default window.initializePeopleListFromServer;
