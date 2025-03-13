<?php


function rn_ppt_add_admin_menu() {
	add_menu_page(
		'Plugin Performance',
		'Plugin Performance',
		'manage_options',
		'plugin-performance-tester',
		'rn_ppt_render_admin_page',
		'dashicons-performance'
	);
}
add_action('admin_menu', 'rn_ppt_add_admin_menu' );

function rn_ppt_render_admin_page() {
	$results = get_option('rn_ppt_performance_results');
	
	wp_enqueue_style( 'rn_ppt_admin_style', RN_PPT_PLUGINS_URL . '/assets/css/admin.css', [], RN_PPT_PLUGIN_VER );
	wp_enqueue_script( 'rn_ppt_chart_js', RN_PPT_PLUGINS_URL . '/assets/js/chart.js', [], RN_PPT_PLUGIN_VER, true );
	wp_enqueue_script( 'rn_ppt_admin_js', RN_PPT_PLUGINS_URL . '/assets/js/admin.js', ['rn_ppt_chart_js'], RN_PPT_PLUGIN_VER, true );
	wp_localize_script( 'rn_ppt_admin_js', 'rnPptAdminData', [ 'results' => $results ] );
		
	?>
	<div class="wrap">
		<h1>Plugin Performance Tester</h1>
		<form method="post">
			<label for="test_runs">Number of runs per test (1-10):</label>
			<input type="number" id="test_runs" name="test_runs" min="1" max="10" value="1">
			<input type="hidden" name="start_performance_test" value="1">
			<?php submit_button('Start Test'); ?>
		</form>
	   
		<?php if ($results) : ?>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Plugin</th>
						<th>Avg Load Time (ms)</th>
						<th>Avg Peak Memory (MB)</th>
						<th>Impact vs Baseline</th>
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
							<td><?php echo number_format($data['time'], 2); ?></td>
							<td><?php echo number_format($data['memory'] / 1024 / 1024, 2); ?></td>
							<td>
								<?php 
								$time_diff = $data['time'] - $results['Baseline']['time'];
								$mem_diff = ($data['memory'] - $results['Baseline']['memory']) / 1024 / 1024;
								echo sprintf('Time: %s%.2fms, Mem: %s%.2fMB', 
									$time_diff > 0 ? '+' : '', $time_diff,
									$mem_diff > 0 ? '+' : '', $mem_diff
								);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div id="chart-wrapper">
				<div class="chart-container">
					<canvas id="timeChart"></canvas>
				</div>
				<div class="chart-container">
					<canvas id="memoryChart"></canvas>
				</div>
			</div>
			<code><?php echo json_encode( $results ); ?></code>
		<?php endif; ?>
	</div>
	<?php
}