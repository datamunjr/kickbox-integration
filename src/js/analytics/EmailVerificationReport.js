import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Pie } from 'react-chartjs-2';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { useRef, Fragment } from '@wordpress/element';
import { Spinner, Card, Button, Popover } from '@wordpress/components';
import { DateRange } from '@woocommerce/components';
import { withViewportMatch } from '@wordpress/viewport';
import moment from 'moment';
import PropTypes from 'prop-types';

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend);

// Chart.js Pie Chart component
const Chart = ({ data, title }) => {
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

// EmailVerificationReportVisuals Component
const EmailVerificationReportVisuals = ({ stats, rates, chartData, reasonChartData, getResultLabel, getResultColor }) => {
	const totalVerifications = stats ? stats.reduce((sum, item) => sum + parseInt(item.count), 0) : 0;

	// Show special message if no verifications found
	if (totalVerifications === 0) {
		return (
			<Card style={{ marginTop: '20px' }}>
				<div style={{ 
					textAlign: 'center', 
					padding: '40px 20px',
					color: '#666'
				}}>
					<div style={{ 
						fontSize: '48px', 
						marginBottom: '16px',
						opacity: 0.5
					}}>
						📊
					</div>
					<h3 style={{ 
						margin: '0 0 8px 0', 
						fontSize: '18px', 
						color: '#333' 
					}}>
						{__('No Verifications Found', 'kickbox-integration')}
					</h3>
					<p style={{ 
						margin: '0 0 16px 0', 
						fontSize: '14px' 
					}}>
						{__('Could not find any verifications for this time range!', 'kickbox-integration')}
					</p>
					<p style={{ 
						margin: '0', 
						fontSize: '12px', 
						color: '#999' 
					}}>
						{__('Try adjusting your date range or check if verifications exist for other periods.', 'kickbox-integration')}
					</p>
				</div>
			</Card>
		);
	}

	return (
		<Fragment>
			{/* Verification Statistics */}
			<div style={{
				display: 'grid',
				gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
				gap: '20px',
				marginBottom: '30px',
			}}>
				{/* Total Verifications Card */}
				<Card>
					<div style={{ textAlign: 'center', padding: '20px' }}>
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
						<div style={{ textAlign: 'center', padding: '20px' }}>
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
						<div style={{ textAlign: 'center', padding: '20px' }}>
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
						<div style={{ textAlign: 'center', padding: '20px' }}>
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
					{__('No chart data available for the selected period.', 'kickbox-integration')}
				</div>
			)}
		</Fragment>
	);
};

