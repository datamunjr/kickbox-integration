import React, {useState, useEffect} from 'react';
import {Pie} from 'react-chartjs-2';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js';

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend);

const VerificationStats = () => {
    const [stats, setStats] = useState(null);
    const [reasonStats, setReasonStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadStats();
    }, []);

    const loadStats = async () => {
        try {
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_get_stats',
                    nonce: kickbox_integration_admin.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                setStats(data.data.verification_stats);
                setReasonStats(data.data.reason_stats);
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return <div className="kickbox_integration-loading">Loading statistics...</div>;
    }

    if (!stats) {
        return <div className="kickbox_integration-error">Unable to load statistics.</div>;
    }

    const getResultColor = (result) => {
        const colors = {
            deliverable: '#46b450',
            undeliverable: '#dc3232',
            risky: '#ffb900',
            unknown: '#00a0d2'
        };
        return colors[result] || '#666';
    };

    const getResultLabel = (result) => {
        const labels = {
            deliverable: 'Deliverable',
            undeliverable: 'Undeliverable',
            risky: 'Risky',
            unknown: 'Unknown'
        };
        return labels[result] || result;
    };

    const getReasonLabel = (reason) => {
        const labels = {
            invalid_email: 'Invalid Email',
            invalid_domain: 'Invalid Domain',
            rejected_email: 'Rejected Email',
            accepted_email: 'Accepted Email',
            low_quality: 'Low Quality',
            low_deliverability: 'Low Deliverability',
            no_connect: 'No Connect',
            timeout: 'Timeout',
            invalid_smtp: 'Invalid SMTP',
            unavailable_smtp: 'Unavailable SMTP',
            unexpected_error: 'Unexpected Error'
        };
        return labels[reason] || reason;
    };

    const getReasonColor = (reason) => {
        const colors = {
            invalid_email: '#dc3232',        // Red - Invalid email format
            invalid_domain: '#845a7b',       // Light red - Invalid domain
            rejected_email: '#c8a076',       // Dark red - Rejected email
            accepted_email: '#27ae60',       // Green - Accepted email
            low_quality: '#f39c12',          // Orange - Low quality
            low_deliverability: '#bfafa1',   // Dark orange - Low deliverability
            no_connect: '#601531',           // Blue - No connect
            timeout: '#2980b9',              // Dark blue - Timeout
            invalid_smtp: '#2e2733',         // Purple - Invalid SMTP
            unavailable_smtp: '#9b59b6',     // Light purple - Unavailable SMTP
            unexpected_error: '#95a5a6'      // Gray - Unexpected error
        };
        return colors[reason] || '#666';
    };

    // Prepare chart data for verification results
    const prepareChartData = () => {
        if (!stats || !Array.isArray(stats)) {
            return null;
        }

        const total = stats.reduce((sum, item) => sum + parseInt(item.count), 0);

        if (total === 0) {
            return null;
        }

        return {
            labels: stats.map(item => getResultLabel(item.verification_result)),
            datasets: [
                {
                    data: stats.map(item => parseInt(item.count)),
                    backgroundColor: stats.map(item => getResultColor(item.verification_result)),
                    borderColor: stats.map(item => getResultColor(item.verification_result)),
                    borderWidth: 2,
                    hoverBorderWidth: 3,
                }
            ]
        };
    };

    // Prepare chart data for result reasons
    const prepareReasonChartData = () => {
        if (!reasonStats || !Array.isArray(reasonStats)) {
            return null;
        }

        const total = reasonStats.reduce((sum, item) => sum + parseInt(item.count), 0);

        if (total === 0) {
            return null;
        }

        return {
            labels: reasonStats.map(item => getReasonLabel(item.result_reason)),
            datasets: [
                {
                    data: reasonStats.map(item => parseInt(item.count)),
                    backgroundColor: reasonStats.map(item => getReasonColor(item.result_reason)),
                    borderColor: reasonStats.map(item => getReasonColor(item.result_reason)),
                    borderWidth: 2,
                    hoverBorderWidth: 3,
                }
            ]
        };
    };

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
                        size: 12
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function (context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    };

    const chartData = prepareChartData();
    const reasonChartData = prepareReasonChartData();
    const totalVerifications = stats ? stats.reduce((sum, item) => sum + parseInt(item.count), 0) : 0;

    return (
        <div className="kickbox_integration-verification-stats">
            <div className="kickbox_integration-stats-grid">
                <div className="kickbox_integration-stat-card">
                    <h3>Total Verifications</h3>
                    <div className="kickbox_integration-stat-number">{totalVerifications}</div>
                </div>

                <div className="kickbox_integration-stat-card">
                    <h3>Deliverable</h3>
                    <div className="kickbox_integration-stat-number">
                        {stats ? stats.find(item => item.verification_result === 'deliverable')?.count || 0 : 0}
                    </div>
                </div>

                <div className="kickbox_integration-stat-card">
                    <h3>Undeliverable</h3>
                    <div className="kickbox_integration-stat-number">
                        {stats ? stats.find(item => item.verification_result === 'undeliverable')?.count || 0 : 0}
                    </div>
                </div>

                <div className="kickbox_integration-stat-card">
                    <h3>Risky</h3>
                    <div className="kickbox_integration-stat-number">
                        {stats ? stats.find(item => item.verification_result === 'risky')?.count || 0 : 0}
                    </div>
                </div>

                <div className="kickbox_integration-stat-card">
                    <h3>Unknown</h3>
                    <div className="kickbox_integration-stat-number">
                        {stats ? stats.find(item => item.verification_result === 'unknown')?.count || 0 : 0}
                    </div>
                </div>
            </div>

            <div className="kickbox_integration-charts-container">
                {chartData && (
                    <div className="kickbox_integration-pie-chart-container">
                        <h3>Verification Results Distribution</h3>
                        <div className="kickbox_integration-pie-chart">
                            <Pie data={chartData} options={chartOptions}/>
                        </div>
                    </div>
                )}

                {reasonChartData && (
                    <div className="kickbox_integration-pie-chart-container">
                        <h3>Result Reason Distribution</h3>
                        <div className="kickbox_integration-pie-chart">
                            <Pie data={reasonChartData} options={chartOptions}/>
                        </div>
                    </div>
                )}
            </div>

            {!chartData && !reasonChartData && stats && stats.length > 0 && (
                <div className="kickbox_integration-no-data">
                    <p>No verification data available to display.</p>
                </div>
            )}

            <div className="kickbox_integration-stats-actions">
                <button
                    type="button"
                    className="button button-secondary"
                    onClick={loadStats}
                >
                    Refresh Statistics
                </button>
            </div>
        </div>
    );
};

export default VerificationStats;
