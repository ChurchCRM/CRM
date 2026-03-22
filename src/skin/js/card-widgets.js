/**
 * Bootstrap 5 Card Widget Handler
 * Handles collapse and remove buttons for cards using data-card-widget attribute
 * Replaces deprecated AdminLTE card widget functionality
 */
(function () {
  'use strict';

  // Handle card widget buttons
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-card-widget]');
    
    if (!btn) return;

    const cardWidget = btn.getAttribute('data-card-widget');
    const card = btn.closest('.card');

    if (!card) return;

    switch (cardWidget) {
      case 'collapse':
        handleCardCollapse(card, btn);
        break;
      case 'remove':
        handleCardRemove(card);
        break;
      default:
        break;
    }
  });

  /**
   * Toggle card collapse state
   */
  function handleCardCollapse(card, btn) {
    const cardBody = card.querySelector('.card-body');
    const cardFooter = card.querySelector('.card-footer');
    const isCollapsed = card.classList.contains('collapsed-card');

    if (isCollapsed) {
      // Expand
      card.classList.remove('collapsed-card');
      if (cardBody) cardBody.style.display = '';
      if (cardFooter) cardFooter.style.display = '';
      
      // Update icon - toggle between minus and plus
      const icon = btn.querySelector('i');
      if (icon) {
        icon.classList.remove('fa-plus');
        icon.classList.add('fa-minus');
      }
    } else {
      // Collapse
      card.classList.add('collapsed-card');
      if (cardBody) cardBody.style.display = 'none';
      if (cardFooter) cardFooter.style.display = 'none';
      
      // Update icon - toggle between minus and plus
      const icon = btn.querySelector('i');
      if (icon) {
        icon.classList.remove('fa-minus');
        icon.classList.add('fa-plus');
      }
    }
  }

  /**
   * Remove card from DOM and animate if Bootstrap is available
   */
  function handleCardRemove(card) {
    // Use Bootstrap 5 fade animation if available
    card.addEventListener('hidden.bs.fade', function () {
      card.remove();
    }, { once: true });

    // Add fade animation and hide
    card.classList.add('fade');
    card.style.opacity = '0';
    setTimeout(() => {
      card.remove();
    }, 300);
  }
})();
