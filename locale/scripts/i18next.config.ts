import { defineConfig } from 'i18next-cli';
import { phpInlineI18nextPlugin } from './php-inline-i18next-plugin';

export default defineConfig({
  locales: ['en'],
  extract: {
    input: [
      'src/skin/js/**/*.js',
      // .php views carry inline <script> blocks that call i18next.t().
      // php-inline-i18next-plugin lifts those calls out so SWC can read them
      // — without it every .php file is skipped unparsed (#9723).
      'src/**/*.php',
      'webpack/**/*.{js,ts,tsx}'
    ],
    // Exclusions belong here, not as '!…' entries in `input` (#9790).
    // i18next-cli hands `input` straight to node-glob, which does not
    // implement '!' negation inside a pattern array — those entries were
    // inert and the vendored/external files were scanned anyway. `ignore`
    // is glob's real exclusion option.
    ignore: [
      'src/vendor/**',
      'src/locale/vendor/**',
      'src/skin/external/**',
      'webpack/**/*.d.ts'
    ],
    output: 'locale/.work/locales/{{language}}/{{namespace}}.json',
    defaultNS: 'translation',
    defaultValue: '',
    keySeparator: false,
    nsSeparator: false,
    contextSeparator: '_',
    functions: ['t', '*.t'],
    transComponents: ['Trans']
  },
  plugins: [phpInlineI18nextPlugin()]
});
