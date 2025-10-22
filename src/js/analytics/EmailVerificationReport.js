import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Pie } from 'react-chartjs-2';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Spinner } from '@wordpress/components'

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend);

// Use WooCommerce components with fallbacks
const Section = ({ children, ...props }) => {
	const WooCommerceSection = wc?.components?.Section;
	if (WooCommerceSection) {
		return <WooCommerceSection {...props}>{children}</WooCommerceSection>;
	}

	// Fallback to custom component
	return (
		<div style={{
			background: '#fff',
			border: '1px solid #ddd',
			borderRadius: '4px',
			marginBottom: '20px',
			boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
		}} {...props}>
			{children}
		</div>
	);
};

// WooCommerce Card component with fallback
const Card = ({ children, ...props }) => {
	const WooCommerceCard = wc?.components?.Card;
	if (WooCommerceCard) {
		return <WooCommerceCard {...props}>{children}</WooCommerceCard>;
	}

	// Fallback to custom component
	return (
		<div style={{
			background: '#fff',
			border: '1px solid #ddd',
			borderRadius: '4px',
			padding: '20px',
			marginBottom: '20px',
			boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
		}} {...props}>
			{children}
		</div>
	);
};

// Chart.js Pie Chart component
const Chart = ({ data, title, ...props }) => {
	// Validate data
	if (!data || !data.labels || !Array.isArray(data.labels) || !data.datasets || !Array.isArray(data.datasets)) {
		return (
			<div style={{ textAlign: 'center', padding: '20px', color: '#dc3232' }}>
				<h4>{title}</h4>
				<p>No data available</p>
			</div>
		);
	}

	// Chart.js options for pie charts
	const chartOptions = {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: {
				position: 'bottom',
				labels: {
					padding: 20,
					usePointStyle: true,
					font: {
						size: 12,
					},
				},
			},
			tooltip: {
				callbacks: {
					label: function(context) {
						const label = context.label || '';
						const value = context.parsed;
						const total = context.dataset.data.reduce((a, b) => a + b, 0);
						const percentage = ((value / total) * 100).toFixed(1);
						return `${label}: ${value} (${percentage}%)`;
					},
				},
			},
		},
	};

	return (
		<div style={{ padding: '20px' }}>
			<h4 style={{ marginTop: 0, marginBottom: '20px', textAlign: 'center' }}>{title}</h4>
			<div style={{ position: 'relative', height: '400px', maxWidth: '500px', margin: '0 auto' }}>
				<Pie data={data} options={chartOptions} />
			</div>
		</div>
	);
};

