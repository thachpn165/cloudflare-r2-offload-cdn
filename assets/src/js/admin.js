/**
 * Admin JavaScript for CloudFlare R2 Offload My Plugin CDN
 */

import '../scss/admin.scss';

(function ($) {
  'use strict';

  const CFR2OffLoadAdmin = {
    /**
     * Initialize the admin module.
     */
    init() {
      this.cacheElements();
      this.bindEvents();
      // Hide save button on Dashboard tab and Bulk Actions tab.
      $('.cloudflare-r2-offload-cdn-form-actions').hide();
      this.progressInterval = null;
      this.activityLogInterval = null;
      this.bulkStartTime = null;
    },

    /**
     * Cache DOM elements.
     */
    cacheElements() {
      this.$tabs = $('.cloudflare-r2-offload-cdn-tabs li');
      this.$tabContents = $('.cloudflare-r2-offload-cdn-tab-content');
      this.$form = $('#cloudflare-r2-offload-cdn-settings-form');
      this.$saveBtn = $('.cloudflare-r2-offload-cdn-save-btn');
      this.$toast = $('#cloudflare-r2-offload-cdn-toast');
    },

    /**
     * Bind event handlers.
     */
    bindEvents() {
      this.$tabs.on('click', this.handleTabClick.bind(this));
      this.$form.on('submit', this.handleFormSubmit.bind(this));
      $(document).on('click', '.cloudflare-r2-offload-cdn-accordion-header', this.handleAccordionClick);
      $('#test-r2-connection').on('click', this.handleTestR2Connection.bind(this));
      $('#cfr2-bulk-offload-all').on('click', this.handleBulkOffload.bind(this));
      $('#cfr2-cancel-bulk').on('click', this.handleCancelBulk.bind(this));
      $('#cdn_enabled').on('change', this.handleCDNToggle.bind(this));
      $('#quality').on('input', this.handleQualityChange.bind(this));
      $('#deploy-worker').on('click', this.handleDeployWorker.bind(this));
      $('#remove-worker').on('click', this.handleRemoveWorker.bind(this));
      $('#cfr2-retry-all-failed').on('click', this.handleRetryFailed.bind(this));
      $('#cfr2-retry-all').on('click', this.handleRetryFailed.bind(this));
      $('#cfr2-clear-log').on('click', this.handleClearLog.bind(this));
      $(document).on('click', '.cfr2-retry-single', this.handleRetrySingle.bind(this));
      $('#goto-bulk-actions').on('click', this.handleGotoBulkActions.bind(this));
    },

    /**
     * Handle tab click.
     *
     * @param {Event} e Click event.
     */
    handleTabClick(e) {
      const $tab = $(e.currentTarget);
      const tabId = $tab.data('tab');

      // Update active tab.
      this.$tabs.removeClass('active');
      $tab.addClass('active');

      // Show corresponding content.
      this.$tabContents.removeClass('active');
      $('#tab-' + tabId).addClass('active');

      // Hide save button on Dashboard tab and Bulk Actions tab.
      $('.cloudflare-r2-offload-cdn-form-actions').toggle(tabId !== 'dashboard' && tabId !== 'bulk-actions');

      // Load activity log if on bulk actions tab.
      if (tabId === 'bulk-actions') {
        this.loadActivityLog();
        this.checkBulkProgress();
      }
    },

    /**
     * Handle accordion header click.
     *
     * @param {Event} e Click event.
     */
    handleAccordionClick(e) {
      const $header = $(e.currentTarget);
      const $content = $header.next('.cloudflare-r2-offload-cdn-accordion-content');

      $header.toggleClass('active');
      $content.toggleClass('active');
    },

    /**
     * Handle form submit (AJAX save).
     *
     * @param {Event} e Submit event.
     */
    handleFormSubmit(e) {
      e.preventDefault();

      if (this.$saveBtn.hasClass('is-loading')) {
        return;
      }

      this.setLoading(true);

      const formData = this.$form.serialize();

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: formData + '&action=cloudflare_r2_offload_cdn_save_settings',
        success: this.handleSaveSuccess.bind(this),
        error: this.handleSaveError.bind(this),
        complete: () => this.setLoading(false),
      });
    },

    /**
     * Handle successful save.
     *
     * @param {Object} response AJAX response.
     */
    handleSaveSuccess(response) {
      if (response.success) {
        this.showToast(response.data.message, 'success');
      } else {
        this.showToast(response.data.message || myPluginAdmin.strings.error, 'error');
      }
    },

    /**
     * Handle save error.
     */
    handleSaveError() {
      this.showToast(myPluginAdmin.strings.error, 'error');
    },

    /**
     * Set loading state.
     *
     * @param {boolean} isLoading Loading state.
     */
    setLoading(isLoading) {
      this.$saveBtn.toggleClass('is-loading', isLoading);
    },

    /**
     * Show toast notification.
     *
     * @param {string} message Toast message.
     * @param {string} type    Toast type (success|error).
     */
    showToast(message, type = 'success') {
      const icon = type === 'success' ? 'yes-alt' : 'warning';
      const $toast = $('<div>', { class: `cloudflare-r2-offload-cdn-toast-item ${type}` }).append(
        $('<span>', { class: `dashicons dashicons-${icon}` }),
        $('<span>').text(message) // Use .text() to prevent XSS
      );

      this.$toast.append($toast);

      // Auto-remove after 3 seconds.
      setTimeout(() => {
        $toast.addClass('fade-out');
        setTimeout(() => $toast.remove(), 300);
      }, 3000);
    },

    /**
     * Handle R2 connection test.
     *
     * @param {Event} e Click event.
     */
    handleTestR2Connection(e) {
      e.preventDefault();

      const $btn = $(e.currentTarget);
      const $result = $('#r2-connection-result');

      if ($btn.hasClass('is-loading')) {
        return;
      }

      $btn.addClass('is-loading').prop('disabled', true);
      $result.html('<span class="spinner is-active" style="float:none;margin:0 5px;"></span>');

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cloudflare_r2_offload_cdn_test_r2',
          cloudflare_r2_offload_cdn_nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success) {
            $result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
            this.showToast(response.data.message, 'success');
          } else {
            $result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
            this.showToast(response.data.message, 'error');
          }
        },
        error: () => {
          $result.html('<span style="color: #dc3232;">✗ Connection failed</span>');
          this.showToast('Connection test failed', 'error');
        },
        complete: () => {
          $btn.removeClass('is-loading').prop('disabled', false);
        },
      });
    },

    /**
     * Handle bulk offload.
     *
     * @param {Event} e Click event.
     */
    handleBulkOffload(e) {
      e.preventDefault();

      if (!confirm('Queue all media files for offload to R2?')) {
        return;
      }

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_bulk_offload_all',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success) {
            this.showToast(response.data.message, 'success');
            this.startProgressPolling();
          } else {
            this.showToast(response.data.message, 'error');
          }
        },
        error: () => {
          this.showToast('Failed to start bulk offload', 'error');
        },
      });
    },

    /**
     * Handle cancel bulk.
     *
     * @param {Event} e Click event.
     */
    handleCancelBulk(e) {
      e.preventDefault();

      if (!confirm('Cancel bulk offload?')) {
        return;
      }

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_cancel_bulk',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success) {
            this.showToast(response.data.message, 'success');
            this.stopProgressPolling();
          } else {
            this.showToast(response.data.message, 'error');
          }
        },
        error: () => {
          this.showToast('Failed to cancel bulk offload', 'error');
        },
      });
    },

    /**
     * Start progress polling.
     */
    startProgressPolling() {
      $('#cfr2-bulk-offload-all').hide();
      $('#cfr2-retry-all-failed').hide();
      $('#cfr2-cancel-bulk').show();
      $('#cfr2-bulk-progress-section').show();

      this.bulkStartTime = Date.now();

      this.progressInterval = setInterval(() => {
        this.updateProgress();
      }, 2000);

      // Start activity log polling.
      this.activityLogInterval = setInterval(() => {
        this.loadActivityLog();
      }, 3000);

      this.updateProgress();
      this.loadActivityLog();
    },

    /**
     * Stop progress polling.
     */
    stopProgressPolling() {
      if (this.progressInterval) {
        clearInterval(this.progressInterval);
        this.progressInterval = null;
      }

      if (this.activityLogInterval) {
        clearInterval(this.activityLogInterval);
        this.activityLogInterval = null;
      }

      $('#cfr2-bulk-offload-all').show();
      $('#cfr2-retry-all-failed').show();
      $('#cfr2-cancel-bulk').hide();
      $('#cfr2-bulk-progress-section').hide();

      this.bulkStartTime = null;
    },

    /**
     * Update progress.
     */
    updateProgress() {
      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_get_bulk_progress',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success) {
            const data = response.data;
            const percentage = data.total > 0 ? Math.round(((data.completed + data.failed) / data.total) * 100) : 0;

            $('.cfr2-progress-fill').css('width', percentage + '%');
            $('.cfr2-progress-percentage').text(percentage + '%');
            $('.cfr2-progress-text').html(
              `<span style="color: #46b450;">✓ ${data.completed} completed</span> | ` +
              `<span style="color: #dc3232;">✗ ${data.failed} failed</span> | ` +
              `<span style="color: #646970;">○ ${data.pending + data.processing} remaining</span>`
            );

            // Update current file.
            if (data.current_file) {
              $('#cfr2-current-file').text(data.current_file);
            } else {
              $('#cfr2-current-file').text('—');
            }

            // Update elapsed time.
            if (this.bulkStartTime) {
              const elapsed = Math.floor((Date.now() - this.bulkStartTime) / 1000);
              const minutes = Math.floor(elapsed / 60);
              const seconds = elapsed % 60;
              $('#cfr2-elapsed').text(`${minutes}m ${seconds}s`);
            }

            if (!data.is_running) {
              this.stopProgressPolling();
              this.showToast('Bulk offload completed', 'success');
              this.loadActivityLog();
              this.loadFailedItems();
            }
          }
        },
      });
    },

    /**
     * Handle CDN toggle.
     *
     * @param {Event} e Change event.
     */
    handleCDNToggle(e) {
      const isEnabled = $(e.currentTarget).is(':checked');
      $('.cdn-fields').toggle(isEnabled);
    },

    /**
     * Handle quality slider change.
     *
     * @param {Event} e Input event.
     */
    handleQualityChange(e) {
      const value = $(e.currentTarget).val();
      $('#quality-value').text(value);
    },

    /**
     * Handle deploy worker.
     *
     * @param {Event} e Click event.
     */
    handleDeployWorker(e) {
      e.preventDefault();

      const $btn = $(e.currentTarget);
      const $result = $('#worker-deploy-result');

      if ($btn.hasClass('is-loading')) {
        return;
      }

      $btn.addClass('is-loading').prop('disabled', true);
      $result.html('<span class="spinner is-active" style="float:none;margin:0 5px;"></span>');

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_deploy_worker',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success) {
            $result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
            this.showToast(response.data.message, 'success');
            $('#remove-worker').show();
            // Refresh page to update status
            setTimeout(() => location.reload(), 1500);
          } else {
            $result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
            this.showToast(response.data.message, 'error');
          }
        },
        error: () => {
          $result.html('<span style="color: #dc3232;">✗ Deployment failed</span>');
          this.showToast('Worker deployment failed', 'error');
        },
        complete: () => {
          $btn.removeClass('is-loading').prop('disabled', false);
        },
      });
    },

    /**
     * Handle remove worker.
     *
     * @param {Event} e Click event.
     */
    handleRemoveWorker(e) {
      e.preventDefault();

      if (!confirm('Remove deployed Worker? This will disable CDN URL rewriting.')) {
        return;
      }

      const $btn = $(e.currentTarget);
      const $result = $('#worker-deploy-result');

      if ($btn.hasClass('is-loading')) {
        return;
      }

      $btn.addClass('is-loading').prop('disabled', true);
      $result.html('<span class="spinner is-active" style="float:none;margin:0 5px;"></span>');

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_remove_worker',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success) {
            $result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
            this.showToast(response.data.message, 'success');
            $btn.hide();
            // Refresh page to update status
            setTimeout(() => location.reload(), 1500);
          } else {
            $result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
            this.showToast(response.data.message, 'error');
          }
        },
        error: () => {
          $result.html('<span style="color: #dc3232;">✗ Remove failed</span>');
          this.showToast('Worker removal failed', 'error');
        },
        complete: () => {
          $btn.removeClass('is-loading').prop('disabled', false);
        },
      });
    },

    /**
     * Load activity log.
     */
    loadActivityLog() {
      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_get_activity_log',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
          limit: 20,
        },
        success: (response) => {
          if (response.success && response.data.logs.length > 0) {
            const $log = $('#cfr2-activity-log');
            $log.empty();

            response.data.logs.forEach((entry) => {
              const statusIcon = entry.status === 'success' ? '✓' : '✗';
              const statusColor = entry.status === 'success' ? '#46b450' : '#dc3232';
              const time = new Date(entry.timestamp * 1000).toLocaleTimeString();

              const $entry = $(`<div class="cfr2-log-entry cfr2-log-${entry.status}">
                <span class="cfr2-log-time">${time}</span>
                <span class="cfr2-log-status" style="color: ${statusColor};">${statusIcon}</span>
                <span class="cfr2-log-filename">${entry.filename}</span>
                <span class="cfr2-log-message">${entry.message}</span>
              </div>`);

              $log.append($entry);
            });

            // Auto-scroll to bottom.
            $log.scrollTop($log[0].scrollHeight);
          }
        },
      });
    },

    /**
     * Check bulk progress on page load.
     */
    checkBulkProgress() {
      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_get_bulk_progress',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success && response.data.is_running) {
            this.startProgressPolling();
          }
        },
      });
    },

    /**
     * Load failed items.
     */
    loadFailedItems() {
      // This would query failed items from queue and display in error summary.
      // For now, we'll check if there are any failed items via the retry button visibility.
    },

    /**
     * Handle retry failed.
     *
     * @param {Event} e Click event.
     */
    handleRetryFailed(e) {
      e.preventDefault();

      if (!confirm('Retry all failed items?')) {
        return;
      }

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_retry_failed',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success) {
            this.showToast(response.data.message, 'success');
            this.startProgressPolling();
          } else {
            this.showToast(response.data.message, 'error');
          }
        },
        error: () => {
          this.showToast('Failed to retry items', 'error');
        },
      });
    },

    /**
     * Handle retry single.
     *
     * @param {Event} e Click event.
     */
    handleRetrySingle(e) {
      e.preventDefault();

      const attachmentId = $(e.currentTarget).data('attachment-id');

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_retry_single',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
          attachment_id: attachmentId,
        },
        success: (response) => {
          if (response.success) {
            this.showToast(response.data.message, 'success');
            $(e.currentTarget).closest('.cfr2-error-item').fadeOut();
          } else {
            this.showToast(response.data.message, 'error');
          }
        },
        error: () => {
          this.showToast('Failed to retry item', 'error');
        },
      });
    },

    /**
     * Handle clear log.
     *
     * @param {Event} e Click event.
     */
    handleClearLog(e) {
      e.preventDefault();

      if (!confirm('Clear activity log?')) {
        return;
      }

      $.ajax({
        url: myPluginAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cfr2_clear_log',
          nonce: $('#cloudflare_r2_offload_cdn_nonce').val(),
        },
        success: (response) => {
          if (response.success) {
            this.showToast(response.data.message, 'success');
            $('#cfr2-activity-log').html('<p class="cfr2-no-data">No recent activity.</p>');
          } else {
            this.showToast(response.data.message, 'error');
          }
        },
        error: () => {
          this.showToast('Failed to clear log', 'error');
        },
      });
    },

    /**
     * Handle goto bulk actions tab.
     *
     * @param {Event} e Click event.
     */
    handleGotoBulkActions(e) {
      e.preventDefault();

      // Trigger tab click.
      $('.cloudflare-r2-offload-cdn-tabs li[data-tab="bulk-actions"]').trigger('click');
    },
  };

  // Initialize when DOM ready.
  $(document).ready(() => {
    CFR2OffLoadAdmin.init();
  });
})(jQuery);
