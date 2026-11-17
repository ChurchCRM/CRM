const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

function makeElement(tagName = 'DIV') {
  return {
    tagName: tagName.toUpperCase(),
    className: '',
    attributes: {},
    children: [],
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
    set textContent(val) {
      this.children = val === '' ? [] : [{ textContent: String(val), nodeType: 3 }];
    },
    get textContent() {
      return this.children
        .map((child) => (typeof child === 'string' ? child : child.textContent || ''))
        .join('');
    },
  };
}

function makeDocument() {
  return {
    createElement(tag) {
      return makeElement(tag);
    },
    createTextNode(text) {
      return { textContent: String(text), nodeType: 3 };
    },
  };
}

function loadModule() {
  const tsPath = path.join(__dirname, '../../webpack/common/inline-error.ts');
  const source = fs.readFileSync(tsPath, 'utf8');

  let compiled;
  try {
    const ts = require('typescript');
    compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
  } catch {
    // Fallback when typescript is not installed in the environment
    const withoutInterfaces = source.replace(/export interface[\s\S]*?\n\}/g, '');
    compiled = withoutInterfaces
      .replace(/:\s*HTMLElement\s*\|\s*null\s*\|\s*undefined/g, '')
      .replace(/:\s*string/g, '')
      .replace(/:\s*InlineErrorOptions\s*=\s*\{\}/g, ' = {}')
      .replace(/:\s*InlineErrorOptions/g, '')
      .replace(/\):\s*void/g, ')')
      .replace(/export function (\w+)/g, 'exports.$1 = function');
  }

  const document = makeDocument();
  const context = {
    exports: {},
    document,
    HTMLElement: function () {},
  };
  vm.runInNewContext(compiled, context);
  return {
    renderInlineError: context.exports.renderInlineError,
    clearInlineError: context.exports.clearInlineError,
    document,
  };
}

test('renders default error banner with icon, alert styling and role="alert"', () => {
  const { renderInlineError } = loadModule();
  const container = makeElement('DIV');

  renderInlineError(container, 'Failed to load event editor.');

  assert.equal(container.children.length, 1);
  const alert = container.children[0];
  assert.equal(alert.tagName, 'DIV');
  assert.equal(alert.className, 'alert alert-danger mb-0');
  assert.equal(alert.getAttribute('role'), 'alert');

  assert.equal(alert.children.length, 2);
  const icon = alert.children[0];
  assert.equal(icon.tagName, 'I');
  assert.equal(icon.className, 'fa-solid fa-triangle-exclamation me-1');

  const textNode = alert.children[1];
  assert.equal(textNode.nodeType, 3);
  assert.equal(textNode.textContent, 'Failed to load event editor.');
});

test('safely handles text with HTML without parsing it as markup (XSS protection)', () => {
  const { renderInlineError } = loadModule();
  const container = makeElement('DIV');
  const malicious = '<script>alert(1)</script><b>Bold Error</b> & "quotes"';

  renderInlineError(container, malicious);

  const alert = container.children[0];
  // The only element child should be the icon (tagName 'I'); the text must be a text node (nodeType 3)
  const elementChildren = alert.children.filter((child) => child.nodeType !== 3);
  assert.equal(elementChildren.length, 1);
  assert.equal(elementChildren[0].tagName, 'I');

  const textNode = alert.children.find((child) => child.nodeType === 3);
  assert.ok(textNode);
  assert.equal(textNode.textContent, malicious);
});

test('supports custom className and custom icon', () => {
  const { renderInlineError } = loadModule();
  const container = makeElement('DIV');

  renderInlineError(container, 'Custom error', {
    className: 'alert alert-danger mt-2',
    icon: 'fa-solid fa-circle-exclamation me-2',
  });

  const alert = container.children[0];
  assert.equal(alert.className, 'alert alert-danger mt-2');
  const icon = alert.children[0];
  assert.equal(icon.className, 'fa-solid fa-circle-exclamation me-2');
});

test('omits icon when icon option is false or empty string', () => {
  const { renderInlineError } = loadModule();
  const containerNoIcon = makeElement('DIV');
  renderInlineError(containerNoIcon, 'No icon error', { icon: false });

  const alertNoIcon = containerNoIcon.children[0];
  assert.equal(alertNoIcon.children.length, 1);
  assert.equal(alertNoIcon.children[0].nodeType, 3);

  const containerEmptyIcon = makeElement('DIV');
  renderInlineError(containerEmptyIcon, 'Empty icon error', { icon: '' });
  const alertEmptyIcon = containerEmptyIcon.children[0];
  assert.equal(alertEmptyIcon.children.length, 1);
  assert.equal(alertEmptyIcon.children[0].nodeType, 3);
});

test('clears error when empty message is passed or clearInlineError is called', () => {
  const { renderInlineError, clearInlineError } = loadModule();
  const container = makeElement('DIV');

  renderInlineError(container, 'Initial error');
  assert.equal(container.children.length, 1);

  renderInlineError(container, '');
  assert.equal(container.children.length, 0);

  renderInlineError(container, 'Another error');
  assert.equal(container.children.length, 1);

  clearInlineError(container);
  assert.equal(container.children.length, 0);
});

test('safely handles null or undefined containers without throwing', () => {
  const { renderInlineError, clearInlineError } = loadModule();

  assert.doesNotThrow(() => renderInlineError(null, 'Error'));
  assert.doesNotThrow(() => renderInlineError(undefined, 'Error'));
  assert.doesNotThrow(() => clearInlineError(null));
  assert.doesNotThrow(() => clearInlineError(undefined));
});
