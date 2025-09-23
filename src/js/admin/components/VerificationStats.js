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
                setStats(data.data);
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

    // Prepare chart data
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

            {chartData && (
                <div className="kickbox_integration-pie-chart-container">
                    <h3>Verification Results Distribution</h3>
                    <div className="kickbox_integration-pie-chart">
                        <Pie data={chartData} options={chartOptions}/>
                    </div>
                </div>
            )}

            {!chartData && stats && stats.length > 0 && (
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
