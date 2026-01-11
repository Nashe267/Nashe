<?php
/**
 * Admin Dashboard
 *
 * Main admin dashboard with AI insights
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Admin_Dashboard {

    /**
     * Render dashboard
     */
    public function render() {
        $welcome = isset($_GET['welcome']) && $_GET['welcome'] == '1';
        $stats = SBHA()->get_analytics()->get_dashboard_stats();
        $insights = SBHA_Insights::get_active_insights(5);
        $recent_jobs = SBHA()->get_job_manager()->get_jobs(array('limit' => 5));
        $gaps = SBHA_Insights::get_service_gaps(3);
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                <span class="sbha-logo">Switch Business Hub</span>
                <span class="sbha-version">v<?php echo esc_html(SBHA_VERSION); ?></span>
            </h1>

            <?php if ($welcome): ?>
            <div class="sbha-welcome-banner">
                <h2>Welcome to Switch Business Hub AI!</h2>
                <p>Your AI-powered business management system is now active. Start by adding your services and let the AI help you grow your business.</p>
                <div class="sbha-welcome-actions">
                    <a href="<?php echo admin_url('admin.php?page=sbha-services'); ?>" class="button button-primary">Manage Services</a>
                    <a href="<?php echo admin_url('admin.php?page=sbha-settings'); ?>" class="button">Configure Settings</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="sbha-stats-grid">
                <div class="sbha-stat-card">
                    <div class="sbha-stat-icon revenue"></div>
                    <div class="sbha-stat-content">
                        <span class="sbha-stat-value"><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($stats['month']['revenue'], 2); ?></span>
                        <span class="sbha-stat-label">Monthly Revenue</span>
                    </div>
                </div>
                <div class="sbha-stat-card">
                    <div class="sbha-stat-icon jobs"></div>
                    <div class="sbha-stat-content">
                        <span class="sbha-stat-value"><?php echo esc_html($stats['month']['total_jobs']); ?></span>
                        <span class="sbha-stat-label">Monthly Jobs</span>
                    </div>
                </div>
                <div class="sbha-stat-card">
                    <div class="sbha-stat-icon customers"></div>
                    <div class="sbha-stat-content">
                        <span class="sbha-stat-value"><?php echo esc_html($stats['month']['new_customers']); ?></span>
                        <span class="sbha-stat-label">New Customers</span>
                    </div>
                </div>
                <div class="sbha-stat-card">
                    <div class="sbha-stat-icon conversion"></div>
                    <div class="sbha-stat-content">
                        <span class="sbha-stat-value"><?php echo esc_html($stats['month']['conversion_rate']); ?>%</span>
                        <span class="sbha-stat-label">Conversion Rate</span>
                    </div>
                </div>
            </div>

            <div class="sbha-dashboard-grid">
                <!-- AI Insights Panel -->
                <div class="sbha-panel sbha-insights-panel">
                    <div class="sbha-panel-header">
                        <h2>AI Insights</h2>
                        <a href="<?php echo admin_url('admin.php?page=sbha-insights'); ?>" class="sbha-view-all">View All</a>
                    </div>
                    <div class="sbha-panel-content">
                        <?php if (empty($insights)): ?>
                            <p class="sbha-no-data">No active insights. AI is learning from your data.</p>
                        <?php else: ?>
                            <ul class="sbha-insights-list">
                                <?php foreach ($insights as $insight): ?>
                                    <?php $data = json_decode($insight['insight_data'], true); ?>
                                    <li class="sbha-insight-item severity-<?php echo esc_attr($insight['severity']); ?>">
                                        <span class="sbha-insight-icon"></span>
                                        <div class="sbha-insight-content">
                                            <strong><?php echo esc_html($insight['insight_title']); ?></strong>
                                            <span class="sbha-insight-date"><?php echo human_time_diff(strtotime($insight['created_at']), current_time('timestamp')); ?> ago</span>
                                        </div>
                                        <?php if ($insight['action_required']): ?>
                                            <span class="sbha-badge action-required">Action Required</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="sbha-panel sbha-chart-panel">
                    <div class="sbha-panel-header">
                        <h2>Revenue Overview</h2>
                        <select id="sbha-chart-period" class="sbha-select">
                            <option value="week">Last 7 Days</option>
                            <option value="month" selected>Last 30 Days</option>
                            <option value="quarter">Last 90 Days</option>
                        </select>
                    </div>
                    <div class="sbha-panel-content">
                        <canvas id="sbha-revenue-chart" height="250"></canvas>
                    </div>
                </div>

                <!-- Recent Jobs -->
                <div class="sbha-panel sbha-jobs-panel">
                    <div class="sbha-panel-header">
                        <h2>Recent Jobs</h2>
                        <a href="<?php echo admin_url('admin.php?page=sbha-jobs'); ?>" class="sbha-view-all">View All</a>
                    </div>
                    <div class="sbha-panel-content">
                        <?php if (empty($recent_jobs)): ?>
                            <p class="sbha-no-data">No jobs yet.</p>
                        <?php else: ?>
                            <table class="sbha-table">
                                <thead>
                                    <tr>
                                        <th>Job #</th>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_jobs as $job): ?>
                                        <tr>
                                            <td><a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=view&id=' . $job['id']); ?>"><?php echo esc_html($job['job_number']); ?></a></td>
                                            <td><?php echo esc_html(substr($job['title'], 0, 30)); ?></td>
                                            <td><span class="sbha-status-badge status-<?php echo esc_attr($job['job_status']); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $job['job_status']))); ?></span></td>
                                            <td><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($job['total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Service Gaps (Business Opportunities) -->
                <div class="sbha-panel sbha-gaps-panel">
                    <div class="sbha-panel-header">
                        <h2>Business Opportunities</h2>
                        <span class="sbha-badge ai">AI Detected</span>
                    </div>
                    <div class="sbha-panel-content">
                        <?php if (empty($gaps)): ?>
                            <p class="sbha-no-data">No service gaps detected yet. The AI will identify opportunities as customers make inquiries.</p>
                        <?php else: ?>
                            <ul class="sbha-gaps-list">
                                <?php foreach ($gaps as $gap): ?>
                                    <li class="sbha-gap-item">
                                        <div class="sbha-gap-keyword">
                                            <strong>"<?php echo esc_html($gap['keyword']); ?>"</strong>
                                            <span class="sbha-gap-count"><?php echo esc_html($gap['request_count']); ?> requests</span>
                                        </div>
                                        <div class="sbha-gap-score">
                                            <span class="sbha-score-label">Demand Score</span>
                                            <span class="sbha-score-value"><?php echo number_format($gap['estimated_demand_score'], 1); ?></span>
                                        </div>
                                        <div class="sbha-gap-actions">
                                            <button class="button button-small sbha-review-gap" data-id="<?php echo esc_attr($gap['id']); ?>">Review</button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="sbha-gap-tip">These are services customers are asking for that you don't currently offer. Consider adding them to grow your business.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="sbha-panel sbha-actions-panel">
                    <div class="sbha-panel-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="sbha-panel-content">
                        <div class="sbha-quick-actions">
                            <a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=new'); ?>" class="sbha-action-btn">
                                <span class="dashicons dashicons-plus-alt"></span>
                                New Job
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=sbha-services&action=new'); ?>" class="sbha-action-btn">
                                <span class="dashicons dashicons-admin-generic"></span>
                                Add Service
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=sbha-customers&action=new'); ?>" class="sbha-action-btn">
                                <span class="dashicons dashicons-admin-users"></span>
                                Add Customer
                            </a>
                            <a href="#" class="sbha-action-btn sbha-export-report">
                                <span class="dashicons dashicons-download"></span>
                                Export Report
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Today's Summary -->
                <div class="sbha-panel sbha-today-panel">
                    <div class="sbha-panel-header">
                        <h2>Today's Summary</h2>
                    </div>
                    <div class="sbha-panel-content">
                        <div class="sbha-today-stats">
                            <div class="sbha-today-stat">
                                <span class="sbha-today-value"><?php echo esc_html($stats['today']['total_jobs']); ?></span>
                                <span class="sbha-today-label">New Jobs</span>
                            </div>
                            <div class="sbha-today-stat">
                                <span class="sbha-today-value"><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($stats['today']['revenue'], 2); ?></span>
                                <span class="sbha-today-label">Revenue</span>
                            </div>
                            <div class="sbha-today-stat">
                                <span class="sbha-today-value"><?php echo esc_html($stats['today']['inquiries']); ?></span>
                                <span class="sbha-today-label">Inquiries</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Revenue Chart
            var ctx = document.getElementById('sbha-revenue-chart');
            if (ctx) {
                $.ajax({
                    url: sbhaAdmin.restUrl + 'admin/analytics',
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', sbhaAdmin.restNonce);
                    },
                    data: { period: 'month' },
                    success: function(response) {
                        if (response.success && response.data.revenue_chart) {
                            var labels = response.data.revenue_chart.map(function(item) {
                                return item.date;
                            });
                            var values = response.data.revenue_chart.map(function(item) {
                                return parseFloat(item.revenue);
                            });

                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Revenue',
                                        data: values,
                                        borderColor: '#FF6600',
                                        backgroundColor: 'rgba(255, 102, 0, 0.1)',
                                        fill: true,
                                        tension: 0.4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                callback: function(value) {
                                                    return sbhaAdmin.currency + value;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    }
                });
            }

            // Export Report
            $('.sbha-export-report').on('click', function(e) {
                e.preventDefault();
                $.ajax({
                    url: sbhaAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'sbha_admin_action',
                        sbha_action: 'export_report',
                        nonce: sbhaAdmin.nonce,
                        period: 'month',
                        format: 'csv'
                    },
                    success: function(response) {
                        if (response.success) {
                            var link = document.createElement('a');
                            link.href = 'data:' + response.data.mime + ';base64,' + response.data.content;
                            link.download = response.data.filename;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
}
