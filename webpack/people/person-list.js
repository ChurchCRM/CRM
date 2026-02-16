/* JS for People List moved from template
   This file contains the initialization and filter wiring previously inline
   in src/v2/templates/people/person-list.php
*/
import $ from 'jquery';
import 'select2';

// Expose a global initializer for server-rendered variables
window.initializePeopleListFromServer = function(serverVars) {
  const { RoleList, PropertyList, CustomList, GroupList, filterByGender, filterByClsId, filterByFmrId, familyActiveStatus } = serverVars;

  // Classification - use DOM APIs to escape server values and prevent XSS
  if (Array.isArray(serverVars.ClassificationList)) {
    for (let i = 0; i < serverVars.ClassificationList.length; i++) {
      $('<option>').val(i).text(serverVars.ClassificationList[i]).appendTo('.filter-Classification');
    }
  }

  // Populate Role select
  if (Array.isArray(RoleList)) {
    for (let i = 0; i < RoleList.length; i++) {
      $('<option>').val(i).text(RoleList[i]).appendTo('.filter-Role');
    }
  }

  // Properties
  if (Array.isArray(PropertyList)) {
    for (let i = 0; i < PropertyList.length; i++) {
      $('<option>').val(i).text(PropertyList[i]).appendTo('.filter-Properties');
    }
  }

  // CustomList can be an object (mapping) - use the keys for the dropdown
  const CustomListKeys = Array.isArray(CustomList) ? CustomList : Object.keys(CustomList || {});
  for (let i = 0; i < CustomListKeys.length; i++) {
    $('<option>').val(i).text(CustomListKeys[i]).appendTo('.filter-Custom');
  }

  // Family Status (use server-provided, localized list when available)
  const FamilyStatusList = Array.isArray(serverVars.FamilyStatusList) ? serverVars.FamilyStatusList : ['Active', 'Inactive'];
  for (let i = 0; i < FamilyStatusList.length; i++) {
    $('<option>').val(FamilyStatusList[i]).text(FamilyStatusList[i]).appendTo('.filter-FamilyStatus');
  }

  // Apply initial selections if provided
  if (typeof filterByGender !== 'undefined' && filterByGender !== '') {
    $('.filter-Gender').val(filterByGender);
  }
  if (typeof filterByClsId !== 'undefined' && filterByClsId !== '') {
    $('.filter-Classification').val(filterByClsId);
  }
  if (typeof filterByFmrId !== 'undefined' && filterByFmrId !== '') {
    $('.filter-Role').val(filterByFmrId);
  }
  if (typeof familyActiveStatus !== 'undefined' && familyActiveStatus === 'active') {
    $('.filter-FamilyStatus').val([FamilyStatusList[0]]);
  } else if (typeof familyActiveStatus !== 'undefined' && familyActiveStatus === 'inactive') {
    $('.filter-FamilyStatus').val([FamilyStatusList[1]]);
  }

  // Groups
  if (Array.isArray(GroupList)) {
    for (let i = 0; i < GroupList.length; i++) {
      $('<option>').val(i).text(GroupList[i]).appendTo('.filter-Group');
    }
  }

  // Initialize Select2 for elements using the shared class
  $('.filter-Select2').select2({ width: 'resolve' });
};

export default window.initializePeopleListFromServer;
