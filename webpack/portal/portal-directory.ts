/**
 * Member Portal directory, when the church lets members browse it: narrow the list to the
 * entries whose names hold every word the member types, and hide letters with nothing left
 * under them. Search only, the form goes to the server and this does nothing.
 */

function filterDirectory(query: string): void {
  const words = query.trim().toLowerCase().split(/\s+/).filter(Boolean);
  const shownByLetter = new Map<string, number>();

  for (const entry of document.querySelectorAll<HTMLElement>("[data-directory-entry]")) {
    const text = entry.dataset.search ?? "";
    const match = words.every((word) => text.includes(word));
    entry.hidden = !match;
    const letter = entry.dataset.letter ?? "";
    shownByLetter.set(letter, (shownByLetter.get(letter) ?? 0) + (match ? 1 : 0));
  }

  // While filtering the headings go, so the matches fill every column.
  for (const heading of document.querySelectorAll<HTMLElement>("[data-directory-heading]")) {
    heading.hidden = words.length > 0;
  }
  for (const link of document.querySelectorAll<HTMLElement>("[data-directory-letter]")) {
    link.hidden = (shownByLetter.get(link.dataset.directoryLetter ?? "") ?? 0) === 0;
  }

  const noMatch = document.getElementById("portal-directory-no-match");
  if (noMatch) {
    noMatch.hidden = [...shownByLetter.values()].some((count) => count > 0);
  }
}

function start(): void {
  const search = document.getElementById("portal-directory-search");
  if (search instanceof HTMLInputElement && document.querySelector("[data-directory-browse]")) {
    search.addEventListener("input", () => filterDirectory(search.value));
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
