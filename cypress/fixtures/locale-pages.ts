/**
 * Pages visited during locale smoke tests.
 *
 * Adding a page to the locale test suite requires exactly ONE line here.
 * The spec and CI workflow never need to be edited to add a page.
 */
export const LOCALE_TEST_PAGES: { name: string; url: string }[] = [
  { name: 'Dashboard',         url: '/' },
  { name: 'People',            url: '/v2/people' },
  { name: 'Families',          url: '/v2/families' },
  { name: 'Groups',            url: '/v2/groups' },
  { name: 'Events / Calendar', url: '/event/calendars' },
  { name: 'Sunday School',     url: '/sundayschool/' },
  { name: 'Finance',           url: '/finance/' },
  { name: 'Reports',           url: '/Reports/' },
  { name: 'Admin: Settings',   url: '/admin/system/settings/' },
  { name: 'Admin: Users',      url: '/admin/users/' },
  { name: 'Admin: Debug',      url: '/admin/system/debug/' },
  // ← add new pages here
];
