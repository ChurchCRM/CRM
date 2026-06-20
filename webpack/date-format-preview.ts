/**
 * Date Format Preview Module
 *
 * Provides live preview of PHP date format strings in the Church Info form.
 * Converts PHP date tokens (Y, m, d, g, G, i, a, A, …) to their corresponding
 * values using the current date/time, and renders them as a preview string.
 *
 * Named presets: US (m/d/Y), EU (d/m/Y), ISO (Y-m-d)
 */

/** Named date-format presets shown as quick-pick buttons */
export const DATE_PRESETS: ReadonlyArray<{ label: string; format: string }> = [
  { label: "US (m/d/Y)", format: "m/d/Y" },
  { label: "EU (d/m/Y)", format: "d/m/Y" },
  { label: "ISO (Y-m-d)", format: "Y-m-d" },
];

/** Named datetime presets (for the sDateTimeFormat field) */
export const DATETIME_PRESETS: ReadonlyArray<{ label: string; format: string }> = [
  { label: "US 12h", format: "m/d/Y g:i a" },
  { label: "EU 24h", format: "d/m/Y H:i" },
  { label: "ISO 24h", format: "Y-m-d H:i" },
];

/**
 * Convert a PHP date format string to a rendered date string using the given Date.
 *
 * Supported tokens: Y y m n d j H G h g i s A a D l N w
 * Unsupported tokens are passed through literally.
 */
export function formatPhpDate(format: string, date: Date): string {
  const pad = (n: number, w = 2): string => String(n).padStart(w, "0");

  const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
  const dayAbbr = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
  const monthNames = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  const year = date.getFullYear();
  const month = date.getMonth(); // 0-based
  const day = date.getDate();
  const hours = date.getHours();
  const minutes = date.getMinutes();
  const seconds = date.getSeconds();
  const dayOfWeek = date.getDay(); // 0=Sunday

  const hour12 = hours % 12 === 0 ? 12 : hours % 12;

  // ISO day of week: 1=Monday ... 7=Sunday
  const isoDay = dayOfWeek === 0 ? 7 : dayOfWeek;

  const tokens: Record<string, string> = {
    Y: String(year),
    y: String(year).slice(-2),
    m: pad(month + 1),
    n: String(month + 1),
    M: monthNames[month].slice(0, 3),
    F: monthNames[month],
    d: pad(day),
    j: String(day),
    H: pad(hours),
    G: String(hours),
    h: pad(hour12),
    g: String(hour12),
    i: pad(minutes),
    s: pad(seconds),
    A: hours < 12 ? "AM" : "PM",
    a: hours < 12 ? "am" : "pm",
    D: dayAbbr[dayOfWeek],
    l: dayNames[dayOfWeek],
    N: String(isoDay),
    w: String(dayOfWeek),
  };

  let result = "";
  let i = 0;
  while (i < format.length) {
    const ch = format[i];
    if (ch === "\\") {
      // Escaped character — emit next char literally
      i++;
      if (i < format.length) {
        result += format[i];
      }
    } else if (ch in tokens) {
      result += tokens[ch];
    } else {
      result += ch;
    }
    i++;
  }
  return result;
}

/**
 * Attach a live preview span + preset buttons beneath a date-format input.
 *
 * @param inputEl - The <input> element containing the PHP date format string
 * @param presets - Array of preset objects to render as buttons (optional)
 */
export function attachDatePreview(
  inputEl: HTMLInputElement,
  presets: ReadonlyArray<{ label: string; format: string }> = DATE_PRESETS,
): void {
  const wrapper = document.createElement("div");
  wrapper.className = "date-format-preview-wrapper mt-1";

  // Preview text span
  const previewSpan = document.createElement("small");
  previewSpan.className = "form-text text-body-secondary date-format-preview";
  wrapper.appendChild(previewSpan);

  // Preset button row
  if (presets.length > 0) {
    const btnRow = document.createElement("div");
    btnRow.className = "mt-1 d-flex flex-wrap gap-1";
    btnRow.setAttribute("aria-label", "Date format presets");

    for (const preset of presets) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "btn btn-outline-secondary btn-sm date-preset-btn";
      btn.textContent = preset.label;
      btn.dataset.format = preset.format;
      btn.setAttribute("aria-label", `Set format to ${preset.format}`);

      btn.addEventListener("click", () => {
        inputEl.value = preset.format;
        // Dispatch input event so preview updates
        inputEl.dispatchEvent(new Event("input", { bubbles: true }));
        // Dispatch change so form dirty-tracking picks it up
        inputEl.dispatchEvent(new Event("change", { bubbles: true }));
      });

      btnRow.appendChild(btn);
    }

    wrapper.appendChild(btnRow);
  }

  // Insert wrapper immediately after the input (or its wrapping element)
  inputEl.insertAdjacentElement("afterend", wrapper);

  function updatePreview(): void {
    const fmt = inputEl.value.trim();
    if (!fmt) {
      previewSpan.textContent = "";
      return;
    }
    const now = new Date();
    const rendered = formatPhpDate(fmt, now);
    previewSpan.textContent = `Preview: ${rendered}`;
  }

  inputEl.addEventListener("input", updatePreview);
  // Run immediately to show preview for pre-populated values
  updatePreview();
}

/**
 * Wire up live date previews for all date-format inputs on the Church Info page.
 * Call this once from church-info.js after DOMContentLoaded.
 */
export function initDateFormatPreviews(): void {
  // Date format fields (use DATE_PRESETS)
  const dateFields: string[] = ["sDateFormatLong", "sDateFormatNoYear", "sDatePickerFormat"];

  // DateTime fields (use DATETIME_PRESETS)
  const datetimeFields: string[] = ["sDateTimeFormat", "sDateFilenameFormat"];

  // DatePicker placeholder — no presets, just a plain preview
  const plainFields: string[] = ["sDatePickerPlaceHolder"];

  for (const id of dateFields) {
    const el = document.getElementById(id) as HTMLInputElement | null;
    if (el) {
      attachDatePreview(el, DATE_PRESETS);
    }
  }

  for (const id of datetimeFields) {
    const el = document.getElementById(id) as HTMLInputElement | null;
    if (el) {
      attachDatePreview(el, DATETIME_PRESETS);
    }
  }

  for (const id of plainFields) {
    const el = document.getElementById(id) as HTMLInputElement | null;
    if (el) {
      attachDatePreview(el, []);
    }
  }
}
