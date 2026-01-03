/**
 * RCP Content Filter - Batch User Creation Processing
 * Handles AJAX-based batch processing with live progress updates
 */

(function($) {
    'use strict';

    var RCFBatchProcessor = {
        processing: false,
        retryCount: 0,
        maxRetries: 8,
        baseRetryDelay: 2000,
        batchesCompleted: 0,
        lastProgressProcessed: 0,
        consecutiveSuccesses: 0,

        init: function() {
            // Check if batch is active on page load
            var $container = $('#rcf-batch-progress-container');

            if ($container.length && $container.data('batch-active')) {
                this.startProcessing();
            }
        },

        startProcessing: function() {
            if (this.processing) {
                return;
            }

            this.processing = true;
            this.retryCount = 0;
            this.batchesCompleted = 0;
            this.lastProgressProcessed = 0;
            this.updateStatus('Starting batch processing...', '#0073aa');

            // Small delay before first batch to let page fully render
            var self = this;
            setTimeout(function() {
                self.processBatch();
            }, 1000);
        },

        processBatch: function() {
            var self = this;

            this.updateStatus('Processing batch ' + (this.batchesCompleted + 1) + '...', '#0073aa');

            // Use XMLHttpRequest directly for more control
            var xhr = new XMLHttpRequest();
            xhr.open('POST', rcfBatchProcessing.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.timeout = 180000; // 3 minute timeout

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        var responseText = xhr.responseText;

                        // Try to find JSON in the response (in case there's extra output)
                        var jsonMatch = responseText.match(/\{[\s\S]*\}/);
                        if (jsonMatch) {
                            try {
                                var response = JSON.parse(jsonMatch[0]);

                                // Reset retry count on successful parse
                                self.retryCount = 0;

                                if (response && response.success) {
                                    if (response.data && response.data.complete) {
                                        // All batches complete!
                                        self.handleCompletion(response.data);
                                    } else if (response.data) {
                                        // Check if this is actually new progress
                                        var currentProgress = response.data.progress || 0;
                                        if (currentProgress > self.lastProgressProcessed) {
                                            self.lastProgressProcessed = currentProgress;
                                            self.batchesCompleted++;
                                            self.consecutiveSuccesses++;
                                            self.updateProgress(response.data);
                                        }

                                        // Adaptive delay: longer delay to give server breathing room
                                        // Start with 1.5s, reduce to 1s after 10 consecutive successes
                                        var batchDelay = self.consecutiveSuccesses > 10 ? 1000 : 1500;

                                        setTimeout(function() {
                                            self.processBatch();
                                        }, batchDelay);
                                    } else {
                                        self.handleRetry('Invalid response data');
                                    }
                                } else {
                                    var errorMsg = (response && response.data && response.data.message)
                                        ? response.data.message
                                        : 'Unknown server error';
                                    self.handleError(errorMsg);
                                }
                            } catch (e) {
                                self.handleRetry('JSON parse error: ' + e.message);
                            }
                        } else {
                            self.handleRetry('No valid JSON in response');
                        }
                    } else if (xhr.status === 0) {
                        // Network error or request aborted
                        self.handleRetry('Network connection failed');
                    } else {
                        self.handleRetry('HTTP error: ' + xhr.status);
                    }
                }
            };

            xhr.ontimeout = function() {
                self.handleRetry('Request timed out');
            };

            xhr.onerror = function() {
                self.handleRetry('Connection error');
            };

            // Send the request
            var params = 'action=rcf_process_user_batch&nonce=' + encodeURIComponent(rcfBatchProcessing.nonce);
            xhr.send(params);
        },

        handleRetry: function(errorMsg) {
            var self = this;

            if (this.retryCount < this.maxRetries) {
                this.retryCount++;
                // Reset consecutive successes on error
                this.consecutiveSuccesses = 0;

                // Exponential backoff: 2s, 4s, 8s, 16s, 32s, 60s, 60s, 60s (capped at 60s)
                var retryDelay = Math.min(this.baseRetryDelay * Math.pow(2, this.retryCount - 1), 60000);

                this.updateStatus(
                    'Connection issue - retrying in ' + Math.round(retryDelay / 1000) + 's (' + this.retryCount + '/' + this.maxRetries + ')...',
                    '#dba617'
                );

                setTimeout(function() {
                    self.processBatch();
                }, retryDelay);
            } else {
                this.handleError('Failed after ' + this.maxRetries + ' attempts: ' + errorMsg);
            }
        },

        updateProgress: function(data) {
            // Update progress bar width
            var percent = Math.min(data.progress_percent || 0, 100);
            $('#rcf-progress-bar').css('width', percent + '%');

            // Update percentage text
            $('#rcf-progress-percent').text(percent + '%');

            // Update progress text
            var progress = data.progress || 0;
            var total = data.total || 0;
            $('#rcf-progress-text').text(
                progress + ' of ' + total + ' users created with Customer role'
            );

            // Update batch number
            var currentBatch = data.current_batch || this.batchesCompleted;
            var totalBatches = data.total_batches || Math.ceil(total / 10);
            $('#rcf-batch-number').text(currentBatch + ' of ' + totalBatches);

            // Update failed count if any
            if (data.failed && data.failed > 0) {
                $('#rcf-failed-count').show().find('span').text(data.failed);
            }

            // Update status message
            this.updateStatus(
                'Batch ' + currentBatch + ' complete (' + progress + ' users created)',
                '#46b450'
            );
        },

        handleCompletion: function(data) {
            this.processing = false;

            // Update progress bar to 100%
            $('#rcf-progress-bar').css('width', '100%');
            $('#rcf-progress-percent').text('100%');

            // Update progress text
            var createdCount = data.created_count || 0;
            $('#rcf-progress-text').text(
                createdCount + ' users created with Customer role'
            );

            // Show completion message
            var message = 'Complete! Created ' + createdCount + ' users.';
            if (data.failed_count && data.failed_count > 0) {
                message += ' (' + data.failed_count + ' failed)';
            }
            this.updateStatus(message, '#46b450');

            // Change container to success style
            $('#rcf-batch-progress-container')
                .removeClass('notice-info')
                .addClass('notice-success')
                .css('border-left-color', '#46b450');

            // Reload page after 3 seconds to show results table
            this.updateStatus(message + ' Redirecting to results...', '#46b450');

            setTimeout(function() {
                window.location.href = window.location.href.split('?')[0] +
                    '?page=rcp-content-filter&tab=user-import&import_complete=1';
            }, 3000);
        },

        handleError: function(message) {
            this.processing = false;
            this.updateStatus('Error: ' + message, '#d63638');

            // Show error in progress container
            $('#rcf-batch-progress-container')
                .removeClass('notice-info')
                .addClass('notice-error')
                .css('border-left-color', '#d63638');

            // Update progress text to show what was completed
            if (this.batchesCompleted > 0) {
                $('#rcf-progress-text').append(
                    '<br><small>(' + this.lastProgressProcessed + ' users may have been created before the error)</small>'
                );
            }

            // Add retry button
            if (!$('#rcf-retry-button').length) {
                $('#rcf-batch-progress-container').append(
                    '<p style="margin-top: 15px;">' +
                    '<button id="rcf-retry-button" class="button button-primary">Continue Processing</button> ' +
                    '<a href="' + window.location.href.split('?')[0] + '?page=rcp-content-filter&tab=user-import" class="button">Start Over</a>' +
                    '</p>'
                );

                var self = this;
                $('#rcf-retry-button').on('click', function() {
                    $(this).prop('disabled', true).text('Continuing...');
                    self.retryCount = 0;
                    self.processing = false;

                    $('#rcf-batch-progress-container')
                        .removeClass('notice-error')
                        .addClass('notice-info')
                        .css('border-left-color', '#0073aa');

                    self.startProcessing();
                });
            }
        },

        updateStatus: function(message, color) {
            $('#rcf-status-message')
                .html(message)
                .css('color', color);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RCFBatchProcessor.init();
    });

})(jQuery);
