document.addEventListener('DOMContentLoaded', function () {
    // Ensure required data exists
    if (!window.rnPptAdminData || !rnPptAdminData.results || !rnPptAdminData.results['Baseline']) {
        console.error('Performance data is missing or invalid.');
        return;
    }

    const results = rnPptAdminData.results;
    const baseline = results['Baseline'];
    const plugins = Object.keys(results).filter(plugin => plugin !== 'Baseline' && plugin !== 'All Plugins');

    /**
     * Helper function to extract the last part of a plugin name (usually the folder name) and remove .php extension
     * @param {string} plugin - Plugin identifier (e.g., "some-plugin/some-file.php")
     * @returns {string} - Formatted plugin name
     */
    const formatPluginName = (plugin) => {
        let name = plugin.includes('/') ? plugin.split('/').pop() : plugin;
        return name.replace(/\.php$/, ''); // Remove .php extension if present
    };

    // Process plugin impact data
    const pluginData = plugins.map(plugin => {
        return {
            name: plugin,
            label: formatPluginName(plugin),
            timeImpact: results[plugin].time - baseline.time,
            memoryImpact: (results[plugin].memory - baseline.memory) / 1024 / 1024 // Convert bytes to MB
        };
    });

    // Filter out plugins with no impact
    const filteredTimeData = pluginData.filter(plugin => plugin.timeImpact > 0);
    const filteredMemoryData = pluginData.filter(plugin => plugin.memoryImpact > 0);

    // Sort plugins by highest time impact
    const sortedPlugins = [...pluginData].sort((a, b) => b.timeImpact - a.timeImpact);

    // Sort memory data by highest memory impact
    const sortedMemoryData = [...filteredMemoryData].sort((a, b) => b.memoryImpact - a.memoryImpact);

    /**
     * Generates a pie chart using Chart.js
     * @param {string} elementId - Canvas element ID
     * @param {Array} data - Chart data
     * @param {string} title - Chart title
     */
    const createPieChart = (elementId, data, title) => {
        if (!data.length) return; // Skip if there's no data to display

        new Chart(document.getElementById(elementId), {
            type: 'pie',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    data: data.map(d => d.value),
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                        '#9966FF', '#FF9F40', '#C9CBCF', '#7F7F7F'
                    ]
                }]
            },
            options: {
                plugins: {
                    title: { display: true, text: title },
                    legend: { position: 'right' }
                }
            }
        });
    };

    /**
     * Generates a bar chart using Chart.js
     * @param {string} elementId - Canvas element ID
     * @param {Array} sortedData - Sorted plugin data
     * @param {string} backgroundColor - Color for bars
     * @param {string} yAxisTitle - Y-axis title
     */
    const createBarChart = (elementId, data, backgroundColor, yAxisTitle, title ) => {
        new Chart(document.getElementById(elementId), {
            type: 'bar',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    labels: data.map(d => d.label),
                    data: data.map(d => d.value),
                    backgroundColor: backgroundColor
                }]
            },
            options: {
                plugins: {
                    title: { display: true, text: title },
                    legend: { display: false } // Remove legend from bar charts
                },
                scales: {
                    y: {
                        title: { display: true, text: yAxisTitle },
                        beginAtZero: true
                    },
                    x: {
                        title: { display: true, text: 'Plugins' }
                    }
                }
            }
        });
    };

    // Create Pie Charts
    createPieChart('memoryChartPie', sortedMemoryData.map(d => ({ label: d.label, value: d.memoryImpact })), 'Memory Usage Impact (MB)');
    createPieChart('timeChartPie', filteredTimeData.map(d => ({ label: d.label, value: d.timeImpact })), 'Load Time Impact (ms)');

    // Create Bar Charts
    createBarChart('timeChart', sortedPlugins.map(d => ({ label: d.label, value: Math.max(d.timeImpact, 0) })), '#FF6384', 'ms', 'Load Time Impact (ms)');
    createBarChart('memoryChart', sortedMemoryData.map(d => ({ label: d.label, value: d.memoryImpact })), '#36A2EB', 'MB', 'Memory Usage Impact (MB)');
});
