/**
 * Cart View — DataTable and email action initialization
 *
 * Initializes the cart listing DataTable and populates email action links
 * from /api/cart/emails endpoint.
 */

document.addEventListener("DOMContentLoaded", () => {
  // Initialize DataTable for cart listing
  const table = $("#cart-listing-table") as any;
  if (table.length) {
    table.DataTable((window.CRM?.plugin as any)?.dataTable || {});
  }

  // Populate email action links from API
  const toBtn = document.getElementById("cart-email-to") as HTMLAnchorElement | null;
  const bccBtn = document.getElementById("cart-email-bcc") as HTMLAnchorElement | null;
  const group = document.getElementById("cart-email-actions");

  if (!toBtn) {
    return;
  }

  fetch(`${window.CRM?.root || ""}/api/cart/emails`, { credentials: "same-origin" })
    .then((r) => r.json())
    .then((data: { emails: string[] }) => {
      if (!data.emails || data.emails.length === 0) {
        return;
      }
      const encoded = data.emails.map((e) => encodeURIComponent(e)).join(",");
      toBtn.href = `mailto:${encoded}`;
      if (bccBtn) {
        bccBtn.href = `mailto:?bcc=${encoded}`;
      }
      group?.classList.remove("d-none");
    })
    .catch((err) => console.error("Failed to load emails:", err));
});