const EmailVerificationReport = ({ query, path, pathMatch, params }) => {
	const [stats, setStats] = useState(null);
	const [reasonStats, setReasonStats] = useState(null);
	const [rates, setRates] = useState(null);
	const [loading, setLoading] = useState(true);
	const [startDate, setStartDate] = useState('');
	const [endDate, setEndDate] = useState('');

	// Debug WooCommerce components availability
	useEffect(() => {
		console.log('WooCommerce components available:', {
			wc: typeof wc,
			wcComponents: typeof wc?.components,
			section: typeof wc?.components?.Section,
			card: typeof wc?.components?.Card,
			sectionHeader: typeof wc?.components?.SectionHeader,
		});
	}, []);

	useEffect(() => {
		loadStats();
	}, []);

	const loadStats = async () => {
		try {
			const params = {
				action: 'kickbox_integration_get_stats',
				nonce: kickboxAnalytics.nonce,
			};

			// Add date parameters if provided
			if (startDate) {
				params.start_date = startDate;
			}
			if (endDate) {
				params.end_date = endDate;
			}

			const response = await fetch(kickboxAnalytics.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams(params),
			});

			const data = await response.json();
			if (data.success) {
				setStats(data.data.verification_stats);
				setReasonStats(data.data.reason_stats);
				setRates(data.data.success_failure_rates);
			}
		} catch (error) {
			// Silently fail - stats will remain null
		} finally {
			setLoading(false);
		}
	};

	const getResultColor = (result) => {
		const colors = {
			deliverable: '#00876c',
			undeliverable: '#d43d51',
			risky: '#f7a258',
			unknown: '#d6ec91',
		};
		return colors[result] || '#88c580';
	};

	const getResultLabel = (result) => {
		const labels = {
			deliverable: 'Deliverable',
			undeliverable: 'Undeliverable',
			risky: 'Risky',
			unknown: 'Unknown',
		};
		return labels[result] || result;
	};

	const getReasonLabel = (reason) => {
		const labels = {
			'invalid_domain': 'Invalid Domain',
			'invalid_syntax': 'Invalid Syntax',
			'invalid_smtp': 'Invalid SMTP',
			'invalid_email': 'Invalid Email',
			'mailbox_full': 'Mailbox Full',
			'mailbox_unavailable': 'Mailbox Unavailable',
			'no_mx_record': 'No MX Record',
			'role_based': 'Role Based',
			'spam_trap': 'Spam Trap',
			'temporary_failure': 'Temporary Failure',
			'unknown': 'Unknown',
		};
		return labels[reason] || reason;
	};

	// Prepare chart data for Chart.js pie charts
	const chartData = stats ? {
		labels: stats.map(item => getResultLabel(item.verification_result)),
		datasets: [{
			data: stats.map(item => parseInt(item.count)),
			backgroundColor: stats.map(item => getResultColor(item.verification_result)),
			borderColor: stats.map(item => getResultColor(item.verification_result)),
			borderWidth: 2,
			hoverBorderWidth: 3,
		}],
	} : null;

	const reasonChartData = reasonStats && reasonStats.length > 0 ? {
		labels: reasonStats.map(item => getReasonLabel(item.result_reason)),
		datasets: [{
			data: reasonStats.map(item => parseInt(item.count)),
			backgroundColor: [
				'#00876c', '#3d9c73', '#63b179', '#88c580', '#aed987',
				'#d6ec91', '#ffff9d', '#fee17e', '#fcc267', '#f7a258',
				'#ef8250', '#e4604e', '#d43d51',
			],
			borderColor: [
				'#00876c', '#3d9c73', '#63b179', '#88c580', '#aed987',
				'#d6ec91', '#ffff9d', '#fee17e', '#fcc267', '#f7a258',
				'#ef8250', '#e4604e', '#d43d51',
			],
			borderWidth: 2,
			hoverBorderWidth: 3,
		}],
	} : null;

	// Debug logging to see what data we're getting
	console.log('Stats data:', stats);
	console.log('Reason stats data:', reasonStats);
	console.log('WooCommerce Chart data:', chartData);
	console.log('WooCommerce Reason chart data:', reasonChartData);

	if (loading) {
		return (
			<div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '50vh' }}>
				<Section>
						<Spinner />{__('Loading statistics...', 'kickbox-integration')}
				</Section>
			</div>
		);
	}

	if (!stats) {
		return (
			<div style={{ padding: '20px' }}>
				<Section>
					<div style={{ padding: '20px' }}>
						<div style={{ color: '#dc3232', textAlign: 'center' }}>
							{__('Unable to load statistics.', 'kickbox-integration')}
						</div>
					</div>
				</Section>
			</div>
		);
	}

	const totalVerifications = stats ? stats.reduce((sum, item) => sum + parseInt(item.count), 0) : 0;

	return (
		<div>
			<div>
				<div className="kickbox-date-range-filter">
					<p>{__('Date Range:', 'kickbox-integration')}</p>
					<div className="kickbox-date-range-controls">
						<div className="kickbox-date-input-group">
							<input
								type="date"
								value={startDate}
								onChange={(e) => setStartDate(e.target.value)}
								className="kickbox-date-input"
							/>
						</div>
						<div className="kickbox-date-input-group">
							<input
								type="date"
								value={endDate}
								onChange={(e) => setEndDate(e.target.value)}
								className="kickbox-date-input"
							/>
						</div>
						<div className="kickbox-filter-buttons">
							<button
								type="button"
								className="button button-primary"
								onClick={() => {
									setLoading(true);
									loadStats();
								}}
								disabled={loading}
							>
								{__('Apply Filter', 'kickbox-integration')}
							</button>
							<button
								type="button"
								className="button button-secondary"
								onClick={() => {
									setStartDate('');
									setEndDate('');
									setLoading(true);
									loadStats();
								}}
								disabled={loading}
							>
								{__('Clear Filter', 'kickbox-integration')}
							</button>
						</div>
					</div>
				</div>

				{/* Verification Statistics */}
				<div style={{
					display: 'grid',
					gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
					gap: '20px',
					marginBottom: '30px',
				}}>
					{/* Total Verifications Card */}
					<Card>
						<div style={{ textAlign: 'center' }}>
							<h3 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#666', textTransform: 'uppercase' }}>
								{__('Total Verifications', 'kickbox-integration')}
							</h3>
							<div style={{ fontSize: '32px', fontWeight: 'bold', color: '#0073aa' }}>
								{totalVerifications}
							</div>
						</div>
					</Card>
					
					{/* Individual Result Counts */}
					{stats.map((item, index) => (
						<Card key={index}>
							<div style={{ textAlign: 'center' }}>
								<h3 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#666', textTransform: 'uppercase' }}>
									{getResultLabel(item.verification_result)}
								</h3>
								<div style={{ fontSize: '32px', fontWeight: 'bold', color: getResultColor(item.verification_result) }}>
									{item.count}
								</div>
							</div>
						</Card>
					))}
				</div>

				{/* Charts */}
				{(chartData || reasonChartData) && (
					<div style={{
						display: 'grid',
						gridTemplateColumns: '1fr 1fr',
						gap: '20px',
						margin: '20px 0',
					}}>
						{chartData && (
							<Card>
								<Chart
									data={chartData}
									title={__('Verification Results Distribution', 'kickbox-integration')}
								/>
							</Card>
						)}

						{reasonChartData && (
							<Card>
								<Chart
									data={reasonChartData}
									title={__('Result Reason Distribution', 'kickbox-integration')}
								/>
							</Card>
						)}
					</div>
				)}

				{/* Success and Failure Rates - Under Charts */}
				{rates && (
					<div style={{
						display: 'grid',
						gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
						gap: '20px',
						marginBottom: '30px',
					}}>
						<Card>
							<div style={{ textAlign: 'center' }}>
								<h3 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#666', textTransform: 'uppercase' }}>
									{__('Success Rate', 'kickbox-integration')}
								</h3>
								<div style={{ fontSize: '32px', fontWeight: 'bold', color: '#00876c' }}>
									{rates.success_rate}%
								</div>
								<div style={{ fontSize: '12px', color: '#666', marginTop: '5px' }}>
									{rates.successful_verifications} {__('successful', 'kickbox-integration')}
								</div>
							</div>
						</Card>

						<Card>
							<div style={{ textAlign: 'center' }}>
								<h3 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#666', textTransform: 'uppercase' }}>
									{__('Failure Rate', 'kickbox-integration')}
								</h3>
								<div style={{ fontSize: '32px', fontWeight: 'bold', color: '#d43d51' }}>
									{rates.failure_rate}%
								</div>
								<div style={{ fontSize: '12px', color: '#666', marginTop: '5px' }}>
									{rates.failed_verifications} {__('failed', 'kickbox-integration')}
								</div>
							</div>
						</Card>
					</div>
				)}

				{/* No data message */}
				{!chartData && !reasonChartData && stats && stats.length > 0 && (
					<div style={{ textAlign: 'center', color: '#666', padding: '20px' }}>
						<p>{__('No verification data available to display.', 'kickbox-integration')}</p>
					</div>
				)}

				{/* Refresh button */}
				<div style={{ marginTop: '20px', textAlign: 'center' }}>
					<button
						type="button"
						className="button button-secondary"
						onClick={() => {
							setLoading(true);
							loadStats();
						}}
						disabled={loading}
					>
						{__('Refresh Statistics', 'kickbox-integration')}
					</button>
				</div>
			</div>
		</div>
	);
};

export default EmailVerificationReport;