// Main entry for all common JS and CSS includes

// Import jQuery first and make it globally available
const $ = require('jquery');
window.$ = $;
window.jQuery = $;

// Now import Bootstrap which will use the global jQuery
require('bootstrap');

// Import AdminLTE and FontAwesome
require('admin-lte');
require('@fortawesome/fontawesome-free/js/all');