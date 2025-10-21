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

const DashboardWidget = () => {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        loadStats();
    }, []);

    const loadStats = async () => {
        try {
            setLoading(true);
            setError('');

            const response = await fetch(kickbox_integration_dashboard.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_dashboard_stats',
                    nonce: kickbox_integration_dashboard.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                setStats(data.data);
            } else {
                setError(data.data?.message || kickbox_integration_dashboard.strings.error);
            }
        } catch (error) {
            setError(kickbox_integration_dashboard.strings.error);
        } finally {
            setLoading(false);
        }
    };

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
            deliverable: kickbox_integration_dashboard.strings.deliverable,
            undeliverable: kickbox_integration_dashboard.strings.undeliverable,
            risky: kickbox_integration_dashboard.strings.risky,
            unknown: kickbox_integration_dashboard.strings.unknown
        };
        return labels[result] || result;
    };

    // Prepare chart data
    const prepareChartData = () => {
        if (!stats || !stats.verification_stats || !Array.isArray(stats.verification_stats)) {
            return null;
        }

        const total = stats.verification_stats.reduce((sum, item) => sum + parseInt(item.count), 0);

        if (total === 0) {
            return null;
        }

        return {
            labels: stats.verification_stats.map(item => getResultLabel(item.verification_result)),
            datasets: [
                {
                    data: stats.verification_stats.map(item => parseInt(item.count)),
                    backgroundColor: stats.verification_stats.map(item => getResultColor(item.verification_result)),
                    borderColor: stats.verification_stats.map(item => getResultColor(item.verification_result)),
                    borderWidth: 1,
                    hoverBorderWidth: 2,
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
                    padding: 15,
                    usePointStyle: true,
                    font: {
                        size: 11
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

    if (loading) {
        return (
            <div className="kickbox_integration-dashboard-loading">
                <span className="spinner is-active"></span>
                {kickbox_integration_dashboard.strings.loading}
            </div>
        );
    }

    if (error) {
        return (
            <div className="kickbox_integration-dashboard-error">
                <p>{error}</p>
                <button
                    type="button"
                    className="button button-small"
                    onClick={loadStats}
                >
                    {kickbox_integration_dashboard.strings.retry || 'Retry'}
                </button>
            </div>
        );
    }

    const chartData = prepareChartData();
    const totalVerifications = stats && stats.verification_stats ? stats.verification_stats.reduce((sum, item) => sum + parseInt(item.count), 0) : 0;

    if (totalVerifications === 0) {
        return (
            <div className="kickbox_integration-dashboard-no-data">
                <p>{kickbox_integration_dashboard.strings.no_data}</p>
            </div>
        );
    }

    return (
        <div className="kickbox_integration-dashboard-widget">
            <div className="kickbox_integration-dashboard-stats">
                <div className="kickbox_integration-dashboard-stat">
                    <span className="kickbox_integration-dashboard-stat-number">{totalVerifications}</span>
                    <span className="kickbox_integration-dashboard-stat-label">{kickbox_integration_dashboard.strings.total}</span>
                </div>

                <div className="kickbox_integration-dashboard-stat">
          <span className="kickbox_integration-dashboard-stat-number">
            {stats && stats.verification_stats ? stats.verification_stats.find(item => item.verification_result === 'deliverable')?.count || 0 : 0}
          </span>
                    <span className="kickbox_integration-dashboard-stat-label">{kickbox_integration_dashboard.strings.deliverable}</span>
                </div>

                <div className="kickbox_integration-dashboard-stat">
          <span className="kickbox_integration-dashboard-stat-number">
            {stats && stats.verification_stats ? stats.verification_stats.find(item => item.verification_result === 'undeliverable')?.count || 0 : 0}
          </span>
                    <span className="kickbox_integration-dashboard-stat-label">{kickbox_integration_dashboard.strings.undeliverable}</span>
                </div>
            </div>

            {chartData && (
                <div className="kickbox_integration-dashboard-chart">
                    <Pie data={chartData} options={chartOptions}/>
                </div>
            )}

            <div className="kickbox_integration-dashboard-actions">
                <a
                    href={kickbox_integration_dashboard.admin_url || '#'}
                    className="button button-small"
                >
                    {kickbox_integration_dashboard.strings.view_details}
                </a>
            </div>
        </div>
    );
};

export default DashboardWidget;
