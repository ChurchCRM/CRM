/**
 * Group membership API calls shared by the group page and the person page bundles.
 */

/**
 * Move an existing member to another of the group's roles.
 */
export function setMemberRole(groupId, personId, roleId) {
  return window.CRM.APIRequest({
    method: "POST",
    path: `groups/${groupId}/userRole/${personId}`,
    data: JSON.stringify({ roleID: Number(roleId) }),
  });
}
