module.exports = {
    locales: ['en'],
    input: ['../src/skin/js/*.js', '../react/**/*.tsx'],
    output: 'locale/locales/$LOCALE/$NAMESPACE.json',
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