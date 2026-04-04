import { defineConfig } from 'i18next-cli';

export default defineConfig({
  locales: ['en'],
  extract: {
    input: [
      'src/skin/js/*.js',
      'webpack/*.js',
      'react/**/*.tsx'
    ],
    output: 'locale/.work/locales/{{language}}/{{namespace}}.json',
    defaultNS: 'translation',
    defaultValue: '',
    keySeparator: false,
    nsSeparator: false,
    contextSeparator: '_',
    functions: ['t', '*.t'],
    transComponents: ['Trans']
  }
});
