/**
 * Admin Dashboard Page JavaScript
 */

import './admin-dashboard.css';

document.addEventListener('DOMContentLoaded', function () {
    // Initialize tooltips if jQuery is available
    if (typeof jQuery !== 'undefined') {
        jQuery('[data-toggle="tooltip"]').tooltip();
    }

    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
});
