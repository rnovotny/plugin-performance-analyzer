<?php

function rn_ppt_add_admin_menu() {
    add_submenu_page (
        'tools.php',
        'Plugin Performance',
        'Plugin Performance',
        'manage_options',
        'rn_ppt_render_admin_page',
        'rn_ppt_render_admin_page'
    );
}
add_action('admin_menu', 'rn_ppt_add_admin_menu' );

function rn_ppt_render_admin_page() {
    $results = get_option( 'rn_ppt_performance_results' );
    $runs = get_option( 'rn_ppt_runs', 3 );
    $original_plugins = get_option('rn_ppt_original_plugins');

    wp_enqueue_style( 'rn_ppt_admin_style', RN_PPT_PLUGINS_URL . '/assets/css/admin.css', [], RN_PPT_PLUGIN_VER );
    wp_enqueue_script( 'rn_ppt_chart_js', RN_PPT_PLUGINS_URL . '/assets/js/chart.js', [], RN_PPT_PLUGIN_VER, true );
    wp_enqueue_script( 'rn_ppt_admin_js', RN_PPT_PLUGINS_URL . '/assets/js/admin.js', ['rn_ppt_chart_js'], RN_PPT_PLUGIN_VER, true );
    wp_localize_script( 'rn_ppt_admin_js', 'rnPptAdminData', [ 'results' => $results ] );
        
    ?>

<div class="wrap">
    <h1>Plugin Performance Tester</h1>
    <form method="post">
        <?php wp_nonce_field( 'rn_ppt_admin_nonce' ); ?>
        <label for="test_runs">Number of runs per test (3-10):</label>
        <input type="number" id="test_runs" name="test_runs" min="3" max="10" value="<?php echo intVal($runs) ?>">
        <?php submit_button( 'Start Test', 'primary', 'start_performance_test' ); ?>
        <?php if( !empty( $original_plugins ) ) {
            submit_button('Restore Active Plugins', 'secondary', 'restore_plugins' );
        }?>
    </form>
   
    <?php if ($results) : ?>
        <div class="chart-wrapper">
			<div class="chart-container">
				<canvas id="timeChartPie"></canvas>
			</div>
			<div class="chart-container">
				<canvas id="memoryChartPie"></canvas>
			</div>			
		</div>
		<div class="chart-wrapper">
			<div class="chart-container">
				<canvas id="timeChart"></canvas>
			</div>
			<div class="chart-container">
				<canvas id="memoryChart"></canvas>
			</div>			
		</div>
		
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Avg Load Time (ms)</th>
                    <th>Avg Peak Memory (MB)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $plugin => $data) : ?>
                    <tr>
                        <td>
                            <?php echo esc_html($plugin); ?>
                            <?php if (!empty($data['runs'])) : ?>
                                <span class="details-toggle" onclick="this.nextElementSibling.classList.toggle('active');"> (Show Details)</span>
                                <table class="details-table">
                                    <thead>
                                        <tr>
                                            <th>Run</th>
                                            <th>Load Time (ms)</th>
                                            <th>Peak Memory (MB)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['runs'] as $i => $run) : ?>
                                            <tr>
                                                <td><?php echo $i + 1; ?></td>
                                                <td><?php echo number_format($run['time'], 2); ?></td>
                                                <td><?php echo number_format($run['memory'] / 1024 / 1024, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            echo number_format($data['time'], 2);
                            $time_diff = $data['time'] - $results['Baseline']['time'];
                            $time_percent_diff = $results['Baseline']['time'] ? ($time_diff / $results['Baseline']['time']) * 100 : 0;
                            $time_class = (abs($time_percent_diff) >= 10) ? ' class="impact"' : '';
                            echo sprintf(' <span%s>(%s%.2fms)</span>', 
                                $time_class,
                                $time_diff > 0 ? '+' : '', 
                                $time_diff
                            );
                            ?>
                        </td>
                        <td>
                            <?php 
                            echo number_format($data['memory'] / 1024 / 1024, 2);
                            $mem_diff = ($data['memory'] - $results['Baseline']['memory']) / 1024 / 1024;
                            $mem_percent_diff = $results['Baseline']['memory'] ? (($data['memory'] - $results['Baseline']['memory']) / $results['Baseline']['memory']) * 100 : 0;
                            $mem_class = (abs($mem_percent_diff) >= 10) ? ' class="impact"' : '';
                            echo sprintf(' <span%s>(%s%.2fMB)</span>', 
                                $mem_class,
                                $mem_diff > 0 ? '+' : '', 
                                $mem_diff
                            );
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        
		
        <code style='display:none;'><?php echo json_encode( $results ); ?></code>
    <?php endif; ?>
</div>
<?php
}
