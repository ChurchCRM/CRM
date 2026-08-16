/**
 * Pages visited during locale smoke tests.
 *
 * Adding a page to the locale test suite requires exactly ONE line here.
 * The spec and CI workflow never need to be edited to add a page.
 */
export const LOCALE_TEST_PAGES: { name: string; url: string }[] = [
  { name: 'Dashboard',         url: '/' },
  { name: 'People',            url: '/people/list' },
  { name: 'Families',          url: '/people/family/' },
  { name: 'Groups',            url: '/groups/dashboard' },
  { name: 'Events / Calendar', url: '/event/calendars' },
  { name: 'Sunday School',     url: '/groups/sundayschool/dashboard' },
  { name: 'Finance',           url: '/finance/' },
  { name: 'Reports',           url: '/groups/reports' },
  { name: 'Admin: Settings',   url: '/admin/system/church-info' },
  { name: 'Admin: Users',      url: '/admin/system/users' },
  { name: 'Admin: Debug',      url: '/admin/system/debug' },
  // ← add new pages here
];
