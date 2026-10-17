/**
 * labels-modal.js — the "Generate Labels" dialog (people/views/partials/labels-modal.php),
 * shared by the cart and the People Reports.
 */
document.addEventListener("DOMContentLoaded", () => {
  const presort = document.getElementById("bulkmailpresort");
  const quiet = document.getElementById("bulkmailquiet");
  const form = document.getElementById("labelsForm");
  const modal = document.getElementById("labelsModal");
  if (!form) return;

  // Quiet presort only means something when presorting.
  presort.addEventListener("change", () => {
    quiet.disabled = !presort.checked;
    if (!presort.checked) {
      quiet.checked = false;
    }
  });

  // The labels open in a new tab; close the dialog so the page underneath is usable.
  form.addEventListener("submit", () => {
    window.bootstrap.Modal.getInstance(modal)?.hide();
  });
});
