/**
 * Event view — Add All to Cart for the Attendance card.
 */

window.CRM.onLocalesReady(() => {
  const button = document.getElementById("event-add-attendees-to-cart");
  button?.addEventListener("click", () => {
    const ids = button.dataset.personIds.split(",").map(Number).filter(Boolean);
    if (ids.length > 0) {
      window.CRM.cartManager.addPerson(ids);
    }
  });
});
