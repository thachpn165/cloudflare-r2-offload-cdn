/**
 * Public JavaScript for CloudFlare R2 Offload & CDN
 */

import '../scss/public.scss';

(function () {
  'use strict';

  const CFR2OffLoadPublic = {
    init() {
      this.bindEvents();
    },

    bindEvents() {
      // Example event binding
      document.querySelectorAll('.cloudflare-r2-offload-cdn-widget').forEach((widget) => {
        widget.addEventListener('click', this.handleWidgetClick);
      });
    },

    handleWidgetClick(e) {
      // Handle widget click event
      e.preventDefault();
    },
  };

  // Initialize when DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CFR2OffLoadPublic.init());
  } else {
    CFR2OffLoadPublic.init();
  }
})();