const EmailVerificationReport = () => {
	const [stats, setStats] = useState(null);
	const [reasonStats, setReasonStats] = useState(null);
	const [rates, setRates] = useState(null);
	const [loading, setLoading] = useState(true);
	const [dateRangeState, setDateRangeState] = useState({
		after: null,
		before: null,
		focusedInput: 'startDate',
		afterText: '',
		beforeText: '',
		afterError: null,
		beforeError: null,
	});
	const [isPopoverOpen, setIsPopoverOpen] = useState(false);
	const controlsRef = useRef(null);

	useEffect(() => {
		// Check for URL parameters first
		const urlParams = new URLSearchParams(window.location.search);
		const startDate = urlParams.get('start_date');
		const endDate = urlParams.get('end_date');
		
		let shouldLoadWithDates = false;
		
		if (startDate && endDate) {
			// Validate date format and security - only allow YYYY-MM-DD format
			const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
			
			if (dateRegex.test(startDate) && dateRegex.test(endDate)) {
				const start = moment(startDate, 'YYYY-MM-DD', true); // strict parsing
				const end = moment(endDate, 'YYYY-MM-DD', true); // strict parsing
				
				if (start.isValid() && end.isValid() && start.isSameOrBefore(end, 'day')) {
					setDateRangeState(prevState => ({
						...prevState,
						after: start,
						before: end,
						afterText: start.format('YYYY-MM-DD'),
						beforeText: end.format('YYYY-MM-DD'),
					}));
					shouldLoadWithDates = true;
				}
			}
		}
		
		// Load stats with or without date filtering
		if (shouldLoadWithDates) {
			const start = moment(startDate, 'YYYY-MM-DD', true);
			const end = moment(endDate, 'YYYY-MM-DD', true);
			loadStats(start, end);
		} else {
			loadStats();
		}
	}, []);


	// Helper methods for dropdown functionality
	const getButtonLabel = () => {
		if (dateRangeState.after && dateRangeState.before) {
			return {
				primary: `${dateRangeState.after.format('MMM DD, YYYY')} - ${dateRangeState.before.format('MMM DD, YYYY')}`,
			};
		} else if (dateRangeState.after) {
			return {
				primary: `From ${dateRangeState.after.format('MMM DD, YYYY')}`,
			};
		} else if (dateRangeState.before) {
			return {
				primary: `Until ${dateRangeState.before.format('MMM DD, YYYY')}`,
			};
		}
		return {
			primary: __('Select date range', 'kickbox-integration'),
		};
	};

	const isFutureDate = (date) => {
		return moment(date).isAfter(moment(), 'day');
	};

	const isValidDateRange = () => {
		return dateRangeState.after && dateRangeState.before && 
			   dateRangeState.after.isValid() && dateRangeState.before.isValid() &&
			   dateRangeState.after.isSameOrBefore(dateRangeState.before, 'day');
	};

	const handleDateUpdate = (updateData) => {
		// Just like WooCommerce: this.setState(update)
		setDateRangeState(prevState => ({
			...prevState,
			...updateData,
		}));
	};

	const handleUpdateDateRange = () => {
		// Update URL parameters
		const url = new URL(window.location);
		if (dateRangeState.after && dateRangeState.before) {
			url.searchParams.set('start_date', dateRangeState.after.format('YYYY-MM-DD'));
			url.searchParams.set('end_date', dateRangeState.before.format('YYYY-MM-DD'));
		} else {
			url.searchParams.delete('start_date');
			url.searchParams.delete('end_date');
		}
		window.history.pushState({}, '', url);
		
		// Reload stats with current dates
		if (dateRangeState.after || dateRangeState.before) {
			setLoading(true);
			loadStats(dateRangeState.after, dateRangeState.before);
		}

		// Close popover
		setIsPopoverOpen(false);
	};

	const handleClearDateRange = () => {
		// Clear state only
		const clearedState = {
			after: null,
			before: null,
			focusedInput: 'startDate',
			afterText: '',
			beforeText: '',
			afterError: null,
			beforeError: null,
		};
		setDateRangeState(clearedState);
	};

	const handleClearDatesAndRefresh = () => {
		// Remove URL parameters and refresh the page
		const url = new URL(window.location);
		url.searchParams.delete('start_date');
		url.searchParams.delete('end_date');
		window.location.href = url.toString();
	};


	const loadStats = async (customStartDate = null, customEndDate = null) => {
		try {
			const params = {
				action: 'kickbox_integration_get_stats',
				nonce: kickboxAnalytics.nonce,
			};

			// Use custom dates if provided, otherwise use state dates
			const effectiveStartDate = customStartDate || dateRangeState.after;
			const effectiveEndDate = customEndDate || dateRangeState.before;

			// Add date parameters if provided
			if (effectiveStartDate) {
				params.start_date = effectiveStartDate.format('YYYY-MM-DD');
			}
			if (effectiveEndDate) {
				params.end_date = effectiveEndDate.format('YYYY-MM-DD');
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
			} else {
				// Handle error response
				console.error('Analytics Error:', data.data);
				
				let errorMessage = data.data.message;
				if (data.data.error_details && Array.isArray(data.data.error_details)) {
					errorMessage += '\n\nDatabase Errors:\n' + data.data.error_details.join('\n');
				} else if (data.data.error_details) {
					errorMessage += '\n\nError Details: ' + data.data.error_details;
				}
				
				alert(`Error loading statistics: ${errorMessage}`);
			}
		} catch (error) {
			// Handle network or other errors
			console.error('Network Error:', error);
			alert('Network error occurred while loading statistics. Please try again.');
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

	if (loading) {
		return (
			<div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '50vh' }}>
				<Spinner />{__('Loading statistics...', 'kickbox-integration')}
			</div>
		);
	}

	if (!stats) {
		return (
			<div style={{ padding: '20px' }}>
				<div style={{ padding: '20px' }}>
					<div style={{ color: '#dc3232', textAlign: 'center' }}>
						{__('Unable to load statistics.', 'kickbox-integration')}
					</div>
				</div>
			</div>
		);
	}

	return (
		<div>
			<div>
				<div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '20px' }}>
					<div className="kickbox-date-range-filter" style={{ position: 'relative' }}>
						<Button
							ref={controlsRef}
							variant="secondary"
							onClick={() => setIsPopoverOpen(!isPopoverOpen)}
							style={{
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'space-between',
								minWidth: '200px',
								textAlign: 'left',
								background: 'white',
							}}
						>
							<span>{getButtonLabel().primary}</span>
							<span style={{ marginLeft: '8px' }}>{isPopoverOpen ? '▲' : '▼'}</span>
						</Button>

					{isPopoverOpen && (
						<Popover 
							anchorRef={controlsRef}
							offset={5}
						>
							<div style={{ padding: '16px', minWidth: '400px' }}>
								<DateRange
									after={dateRangeState.after}
									before={dateRangeState.before}
									onUpdate={handleDateUpdate}
									isInvalidDate={isFutureDate}
									focusedInput={dateRangeState.focusedInput}
									afterText={dateRangeState.afterText}
									beforeText={dateRangeState.beforeText}
									afterError={dateRangeState.afterError}
									beforeError={dateRangeState.beforeError}
									shortDateFormat="YYYY-MM-DD"
									shortDateFormatPlaceholder="YYYY-MM-DD"
									losesFocusTo={controlsRef.current}
								/>
								<div
									style={{
										display: 'flex',
										justifyContent: 'space-between',
										gap: '8px',
										marginTop: '16px',
										paddingTop: '16px',
										borderTop: '1px solid #e0e0e0',
									}}
								>
									<Button
										variant="secondary"
										onClick={handleClearDateRange}
									>
										{__('Clear', 'kickbox-integration')}
									</Button>
									<Button
										variant="primary"
										onClick={handleUpdateDateRange}
										disabled={!isValidDateRange()}
									>
										{__('Update', 'kickbox-integration')}
									</Button>
								</div>
							</div>
						</Popover>
					)}
					</div>
					
					{/* Clear Dates Button */}
					<button
						type="button"
						className="button button-secondary"
						onClick={handleClearDatesAndRefresh}
					>
						{__('Clear Dates', 'kickbox-integration')}
					</button>
				</div>

				{/* Visual Elements */}
				<EmailVerificationReportVisuals
					stats={stats}
					reasonStats={reasonStats}
					rates={rates}
					chartData={chartData}
					reasonChartData={reasonChartData}
					getResultLabel={getResultLabel}
					getResultColor={getResultColor}
				/>

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

// PropTypes for EmailVerificationReport component
EmailVerificationReport.propTypes = {
	query: PropTypes.object,
	path: PropTypes.string,
	pathMatch: PropTypes.object,
	params: PropTypes.object,
	isViewportMobile: PropTypes.bool,
	isViewportSmall: PropTypes.bool,
};

// Wrap with viewport matching like WooCommerce DateRange component
export default withViewportMatch({
	isViewportMobile: '< medium',
	isViewportSmall: '< small',
})(EmailVerificationReport);