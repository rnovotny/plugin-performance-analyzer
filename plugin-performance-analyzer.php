<?php
/*
Plugin Name: Plugin Performance Analyzer
Description: Tests the impact of active plugins on loading speed and memory usage
Version: 0.0.1
Author: Ryan Novotny
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
define( 'RN_PPT_DEBUG', TRUE );
if ( RN_PPT_DEBUG ) {
	define( 'RN_PPT_PLUGIN_VER', time() );
} else {
	define( 'RN_PPT_PLUGIN_VER', '0.0.1' );
}

define( 'RN_PPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RN_PPT_PLUGINS_URL', plugins_url( '', __FILE__ ) );
define( 'RN_PPT_PLUGINS_BASENAME', plugin_basename(__FILE__) );
define( 'RN_PPT_PLUGIN_FILE', __FILE__ );
define( 'RN_PPT_PLUGIN_PACKAGE', '{{PPT-Edition}}' ); //DONT CHANGE THIS, IT WONT ADD FEATURES, ONLY BREAKS UPDATER AND LICENSE

include_once( RN_PPT_PLUGIN_DIR . '/includes/admin.php' );


/**
 * Handles test initiation from admin panel
 */
function rn_ppt_start_test_on_admin_request() {
	
	
	if ( isset( $_POST['restore_plugins'] ) ) {
		
		if( !wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'rn_ppt_admin_nonce' ) ) {
			wp_die('Unauthorized, please log in and try the request again.');
		}
		
		$original_plugins = get_option('rn_ppt_original_plugins', []);
		update_option('active_plugins', $original_plugins);
		delete_option('rn_ppt_original_plugins');
		wp_safe_redirect(admin_url('tools.php?page=rn_ppt_render_admin_page'));
		exit;
		
	}
	
	if ( isset( $_POST['start_performance_test'] ) ) {
		
		if( !wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'rn_ppt_admin_nonce' ) ) {
			wp_die('Unauthorized, please log in and try the request again.');
		}
		
		// Reset previous results and queue
		delete_option('rn_ppt_performance_results');
		delete_option('rn_ppt_queue');
		update_option('rn_ppt_testing', true);

		// Set number of test runs (1-10)
		$runs = isset($_POST['test_runs']) ? max(3, min(10, intval($_POST['test_runs']))) : 3;
		update_option('rn_ppt_runs', $runs);

		// Save original plugin state
		$active_plugins = get_option('active_plugins');
		update_option('rn_ppt_original_plugins', $active_plugins);

		// Prepare test queue excluding this plugin
		$test_plugins = array_filter($active_plugins, function($plugin) {
			return $plugin !== plugin_basename(__FILE__);
		});

		// Create test scenarios
		$queue = [
			'Baseline' => []
		];
		
		foreach ($test_plugins as $plugin) {
			$queue[$plugin] = [$plugin];
		}
		
		$queue['All Plugins'] = array_values($test_plugins);

		// Initialize queue data
		$queue_data = [
			'queue' => $queue,
			'current' => 0,
			'current_run' => 1,
			'runs' => []
		];
		update_option('rn_ppt_queue', $queue_data);

		// Redirect to start testing
		wp_redirect(home_url('?performance_test_setup=0'));
		exit;
	}
}
add_action( 'admin_init', 'rn_ppt_start_test_on_admin_request' );

/**
 * Configures the environment for each test scenario
 */
function rn_ppt_setup_test_environment() {
	
	global $next_performance_test_url;
	
	if (!isset($_GET['performance_test_setup']) || !get_option('rn_ppt_testing')) {
		return;
	}

	$current_index = intval($_GET['performance_test_setup']);
	$queue_data = get_option('rn_ppt_queue');
	$queue = $queue_data['queue'];
	$plugin_names = array_keys($queue);
	$current_test = $plugin_names[$current_index] ?? null;

	rn_ppt_configure_active_plugins($current_test, $queue[$current_test]);
	
	wp_cache_flush();
	
	$next_performance_test_url = home_url('?performance_test_measure=' . $current_index);
}
add_action( 'init', 'rn_ppt_setup_test_environment' );

/**
 * Manages the performance measurement process and test progression
 */
