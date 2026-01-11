<?php
/**
 * Admin AI Insights
 *
 * AI insights and business intelligence dashboard
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Admin_Insights {

    /**
     * Render page
     */
    public function render() {
        $insights = SBHA_Insights::get_active_insights(20);
        $gaps = SBHA_Insights::get_service_gaps();
        $opportunities = SBHA_Insights::get_expansion_opportunities();
        $customer_insights = SBHA_Insights::get_customer_insights();
        $operational = SBHA_Insights::get_operational_insights();
        $market = SBHA_Insights::get_market_insights();
        $keywords = SBHA()->get_analytics()->get_keyword_analytics(30);
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                AI Insights
                <span class="sbha-badge ai">Powered by AI</span>
            </h1>

            <div class="sbha-insights-grid">
                <!-- Alerts Panel -->
                <div class="sbha-panel sbha-alerts-panel">
                    <div class="sbha-panel-header">
                        <h2>Active Alerts</h2>
                    </div>
                    <div class="sbha-panel-content">
                        <?php
                        $alerts = array_merge($operational, $customer_insights);
                        $alerts = array_filter($alerts, function($a) {
                            return isset($a['severity']) && in_array($a['severity'], array('critical', 'high'));
                        });

                        if (empty($alerts)):
                        ?>
                            <p class="sbha-no-alerts">No critical alerts at this time.</p>
                        <?php else: ?>
                            <ul class="sbha-alerts-list">
                                <?php foreach ($alerts as $alert): ?>
                                    <li class="sbha-alert-item severity-<?php echo esc_attr($alert['severity']); ?>">
                                        <span class="sbha-alert-icon"></span>
                                        <div class="sbha-alert-content">
                                            <strong><?php echo esc_html($alert['title']); ?></strong>
                                            <p><?php echo esc_html($alert['message']); ?></p>
                                            <?php if (!empty($alert['action'])): ?>
                                                <span class="sbha-alert-action"><?php echo esc_html($alert['action']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Business Opportunities -->
                <div class="sbha-panel sbha-opportunities-panel">
                    <div class="sbha-panel-header">
                        <h2>Business Opportunities</h2>
                        <span class="sbha-help-tip" title="Services customers are asking for that you don't currently offer">?</span>
                    </div>
                    <div class="sbha-panel-content">
                        <?php if (empty($opportunities)): ?>
                            <p class="sbha-no-data">No new opportunities detected yet. The AI will identify gaps as customers make inquiries.</p>
                        <?php else: ?>
                            <?php foreach ($opportunities as $opp): ?>
                                <div class="sbha-opportunity-card">
                                    <div class="sbha-opp-header">
                                        <h3>"<?php echo esc_html($opp['keyword']); ?>"</h3>
                                        <span class="sbha-demand-badge"><?php echo esc_html($opp['request_count']); ?> requests</span>
                                    </div>
                                    <div class="sbha-opp-stats">
                                        <div class="sbha-opp-stat">
                                            <span class="sbha-opp-value"><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($opp['analysis']['estimated_monthly_revenue'], 0); ?></span>
                                            <span class="sbha-opp-label">Est. Monthly Revenue</span>
                                        </div>
                                        <div class="sbha-opp-stat">
                                            <span class="sbha-opp-value"><?php echo esc_html($opp['analysis']['estimated_monthly_orders']); ?></span>
                                            <span class="sbha-opp-label">Est. Monthly Orders</span>
                                        </div>
                                        <div class="sbha-opp-stat">
                                            <span class="sbha-opp-value"><?php echo esc_html($opp['analysis']['roi_score']); ?>/100</span>
                                            <span class="sbha-opp-label">ROI Score</span>
                                        </div>
                                    </div>
                                    <?php if (!empty($opp['sample_queries'])): ?>
                                        <div class="sbha-opp-samples">
                                            <strong>Sample Queries:</strong>
                                            <ul>
                                                <?php foreach (array_slice($opp['sample_queries'], 0, 3) as $query): ?>
                                                    <li>"<?php echo esc_html($query); ?>"</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <div class="sbha-opp-action">
                                        <span class="sbha-suggested-action"><?php echo esc_html($opp['analysis']['suggested_action']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Keyword Cloud -->
                <div class="sbha-panel sbha-keywords-panel">
                    <div class="sbha-panel-header">
                        <h2>Customer Request Keywords</h2>
                        <span class="sbha-period">Last 30 days</span>
                    </div>
                    <div class="sbha-panel-content">
                        <?php if (empty($keywords)): ?>
                            <p class="sbha-no-data">No keywords collected yet.</p>
                        <?php else: ?>
                            <div class="sbha-keyword-cloud">
                                <?php
                                $max_count = max($keywords);
                                foreach ($keywords as $keyword => $count):
                                    $size = 12 + (($count / $max_count) * 20);
                                    $opacity = 0.5 + (($count / $max_count) * 0.5);
                                ?>
                                    <span class="sbha-keyword" style="font-size: <?php echo $size; ?>px; opacity: <?php echo $opacity; ?>">
                                        <?php echo esc_html($keyword); ?>
                                        <span class="sbha-keyword-count">(<?php echo $count; ?>)</span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Market Trends -->
                <div class="sbha-panel sbha-trends-panel">
                    <div class="sbha-panel-header">
                        <h2>Market Trends</h2>
                    </div>
                    <div class="sbha-panel-content">
                        <?php if (empty($market)): ?>
                            <p class="sbha-no-data">Analyzing market trends... Need more data to identify patterns.</p>
                        <?php else: ?>
                            <?php foreach ($market as $trend): ?>
                                <div class="sbha-trend-item">
                                    <h4><?php echo esc_html($trend['title']); ?></h4>
                                    <?php if (!empty($trend['message'])): ?>
                                        <p><?php echo esc_html($trend['message']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($trend['data'])): ?>
                                        <div class="sbha-trend-data">
                                            <?php if (isset($trend['data']['peak_months'])): ?>
                                                <p><strong>Peak Months:</strong> <?php echo esc_html(implode(', ', $trend['data']['peak_months'])); ?></p>
                                            <?php endif; ?>
                                            <?php if (isset($trend['data']['low_months'])): ?>
                                                <p><strong>Low Months:</strong> <?php echo esc_html(implode(', ', $trend['data']['low_months'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($trend['action'])): ?>
                                        <span class="sbha-trend-action"><?php echo esc_html($trend['action']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- All Insights -->
                <div class="sbha-panel sbha-all-insights-panel">
                    <div class="sbha-panel-header">
                        <h2>All Insights</h2>
                    </div>
                    <div class="sbha-panel-content">
                        <?php if (empty($insights)): ?>
                            <p class="sbha-no-data">No insights yet. The AI is learning from your business data.</p>
                        <?php else: ?>
                            <table class="sbha-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Insight</th>
                                        <th>Severity</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($insights as $insight): ?>
                                        <tr>
                                            <td><span class="sbha-insight-type"><?php echo esc_html(str_replace('_', ' ', $insight['insight_type'])); ?></span></td>
                                            <td><?php echo esc_html($insight['insight_title']); ?></td>
                                            <td><span class="sbha-severity-badge severity-<?php echo esc_attr($insight['severity']); ?>"><?php echo esc_html(ucfirst($insight['severity'])); ?></span></td>
                                            <td><?php echo date('M j', strtotime($insight['created_at'])); ?></td>
                                            <td>
                                                <?php if ($insight['action_required'] && !$insight['action_taken']): ?>
                                                    <button class="button button-small sbha-mark-done" data-id="<?php echo esc_attr($insight['id']); ?>">Mark Done</button>
                                                <?php elseif ($insight['action_taken']): ?>
                                                    <span class="sbha-done">Done</span>
                                                <?php else: ?>
                                                    <button class="button button-small sbha-dismiss-insight" data-id="<?php echo esc_attr($insight['id']); ?>">Dismiss</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Price Optimization -->
                <div class="sbha-panel sbha-pricing-panel">
                    <div class="sbha-panel-header">
                        <h2>Pricing Recommendations</h2>
                    </div>
                    <div class="sbha-panel-content">
                        <?php
                        $price_optimizer = new SBHA_Price_Optimizer();
                        $bundles = $price_optimizer->get_bundle_suggestions();
                        $seasonal = $price_optimizer->get_seasonal_recommendations();

                        if (empty($bundles) && empty($seasonal)):
                        ?>
                            <p class="sbha-no-data">Not enough data yet for pricing recommendations. Keep collecting orders!</p>
                        <?php else: ?>
                            <?php if (!empty($bundles)): ?>
                                <h4>Bundle Opportunities</h4>
                                <?php foreach (array_slice($bundles, 0, 3) as $bundle): ?>
                                    <div class="sbha-bundle-suggestion">
                                        <p>
                                            <strong><?php echo esc_html($bundle['services'][0]['name']); ?></strong> +
                                            <strong><?php echo esc_html($bundle['services'][1]['name']); ?></strong>
                                        </p>
                                        <p class="sbha-bundle-price">
                                            Bundle Price: <?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($bundle['bundle_price'], 2); ?>
                                            <span class="sbha-discount">(<?php echo esc_html($bundle['suggested_discount']); ?>% off)</span>
                                        </p>
                                        <small>Based on <?php echo esc_html($bundle['pairing_count']); ?> orders together</small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (!empty($seasonal)): ?>
                                <h4>Seasonal Adjustments</h4>
                                <?php foreach (array_slice($seasonal, 0, 3) as $rec): ?>
                                    <div class="sbha-seasonal-rec">
                                        <p>
                                            <strong><?php echo esc_html($rec['service_name']); ?></strong> -
                                            <?php echo esc_html($rec['month']); ?>
                                        </p>
                                        <p><?php echo esc_html($rec['suggestion']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.sbha-dismiss-insight').on('click', function() {
                var $btn = $(this);
                var id = $btn.data('id');

                $.ajax({
                    url: sbhaAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'sbha_admin_action',
                        sbha_action: 'dismiss_insight',
                        nonce: sbhaAdmin.nonce,
                        insight_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.closest('tr').fadeOut();
                        }
                    }
                });
            });

            $('.sbha-mark-done').on('click', function() {
                var $btn = $(this);
                var id = $btn.data('id');

                // Mark as action taken
                $btn.text('Done').removeClass('button-primary').addClass('sbha-done').prop('disabled', true);
            });
        });
        </script>
        <?php
    }
}
