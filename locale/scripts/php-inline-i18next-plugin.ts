import type { Plugin } from 'i18next-cli';

/**
 * Lets the i18next extractor read `i18next.t('…')` calls out of `.php` views.
 *
 * 234 call sites across 19 `.php` views were invisible to the extractor
 * (#9723): the `input` globs only covered `src/skin/js/**` and `webpack/**`,
 * and pointing them at `.php` on its own does not help — i18next-cli parses
 * with SWC, which cannot read PHP, so every `.php` file is skipped with
 * "No plugin handled …".
 *
 * The `onLoad` hook is the supported way in. It receives the raw file and may
 * return replacement source, which the extractor then parses as TSX. Rather
 * than trying to turn a PHP view into valid JavaScript — impossible in general,
 * because `<?php if (…) { ?>` blocks straddle JS syntax — this plugin pulls out
 * just the `i18next.t(...)` call expressions and hands those back as a tiny
 * synthetic module. That is all the extractor is looking for, and it cannot be
 * confused by the surrounding markup.
 *
 * The scanner tracks quoting and nesting so `i18next.t('a', { b: f(1) })` and
 * apostrophes inside a key (`i18next.t("Don't")`) are handled, and it abandons
 * a call whose arguments contain a `<?php` / `<?=` tag — such a key is built at
 * render time and is not statically extractable by anything.
 *
 * Commented-out calls are skipped: `blankComments` blanks `//` line comments
 * and block comments (in the inline <script> blocks and in the PHP itself)
 * before the scan, so a call left behind by a refactor cannot add a phantom
 * msgid that no live call site backs. `#` comments are deliberately NOT
 * blanked — `#` is common in unquoted markup (`href=#tab`, colour literals)
 * and treating it as a comment risks swallowing a live call, which is the very
 * failure #9723 is about.
 */

// Assembled rather than written out as one literal so that this file does not
// itself look like an i18next call site to scripts/locale-check.js.
const CALL_NAME = 'i18next.t';
const CALL_PREFIX = `${CALL_NAME}(`;

/**
 * Replace the contents of every `//` line comment and block comment with
 * spaces, keeping the string the same length so offsets and line numbers are
 * unchanged. Quote-aware, so a `//` inside a string literal survives.
 */
export function blankComments(code: string): string {
  const out = code.split('');
  let i = 0;

  while (i < code.length) {
    const char = code[i];

    if (char === "'" || char === '"' || char === '`') {
      const quote = char;
      i++;
      while (i < code.length) {
        if (code[i] === '\\') {
          i += 2;
          continue;
        }
        if (code[i] === quote) {
          i++;
          break;
        }
        i++;
      }
      continue;
    }

    if (char === '/' && code[i + 1] === '/') {
      while (i < code.length && code[i] !== '\n') {
        out[i] = ' ';
        i++;
      }
      continue;
    }

    if (char === '/' && code[i + 1] === '*') {
      const end = code.indexOf('*/', i + 2);
      const stop = end === -1 ? code.length : end + 2;
      for (; i < stop; i++) {
        if (code[i] !== '\n') out[i] = ' ';
      }
      continue;
    }

    i++;
  }

  return out.join('');
}

/**
 * Return every complete `i18next.t(...)` call expression in `code`.
 */
export function extractI18nextCalls(code: string): string[] {
  const calls: string[] = [];
  let start = code.indexOf(CALL_PREFIX);

  while (start !== -1) {
    let i = start + CALL_PREFIX.length;
    let depth = 1;
    let quote: string | null = null;
    let usable = true;

    while (i < code.length && depth > 0) {
      const char = code[i];

      if (quote !== null) {
        if (char === '\\') {
          i += 2;
          continue;
        }
        if (char === quote) {
          quote = null;
        }
      } else if (char === "'" || char === '"' || char === '`') {
        quote = char;
      } else if (char === '(') {
        depth++;
      } else if (char === ')') {
        depth--;
      } else if (char === '<' && code.startsWith('<?', i)) {
        // A PHP tag inside the arguments — the key is assembled at render
        // time, so there is nothing static to extract.
        usable = false;
        break;
      }

      i++;
    }

    if (usable && depth === 0) {
      calls.push(`${code.slice(start, i)};`);
    }

    start = code.indexOf(CALL_PREFIX, start + CALL_PREFIX.length);
  }

  return calls;
}

export function phpInlineI18nextPlugin(): Plugin {
  return {
    name: 'php-inline-i18next',
    onLoad(code: string, file: string) {
      if (!file.toLowerCase().endsWith('.php')) {
        // Leave every other extension to the extractor's own parser.
        return undefined;
      }

      // Always return a string for .php, even an empty one: that marks the
      // file as handled so the extractor parses the result instead of warning
      // that no plugin claimed it.
      return extractI18nextCalls(blankComments(code)).join('\n');
    },
  };
}
