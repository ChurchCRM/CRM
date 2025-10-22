/**
 * webpack/skin-main.js
 * 
 * Entry point for authenticated UI pages
 * 
 * Imports common CSS and Bootstrap-only plugins via skin-common.js
 */

import './skin-common.js';

// CKEditor (Rich text editor for authenticated pages)
require('ckeditor4');

export {};
