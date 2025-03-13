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


class PluginPerformanceTester {
    private $option_name = 'plugin_performance_results';
    private $testing_option = 'plugin_performance_testing';
    private $test_queue_option = 'plugin_performance_queue';
    private $test_runs_option = 'plugin_performance_runs';
    private $original_plugins_option = 'plugin_performance_original_plugins';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_test_requests']);
        add_action('template_redirect', [$this, 'manage_test_flow']);
        add_action('plugins_loaded', [$this, 'track_performance']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Plugin Performance',
            'Plugin Performance',
            'manage_options',
            'plugin-performance-tester',
            [$this, 'render_admin_page'],
            'dashicons-performance'
        );
    }

    public function render_admin_page() {
		
		$results = get_option($this->option_name);
		
		wp_enqueue_style( 'rn_ppt_admin_style', RN_PPT_PLUGINS_URL . '/assets/css/admin.css', [], RN_PPT_PLUGIN_VER );
		wp_enqueue_script( 'rn_ppt_chart_js', RN_PPT_PLUGINS_URL . '/assets/js/chart.js', [], RN_PPT_PLUGIN_VER, true );
		wp_enqueue_script( 'rn_ppt_admin_js', RN_PPT_PLUGINS_URL . '/assets/js/admin.js', ['rn_ppt_chart_js'], RN_PPT_PLUGIN_VER, true );
		wp_localize_script( 'rn_ppt_admin_js', 'rnPptAdminData', [ 'results' => $results ] );
		
        $runs = get_option($this->test_runs_option, 1);
        ?>
        <div class="wrap">
            <h1>Plugin Performance Tester</h1>
            <form method="post">
                <label for="test_runs">Number of runs per test (1-10):</label>
                <input type="number" id="test_runs" name="test_runs" min="1" max="10" value="<?php echo esc_attr($runs); ?>">
                <input type="hidden" name="start_performance_test" value="1">
                <?php submit_button('Start Test'); ?>
            </form>

            <?php if (get_option($this->testing_option)) : ?>
                <?php 
                $queue_data = get_option($this->test_queue_option);
                $current_index = $queue_data['current'] ?? 'Unknown';
                $current_run = $queue_data['current_run'] ?? 1;
                $current_name = array_keys($queue_data['queue'])[$current_index] ?? 'Unknown';
                ?>
                <p>Testing in progress... Current test: <?php echo esc_html($current_name); ?> (Index: <?php echo esc_html($current_index); ?>, Run: <?php echo esc_html($current_run); ?> of <?php echo esc_html($runs); ?>)</p>
            <?php endif; ?>
           
            <?php if ($results) : echo json_encode( $results ); ?>
                
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

                <!-- Add Chart.js library and canvas elements for pie charts below table -->
                <div id="chart-wrapper">
                    <div class="chart-container">
						<canvas id="timeChart"></canvas>
                    </div>
                    <div class="chart-container">
						<canvas id="memoryChart"></canvas>
                    </div>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_test_requests() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['start_performance_test'])) {
            delete_option($this->option_name);
            delete_option($this->test_queue_option);
            update_option($this->testing_option, true);
            $runs = isset($_POST['test_runs']) ? max(1, min(10, intval($_POST['test_runs']))) : 1;
            update_option($this->test_runs_option, $runs);

            $active_plugins = get_option('active_plugins');
            update_option($this->original_plugins_option, $active_plugins);

            $test_plugins = array_filter($active_plugins, function($plugin) {
                return $plugin !== plugin_basename(__FILE__);
            });

            $queue = ['Baseline' => []];
            foreach ($test_plugins as $plugin) {
                $queue[$plugin] = [$plugin];
            }
            $queue['All Plugins'] = array_values($test_plugins);
            
            $queue_data = ['queue' => $queue, 'current' => 0, 'current_run' => 1, 'runs' => []];
            update_option($this->test_queue_option, $queue_data);

            $this->setup_test('Baseline', []);
            wp_redirect(home_url('?performance_test=0'));
            exit;
        }
    }

    public function manage_test_flow() {
        if (!isset($_GET['performance_test']) || !get_option($this->testing_option)) {
            return;
        }

        $current_index = intval($_GET['performance_test']);
        $queue_data = get_option($this->test_queue_option);
        if (!$queue_data || !isset($queue_data['queue'])) {
            delete_option($this->testing_option);
            wp_safe_redirect(admin_url('admin.php?page=plugin-performance-tester'));
            exit;
        }

        $queue = $queue_data['queue'];
        $plugin_names = array_keys($queue);
        $current_test = $plugin_names[$current_index] ?? null;
        if (!$current_test) {
            delete_option($this->testing_option);
            delete_option($this->test_queue_option);
            wp_safe_redirect(admin_url('admin.php?page=plugin-performance-tester'));
            exit;
        }

        $runs = get_option($this->test_runs_option, 1);
        $current_run = $queue_data['current_run'] ?? 1;

        if ($current_run < $runs) {
            $queue_data['current_run'] = $current_run + 1;
            update_option($this->test_queue_option, $queue_data);
            $this->setup_test($current_test, $queue[$current_test]);
            wp_safe_redirect(home_url('?performance_test=' . $current_index));
            exit;
        } else {
            $next_index = $current_index + 1;
            if (isset($plugin_names[$next_index])) {
                $next_test = $plugin_names[$next_index];
                $queue_data['current'] = $next_index;
                $queue_data['current_run'] = 1;
                update_option($this->test_queue_option, $queue_data);
                $this->setup_test($next_test, $queue[$next_test]);
                wp_safe_redirect(home_url('?performance_test=' . $next_index));
                exit;
            } else {
                $original_plugins = get_option($this->original_plugins_option, []);
                update_option('active_plugins', $original_plugins);
                delete_option($this->testing_option);
                delete_option($this->test_queue_option);
                delete_option($this->original_plugins_option);
                wp_safe_redirect(admin_url('admin.php?page=plugin-performance-tester'));
                exit;
            }
        }
    }

    public function track_performance() {
        if (!isset($_GET['performance_test']) || !get_option($this->testing_option)) {
            return;
        }

        $current_index = intval($_GET['performance_test']);
        $queue_data = get_option($this->test_queue_option);
        $queue = $queue_data['queue'];
        $plugin_names = array_keys($queue);
        $current_test = $plugin_names[$current_index] ?? null;

        if (!$current_test) return;

        $start_time = microtime(true);

        add_action('shutdown', function() use ($start_time, $current_test, $current_index) {
            $end_time = microtime(true);
            $memory_peak = memory_get_peak_usage();

            $results = get_option($this->option_name, []);
            $queue_data = get_option($this->test_queue_option);
            $current_run = $queue_data['current_run'];

            if (!isset($results[$current_test]['runs'])) {
                $results[$current_test]['runs'] = [];
            }
            $run_data = [
                'time' => ($end_time - $start_time) * 1000,
                'memory' => $memory_peak
            ];
            $results[$current_test]['runs'][] = $run_data;

            if ($current_run >= get_option($this->test_runs_option, 1)) {
                $times = array_column($results[$current_test]['runs'], 'time');
                $memories = array_column($results[$current_test]['runs'], 'memory');
                $results[$current_test]['time'] = array_sum($times) / count($times);
                $results[$current_test]['memory'] = array_sum($memories) / count($memories);
            }
            update_option($this->option_name, $results);
            update_option($this->test_queue_option, $queue_data);
        });
    }

    private function setup_test($plugin_name, $plugins_to_activate) {
        $all_plugins = get_option('active_plugins');
        $new_active = array_filter($all_plugins, function($plugin) {
            return $plugin === plugin_basename(__FILE__);
        });
        
        foreach ($plugins_to_activate as $plugin) {
            $new_active[] = $plugin;
        }
        
        update_option('active_plugins', array_values($new_active));
    }
}

new PluginPerformanceTester();