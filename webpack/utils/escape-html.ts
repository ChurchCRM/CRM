/**
 * Escape text for safe insertion into HTML content.
 * @param {string|null|undefined} text - The text to escape
 * @returns {string} - Text safe for use in HTML content
 */
export const escapeHtml = (text: string | null | undefined): string => {
  if (text === null || text === undefined) {
    return "";
  }
  const div = document.createElement("div");
  div.textContent = String(text);
  return div.innerHTML;
};

/**
 * Escape text for safe insertion into HTML attribute values (e.g. title="...", value="...").
 * Extends escapeHtml to also encode double and single quotes so attackers cannot
 * break out of a quoted attribute context.
 * GHSA-369j-c5w2-48m4: attribute-context escaping for HTML attribute values
 * @param {string|null|undefined} text - The text to escape
 * @returns {string} - Text safe for use inside HTML attribute values
 */
export const escapeAttribute = (text: string | null | undefined): string => {
  if (text === null || text === undefined) {
    return "";
  }
  return escapeHtml(text).replace(/"/g, "&quot;").replace(/'/g, "&#39;");
};
