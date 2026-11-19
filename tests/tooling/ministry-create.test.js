const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

function makeElement(tagName = 'DIV', initialClasses = []) {
  const classes = new Set(initialClasses);
  const listeners = new Map();
  const element = {
    tagName: tagName.toUpperCase(),
    className: '',
    attributes: {},
    children: [],
    value: '',
    checked: false,
    setAttribute(key, value) {
      this.attributes[key] = String(value);
    },
    getAttribute(key) {
      return this.attributes[key] || null;
    },
    appendChild(child) {
      this.children.push(child);
      return child;
    },
    replaceChildren(...newChildren) {
      this.children = [...newChildren];
    },
    addEventListener(event, listener) {
      listeners.set(event, listener);
    },
    dispatch(event) {
      listeners.get(event)?.();
    },
    classList: {
      contains(name) {
        return classes.has(name);
      },
      toggle(name, force) {
        if (force) {
          classes.add(name);
        } else {
          classes.delete(name);
        }
      },
    },
    set textContent(value) {
      this.children = value === '' ? [] : [{ textContent: String(value), nodeType: 3 }];
    },
    get textContent() {
      return this.children.map((child) => child.textContent || '').join('');
    },
  };
  return element;
}

function loadModule(sourcePath, modules, document, window) {
  const source = fs.readFileSync(sourcePath, 'utf8');
  const compiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText;
  const context = {
    exports: {},
    require: (specifier) => modules[specifier],
    document,
    window,
    i18next: { t: (key) => key },
  };
  vm.runInNewContext(compiled, context);
  return context.exports;
}

test('ministry create renders and clears its shared inline error without changing notifications', () => {
  const apiPath = path.join(__dirname, '../../webpack/ministries/ministry-create.ts');
  const helperPath = path.join(__dirname, '../../webpack/common/inline-error.ts');
  const ids = new Map([
    ['ministry-new-btn', makeElement('BUTTON')],
    ['ministry-create-save', makeElement('BUTTON')],
    ['ministry-create-form-error', makeElement('DIV', ['d-none'])],
    ['ministry-create-name', makeElement('INPUT')],
    ['ministry-create-description', makeElement('INPUT')],
    ['ministry-create-sunday-school', makeElement('INPUT')],
    ['ministryCreateModal', makeElement('DIV')],
  ]);
  const document = {
    getElementById: (id) => ids.get(id) ?? null,
    createElement: (tag) => makeElement(tag),
    createTextNode: (text) => ({ textContent: String(text), nodeType: 3 }),
  };
  const notices = [];
  let modalShown = false;
  const window = {
    bootstrap: {
      Modal: {
        getOrCreateInstance: () => ({ show: () => (modalShown = true), hide() {} }),
      },
    },
  };
  const helper = loadModule(helperPath, {}, document, window);
  const api = {
    createMinistry() {
      throw new Error('unexpected ministry creation');
    },
    errorMessage: (error) => String(error),
    notifyError: (message) => notices.push(message),
    notifySuccess() {},
  };
  const ministry = loadModule(apiPath, { './api': api, '../common/inline-error': helper }, document, window);
  ministry.initMinistryCreate();

  ids.get('ministry-create-save').dispatch('click');

  const box = ids.get('ministry-create-form-error');
  assert.equal(box.classList.contains('d-none'), false);
  assert.equal(box.children.length, 1);
  const alert = box.children[0];
  assert.equal(alert.className, 'alert alert-danger');
  assert.equal(alert.getAttribute('role'), 'alert');
  assert.equal(alert.children[0].className, 'fa-solid fa-circle-exclamation me-1');
  assert.equal(alert.children[1].textContent, 'Give the ministry a name');
  assert.deepEqual(notices, ['Give the ministry a name']);

  ids.get('ministry-new-btn').dispatch('click');

  assert.equal(box.children.length, 0);
  assert.equal(box.classList.contains('d-none'), true);
  assert.equal(modalShown, true);
});
