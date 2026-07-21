import { fetchAPIJSON } from "../api-utils";

/**
 * People Dashboard — Email collection and dropdown initialization
 *
 * Fetches mailing email lists from /api/people/emails and populates
 * email action dropdowns with mailto: links for "All People" and per-role selections.
 */

document.addEventListener("DOMContentLoaded", () => {
  const emailActionsContainer = document.getElementById("people-email-actions");
  if (!emailActionsContainer) {
    return;
  }
  const labelAll = emailActionsContainer.getAttribute("data-label-all") || "All People";

  /**
   * Creates an anchor element for a mailto link using safe DOM APIs (no innerHTML).
   */
  function createMailtoLink(href: string, label: string): HTMLAnchorElement {
    const a = document.createElement("a");
    a.className = "dropdown-item";
    a.setAttribute("href", href);
    a.setAttribute("target", "_blank");
    a.setAttribute("rel", "noopener noreferrer");
    a.textContent = label;
    return a;
  }

  /**
   * Creates a divider element.
   */
  function createDivider(): HTMLDivElement {
    const div = document.createElement("div");
    div.className = "dropdown-divider";
    return div;
  }

  /**
   * Populates a dropdown menu element with mailto links.
   * Uses safe DOM APIs — no innerHTML.
   */
  function populateMenu(menuEl: HTMLElement, emails: string[], byRole: Record<string, string[]>, prefix: string): void {
    // Clear existing content
    menuEl.textContent = "";

    // "All People" link
    const allEncoded = emails.map((e) => encodeURIComponent(e)).join(",");
    menuEl.appendChild(createMailtoLink(`${prefix}${allEncoded}`, labelAll));

    // Per-role links
    const roles = Object.keys(byRole);
    if (roles.length > 0) {
      menuEl.appendChild(createDivider());
      for (const role of roles) {
        const roleEncoded = byRole[role].map((e) => encodeURIComponent(e)).join(",");
        menuEl.appendChild(createMailtoLink(`${prefix}${roleEncoded}`, role));
      }
    }
  }

  fetchAPIJSON<{ all: string[]; byRole: Record<string, string[]> }>("people/emails")
    .then((data) => {
      if (!data.all || data.all.length === 0) {
        return;
      }

      const emailAllMenu = document.getElementById("email-all-menu");
      const emailBccMenu = document.getElementById("email-bcc-menu");

      if (emailAllMenu) {
        populateMenu(emailAllMenu, data.all, data.byRole, "mailto:");
      }
      if (emailBccMenu) {
        populateMenu(emailBccMenu, data.all, data.byRole, "mailto:?bcc=");
      }

      emailActionsContainer?.classList.remove("d-none");
    })
    .catch((err) => console.error("Failed to load emails:", err));
});

