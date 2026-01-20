/**
 * Carbon Pagination Event Handler for Drupal
 *
 * Handles cds-pagination events and navigates to the appropriate Drupal URL.
 *
 * Build this file with: npm run build:js
 * Output: assets/js/pagination-handler.js
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.carbonPagination = {
    attach: function (context, settings) {
      // Find all Carbon pagination components
      const paginationElements = context.querySelectorAll('cds-pagination');

      paginationElements.forEach((pagination) => {
        // Avoid adding listener multiple times
        if (pagination.dataset.listenerAttached) {
          return;
        }
        pagination.dataset.listenerAttached = 'true';

        // Listen for page change events
        pagination.addEventListener('cds-pagination-changed-current', (event) => {
          const newPage = event.detail.page; // 1-indexed from Carbon
          const drupalPage = newPage - 1; // Convert to 0-indexed for Drupal

          // Get current URL
          const url = new URL(window.location.href);

          // Update or set the 'page' query parameter
          if (drupalPage === 0) {
            // First page - remove page parameter
            url.searchParams.delete('page');
          } else {
            url.searchParams.set('page', drupalPage);
          }

          // Navigate to the new URL
          window.location.href = url.toString();
        });

        // Listen for page size change events
        pagination.addEventListener('cds-page-sizes-select-changed', (event) => {
          const newPageSize = event.detail.value;

          // Get current URL
          const url = new URL(window.location.href);

          // Update the items_per_page parameter
          url.searchParams.set('items_per_page', newPageSize);

          // Reset to first page when changing page size
          url.searchParams.delete('page');

          // Navigate to the new URL
          window.location.href = url.toString();
        });
      });
    }
  };

})(Drupal);
