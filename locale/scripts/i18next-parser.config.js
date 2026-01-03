const path = require('path');

const projectRoot = path.resolve(__dirname, '../..');

module.exports = {
    locales: ['en'],
    input: [
        path.join(projectRoot, 'src/skin/js/*.js'),
        path.join(projectRoot, 'webpack/*.js'),
        path.join(projectRoot, 'react/**/*.tsx')
    ],
    output: path.join(projectRoot, 'locale/locales/$LOCALE/$NAMESPACE.json'),
    sort: true,
    createOldCatalogs: false,
    keepRemoved: false,
    keySeparator: false,
    namespaceSeparator: false,
    resetDefaultValueLocale: 'en',
    i18nextOptions: null,
    pluralSeparator: '_',
    contextSeparator: '_', 
    defaultNamespace: 'translation',
    lexers: {
        tsx: ['JsxLexer'],
        default: ['JavascriptLexer']
      }
  };