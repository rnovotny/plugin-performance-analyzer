document.addEventListener('DOMContentLoaded', function() {
	const results = rnPptAdminData.results
	const plugins = Object.keys(results).filter(plugin => plugin !== 'Baseline' && plugin !== 'All Plugins')
	const baselineTime = results['Baseline'].time
	const baselineMemory = results['Baseline'].memory

	// Prepare data for memory chart
	const memoryData = plugins.map(plugin => ({
		label: plugin,
		value: (results[plugin].memory - baselineMemory) / 1024 / 1024
	})).filter(item => item.value > 0)

	// Prepare data for time chart
	const timeData = plugins.map(plugin => ({
		label: plugin,
		value: results[plugin].time - baselineTime
	})).filter(item => item.value > 0)

	// Memory Usage Pie Chart
	new Chart(document.getElementById('memoryChart'), {
		type: 'pie',
		data: {
			labels: memoryData.map(d => d.label),
			datasets: [{
				data: memoryData.map(d => d.value),
				backgroundColor: [
					'#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
					'#9966FF', '#FF9F40', '#C9CBCF', '#7F7F7F'
				]
			}]
		},
		options: {
			plugins: {
				title: {
					display: true,
					text: 'Memory Usage Impact (MB)'
				},
				legend: {
					position: 'right'
				}
			}
		}
	})

	// Load Time Pie Chart
	new Chart(document.getElementById('timeChart'), {
		type: 'pie',
		data: {
			labels: timeData.map(d => d.label),
			datasets: [{
				data: timeData.map(d => d.value),
				backgroundColor: [
					'#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
					'#9966FF', '#FF9F40', '#C9CBCF', '#7F7F7F'
				]
			}]
		},
		options: {
			plugins: {
				title: {
					display: true,
					text: 'Load Time Impact (ms)'
				},
				legend: {
					position: 'right'
				}
			}
		}
	})
})