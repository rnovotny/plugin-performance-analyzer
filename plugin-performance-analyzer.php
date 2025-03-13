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

class PluginPerformanceTester {
    
    private $testing_option = 'plugin_performance_testing';
    private $test_queue_option = 'plugin_performance_queue';
    private $test_runs_option = 'plugin_performance_runs';
    private $original_plugins_option = 'plugin_performance_original_plugins';

    public function __construct() {
        add_action('admin_init', [$this, 'handle_test_requests']);
        add_action('template_redirect', [$this, 'manage_test_setup']);
        add_action('template_redirect', [$this, 'manage_test_measurement'], 20);
        add_action('plugins_loaded', [$this, 'track_performance']);
        add_action('wp_footer', [$this, 'add_redirect_script']);
    }
	
    public function handle_test_requests() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['start_performance_test'])) {
            delete_option('rn_ppt_performance_results');
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

            wp_redirect(home_url('?performance_test_setup=0'));
            exit;
        }
    }

    public function manage_test_setup() {
        if (!isset($_GET['performance_test_setup']) || !get_option($this->testing_option)) {
            return;
        }

        $current_index = intval($_GET['performance_test_setup']);
        $queue_data = get_option($this->test_queue_option);
        
        if (!$queue_data || !isset($queue_data['queue'])) {
            $this->complete_testing();
            return;
        }

        $queue = $queue_data['queue'];
        $plugin_names = array_keys($queue);
        $current_test = $plugin_names[$current_index] ?? null;
        
        if (!$current_test) {
            $this->complete_testing();
            return;
        }

        $this->setup_test($current_test, $queue[$current_test]);
        // Instead of immediate redirect, set next URL for JavaScript
        $this->set_next_url(home_url('?performance_test_measure=' . $current_index));
    }

    public function manage_test_measurement() {
        if (!isset($_GET['performance_test_measure']) || !get_option($this->testing_option)) {
            return;
        }

        $current_index = intval($_GET['performance_test_measure']);
        $queue_data = get_option($this->test_queue_option);
        
        if (!$queue_data || !isset($queue_data['queue'])) {
            $this->complete_testing();
            return;
        }

        $queue = $queue_data['queue'];
        $plugin_names = array_keys($queue);
        $current_test = $plugin_names[$current_index] ?? null;
        
        if (!$current_test) {
            $this->complete_testing();
            return;
        }

        $runs = get_option($this->test_runs_option, 1);
        $current_run = $queue_data['current_run'] ?? 1;

        if ($current_run < $runs) {
            $queue_data['current_run'] = $current_run + 1;
            update_option($this->test_queue_option, $queue_data);
            $this->set_next_url(home_url('?performance_test_setup=' . $current_index));
        } else {
            $next_index = $current_index + 1;
            if (isset($plugin_names[$next_index])) {
                $queue_data['current'] = $next_index;
                $queue_data['current_run'] = 1;
                update_option($this->test_queue_option, $queue_data);
                $this->set_next_url(home_url('?performance_test_setup=' . $next_index));
            } else {
                //$this->set_next_url(admin_url('admin.php?page=plugin-performance-tester'));
                $this->complete_testing();
            }
        }
    }

    public function track_performance() {
        if (!isset($_GET['performance_test_measure']) || !get_option($this->testing_option)) {
            return;
        }

        $current_index = intval($_GET['performance_test_measure']);
        $queue_data = get_option($this->test_queue_option);
        $queue = $queue_data['queue'];
        $plugin_names = array_keys($queue);
        $current_test = $plugin_names[$current_index] ?? null;

        if (!$current_test) return;

        $start_time = microtime(true);

        add_action('shutdown', function() use ($start_time, $current_test) {
            $end_time = microtime(true);
            $memory_peak = memory_get_peak_usage();

            $results = get_option('rn_ppt_performance_results', []);
            $queue_data = get_option($this->test_queue_option);
            $current_run = $queue_data['current_run'];

            if (!isset($results[$current_test]['runs'])) {
                $results[$current_test]['runs'] = [];
            }
            $run_data = [
                'time' => ($end_time - $start_time) * 1000,
                'memory' => $memory_peak
            ];
            $results[$current_test]['runs'] = array_slice($results[$current_test]['runs'], 0, $current_run - 1);
            $results[$current_test]['runs'][] = $run_data;

            $times = array_column($results[$current_test]['runs'], 'time');
            $memories = array_column($results[$current_test]['runs'], 'memory');
            $results[$current_test]['time'] = array_sum($times) / count($times);
            $results[$current_test]['memory'] = array_sum($memories) / count($memories);

            update_option('rn_ppt_performance_results', $results);
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

    private function complete_testing($redirect = true) {
		$original_plugins = get_option($this->original_plugins_option, []);
        update_option('active_plugins', $original_plugins);
        delete_option($this->testing_option);
        delete_option($this->test_queue_option);
        delete_option($this->original_plugins_option);
        if ($redirect) {
            wp_safe_redirect(admin_url('admin.php?page=plugin-performance-tester'));
            exit;
        }
    }

    private function set_next_url($url) {
        global $next_performance_test_url;
        $next_performance_test_url = $url;
    }

    public function add_redirect_script() {
        global $next_performance_test_url;
        if (!isset($next_performance_test_url) || !get_option($this->testing_option)) {
            return;
        }
        ?>
        <script type="text/javascript">
            setTimeout(function() {
                window.location.href = '<?php echo esc_js($next_performance_test_url); ?>';
            }, 500); // 500ms delay to prevent redirect loop
        </script>
        <?php
    }
}

new PluginPerformanceTester();