function rn_ppt_measure_test_performance() {
	global $next_performance_test_url;
	
	if (!isset($_GET['performance_test_measure']) || !get_option('rn_ppt_testing')) {
		return;
	}

	$current_index = intval($_GET['performance_test_measure']);
	$queue_data = get_option('rn_ppt_queue');
	$queue = $queue_data['queue'];
	$plugin_names = array_keys($queue);
	$current_test = $plugin_names[$current_index] ?? null;
	$runs = get_option('rn_ppt_runs', 1);
	$current_run = $queue_data['current_run'] ?? 1;

	if ($current_run < $runs) {
		// Move to next run of current test
		$queue_data['current_run'] = $current_run + 1;
		update_option('rn_ppt_queue', $queue_data);
		$next_performance_test_url = home_url('?performance_test_measure=' . $current_index);
	} else {
		// Move to next test or finish
		$next_index = $current_index + 1;
		if (isset($plugin_names[$next_index])) {
			$queue_data['current'] = $next_index;
			$queue_data['current_run'] = 1;
			update_option('rn_ppt_queue', $queue_data);
			$next_performance_test_url = home_url('?performance_test_setup=' . $next_index);
		} else {
			rn_ppt_finalize_testing();
		}
	}
}
add_action( 'template_redirect', 'rn_ppt_measure_test_performance' );

/**
 * Records time and memory usage during test runs
 */
function rn_ppt_record_performance_metrics() {
    if (!isset($_GET['performance_test_measure']) || !get_option('rn_ppt_testing')) {
        return;
    }

    $current_index = intval($_GET['performance_test_measure']);
    $queue_data = get_option('rn_ppt_queue');
    $queue = $queue_data['queue'];
    $plugin_names = array_keys($queue);
    $current_test = $plugin_names[$current_index] ?? null;

    if (!$current_test) return;

    $start_time = microtime(true);

    add_action('shutdown', function() use ($start_time, $current_test) {
        $end_time = microtime(true);
        $memory_peak = memory_get_peak_usage();

        $results = get_option('rn_ppt_performance_results', []);
        
        if (!isset($results[$current_test]['runs'])) {
            $results[$current_test]['runs'] = [];
        }

        $run_data = [
            'time' => ($end_time - $start_time) * 1000,
            'memory' => $memory_peak
        ];
        
        $results[$current_test]['runs'][] = $run_data;

        // Only calculate averages when we have all runs
        $queue_data = get_option('rn_ppt_queue');
        $runs = get_option('rn_ppt_runs', 3);
        if (count($results[$current_test]['runs']) == $runs) {
            $times = array_column($results[$current_test]['runs'], 'time');
            $memories = array_column($results[$current_test]['runs'], 'memory');
            
            // Remove fastest and slowest
            if (count($times) >= 3) {
                sort($times);
                array_shift($times); // Remove fastest
                array_pop($times);   // Remove slowest
                
                sort($memories);
                array_shift($memories); // Remove lowest memory
                array_pop($memories);   // Remove highest memory
            }
            
            $results[$current_test]['time'] = array_sum($times) / count($times);
            $results[$current_test]['memory'] = array_sum($memories) / count($memories);
        }

        update_option('rn_ppt_performance_results', $results);
    }, PHP_INT_MAX);
}
add_action( 'plugins_loaded', 'rn_ppt_record_performance_metrics', PHP_INT_MIN );

/**
 * Configures which plugins should be active for a test
 */
function rn_ppt_configure_active_plugins($test_name, $plugins_to_activate) {
	$all_plugins = get_option('active_plugins');
	$new_active = array_filter($all_plugins, function($plugin) {
		return $plugin === plugin_basename(__FILE__);
	});

	foreach ($plugins_to_activate as $plugin) {
		$new_active[] = $plugin;
	}

	update_option('active_plugins', array_values($new_active));
}

/**
 * Cleans up after testing is complete
 */
function rn_ppt_finalize_testing($redirect = true) {
	$original_plugins = get_option('rn_ppt_original_plugins', []);
	update_option('active_plugins', $original_plugins);
	delete_option('rn_ppt_testing');
	delete_option('rn_ppt_queue');
	delete_option('rn_ppt_original_plugins');
	
	if ($redirect) {
		wp_safe_redirect(admin_url('tools.php?page=rn_ppt_render_admin_page'));
		exit;
	}
}


/**
 * Adds JavaScript to handle automatic redirects between test steps
 */
function rn_ppt_inject_redirect_script() {
	global $next_performance_test_url;
	if (!isset($next_performance_test_url) || !get_option('rn_ppt_testing')) {
		return;
	}
	?>
	<script type="text/javascript">
		setTimeout(function() {
			window.location.href = '<?php echo esc_js($next_performance_test_url); ?>';
		}, 400); // 
	</script>
	<?php
}
add_action( 'wp_footer', 'rn_ppt_inject_redirect_script' );
