/**
 * "My ministries and teams" (#9711) — the module index.
 *
 * The page is server-rendered and had no bundle at all until the guided setup
 * wizard was removed; the one thing it now needs a bundle for is the "New
 * ministry" modal that replaced the wizard's first step. Everything else on the
 * page is still plain markup from the route.
 */

import { initMinistryCreate } from "./ministry-create";

document.addEventListener("DOMContentLoaded", () => {
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(initMinistryCreate);
  } else {
    initMinistryCreate();
  }
});
