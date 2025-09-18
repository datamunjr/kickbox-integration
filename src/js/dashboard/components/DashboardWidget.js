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

            const response = await fetch(wckb_dashboard.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wckb_dashboard_stats',
                    nonce: wckb_dashboard.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                setStats(data.data);
            } else {
                setError(data.data?.message || wckb_dashboard.strings.error);
            }
        } catch (error) {
            setError(wckb_dashboard.strings.error);
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
            deliverable: wckb_dashboard.strings.deliverable,
            undeliverable: wckb_dashboard.strings.undeliverable,
            risky: wckb_dashboard.strings.risky,
            unknown: wckb_dashboard.strings.unknown
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
            <div className="wckb-dashboard-loading">
                <span className="spinner is-active"></span>
                {wckb_dashboard.strings.loading}
            </div>
        );
    }

    if (error) {
        return (
            <div className="wckb-dashboard-error">
                <p>{error}</p>
                <button
                    type="button"
                    className="button button-small"
                    onClick={loadStats}
                >
                    {wckb_dashboard.strings.retry || 'Retry'}
                </button>
            </div>
        );
    }

    const chartData = prepareChartData();
    const totalVerifications = stats ? stats.reduce((sum, item) => sum + parseInt(item.count), 0) : 0;

    if (totalVerifications === 0) {
        return (
            <div className="wckb-dashboard-no-data">
                <p>{wckb_dashboard.strings.no_data}</p>
            </div>
        );
    }

    return (
        <div className="wckb-dashboard-widget">
            <div className="wckb-dashboard-stats">
                <div className="wckb-dashboard-stat">
                    <span className="wckb-dashboard-stat-number">{totalVerifications}</span>
                    <span className="wckb-dashboard-stat-label">{wckb_dashboard.strings.total}</span>
                </div>

                <div className="wckb-dashboard-stat">
          <span className="wckb-dashboard-stat-number">
            {stats ? stats.find(item => item.verification_result === 'deliverable')?.count || 0 : 0}
          </span>
                    <span className="wckb-dashboard-stat-label">{wckb_dashboard.strings.deliverable}</span>
                </div>

                <div className="wckb-dashboard-stat">
          <span className="wckb-dashboard-stat-number">
            {stats ? stats.find(item => item.verification_result === 'undeliverable')?.count || 0 : 0}
          </span>
                    <span className="wckb-dashboard-stat-label">{wckb_dashboard.strings.undeliverable}</span>
                </div>
            </div>

            {chartData && (
                <div className="wckb-dashboard-chart">
                    <Pie data={chartData} options={chartOptions}/>
                </div>
            )}

            <div className="wckb-dashboard-actions">
                <a
                    href={wckb_dashboard.admin_url || '#'}
                    className="button button-small"
                >
                    {wckb_dashboard.strings.view_details}
                </a>
            </div>
        </div>
    );
};

export default DashboardWidget;
