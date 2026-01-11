/**
 * Switch Business Hub AI - Admin JavaScript
 */

(function($) {
    'use strict';

    // Document ready
    $(document).ready(function() {
        SBHA_Admin.init();
    });

    // Admin module
    var SBHA_Admin = {

        init: function() {
            this.bindEvents();
            this.initCharts();
        },

        bindEvents: function() {
            // Status select change
            $(document).on('change', '.sbha-status-select', this.handleStatusChange);

            // Delete buttons
            $(document).on('click', '.sbha-delete-job', this.handleDeleteJob);
            $(document).on('click', '.sbha-delete-service', this.handleDeleteService);
            $(document).on('click', '.sbha-delete-customer', this.handleDeleteCustomer);

            // Dismiss insight
            $(document).on('click', '.sbha-dismiss-insight', this.handleDismissInsight);

            // Export report
            $(document).on('click', '.sbha-export-report', this.handleExportReport);

            // Review gap
            $(document).on('click', '.sbha-review-gap', this.handleReviewGap);

            // Duplicate service
            $(document).on('click', '.sbha-duplicate-service', this.handleDuplicateService);

            // Category filter
            $('#sbha-filter-category, #sbha-filter-status').on('change', this.handleFilter);

            // Chart period change
            $('#sbha-chart-period').on('change', this.handleChartPeriodChange);
        },

        handleStatusChange: function() {
            var $select = $(this);
            var jobId = $select.data('job-id');
            var status = $select.val();

            SBHA_Admin.ajax('update_job_status', {
                job_id: jobId,
                status: status
            }, function(response) {
                if (response.success) {
                    $select.addClass('updated');
                    setTimeout(function() {
                        $select.removeClass('updated');
                    }, 1500);
                } else {
                    alert(response.data || sbhaAdmin.strings.error);
                }
            });
        },

        handleDeleteJob: function(e) {
            e.preventDefault();
            if (!confirm(sbhaAdmin.strings.confirm_delete)) return;

            var $btn = $(this);
            var id = $btn.data('id');

            SBHA_Admin.ajax('delete_job', { job_id: id }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || sbhaAdmin.strings.error);
                }
            });
        },

        handleDeleteService: function(e) {
            e.preventDefault();
            if (!confirm(sbhaAdmin.strings.confirm_delete)) return;

            var $btn = $(this);
            var id = $btn.data('id');

            SBHA_Admin.ajax('delete_service', { service_id: id }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || sbhaAdmin.strings.error);
                }
            });
        },

        handleDeleteCustomer: function(e) {
            e.preventDefault();
            if (!confirm(sbhaAdmin.strings.confirm_delete)) return;

            var $btn = $(this);
            var id = $btn.data('id');

            SBHA_Admin.ajax('delete_customer', { customer_id: id }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || sbhaAdmin.strings.error);
                }
            });
        },

        handleDismissInsight: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            SBHA_Admin.ajax('dismiss_insight', { insight_id: id }, function(response) {
                if (response.success) {
                    $btn.closest('tr, .sbha-insight-item').fadeOut();
                }
            });
        },

        handleExportReport: function(e) {
            e.preventDefault();

            SBHA_Admin.ajax('export_report', {
                report_type: 'business',
                period: 'month',
                format: 'csv'
            }, function(response) {
                if (response.success) {
                    SBHA_Admin.downloadFile(
                        response.data.content,
                        response.data.filename,
                        response.data.mime
                    );
                }
            });
        },

        handleReviewGap: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            // Open gap review modal or redirect
            console.log('Review gap:', id);
        },

        handleDuplicateService: function(e) {
            e.preventDefault();
            var id = $(this).data('id');

            if (confirm('Duplicate this service?')) {
                window.location.href = sbhaAdmin.ajaxUrl + '?action=sbha_duplicate_service&id=' + id;
            }
        },

        handleFilter: function() {
            var category = $('#sbha-filter-category').val();
            var status = $('#sbha-filter-status').val();

            $('[data-category]').each(function() {
                var $row = $(this);
                var showCategory = !category || $row.data('category') === category;
                var showStatus = !status || $row.find('.sbha-status-badge').hasClass('status-' + status);

                if (showCategory && showStatus) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },

        handleChartPeriodChange: function() {
            var period = $(this).val();
            SBHA_Admin.updateRevenueChart(period);
        },

        initCharts: function() {
            // Revenue chart initialization is handled inline in the dashboard
        },

        updateRevenueChart: function(period) {
            $.ajax({
                url: sbhaAdmin.restUrl + 'admin/analytics',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', sbhaAdmin.restNonce);
                },
                data: { period: period },
                success: function(response) {
                    if (response.success && window.sbhaRevenueChart) {
                        var labels = response.data.revenue_chart.map(function(item) {
                            return item.date;
                        });
                        var values = response.data.revenue_chart.map(function(item) {
                            return parseFloat(item.revenue);
                        });

                        window.sbhaRevenueChart.data.labels = labels;
                        window.sbhaRevenueChart.data.datasets[0].data = values;
                        window.sbhaRevenueChart.update();
                    }
                }
            });
        },

        ajax: function(action, data, callback) {
            data = data || {};
            data.action = 'sbha_admin_action';
            data.sbha_action = action;
            data.nonce = sbhaAdmin.nonce;

            $.ajax({
                url: sbhaAdmin.ajaxUrl,
                method: 'POST',
                data: data,
                success: callback,
                error: function() {
                    alert(sbhaAdmin.strings.error);
                }
            });
        },

        downloadFile: function(base64Content, filename, mimeType) {
            var link = document.createElement('a');
            link.href = 'data:' + mimeType + ';base64,' + base64Content;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };

    // Expose to global scope
    window.SBHA_Admin = SBHA_Admin;

})(jQuery);
