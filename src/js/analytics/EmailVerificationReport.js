import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Pie } from 'react-chartjs-2';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';

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
            boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
        }} {...props}>
            {children}
        </div>
    );
};

const SectionHeader = ({ title, children, ...props }) => {
    const WooCommerceSectionHeader = wc?.components?.SectionHeader;
    if (WooCommerceSectionHeader) {
        return <WooCommerceSectionHeader title={title} {...props}>{children}</WooCommerceSectionHeader>;
    }
    
    // Fallback to custom component
    return (
        <div style={{ 
            padding: '16px 20px', 
            borderBottom: '1px solid #ddd',
            backgroundColor: '#f8f9fa'
        }} {...props}>
            <h2 style={{ margin: 0, fontSize: '18px', fontWeight: '600' }}>
                {title}
            </h2>
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
            boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
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
                        size: 12
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
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

    return (
        <div style={{ padding: '20px' }}>
            <h4 style={{ marginTop: 0, marginBottom: '20px', textAlign: 'center' }}>{title}</h4>
            <div style={{ position: 'relative', height: '400px', maxWidth: '500px', margin: '0 auto' }}>
                <Pie data={data} options={chartOptions} />
            </div>
        </div>
    );
};

// WordPress Spinner component
const Spinner = ({ message = 'Loading...' }) => {
    return (
        <div style={{
            textAlign: 'center',
            padding: '40px 20px',
            fontSize: '16px',
            color: '#666'
        }}>
            <span className="spinner is-active" style={{
                float: 'none',
                marginRight: '10px',
                verticalAlign: 'top'
            }}></span>
            {message}
        </div>
    );
};

const EmailVerificationReport = ({ query, path, pathMatch, params }) => {
    const [stats, setStats] = useState(null);
    const [reasonStats, setReasonStats] = useState(null);
    const [loading, setLoading] = useState(true);

    // Debug WooCommerce components availability
    useEffect(() => {
        console.log('WooCommerce components available:', {
            wc: typeof wc,
            wcComponents: typeof wc?.components,
            section: typeof wc?.components?.Section,
            card: typeof wc?.components?.Card,
            sectionHeader: typeof wc?.components?.SectionHeader
        });
    }, []);

    useEffect(() => {
        loadStats();
    }, []);

    const loadStats = async () => {
        try {
            const response = await fetch(kickboxAnalytics.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_get_stats',
                    nonce: kickboxAnalytics.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                setStats(data.data.verification_stats);
                setReasonStats(data.data.reason_stats);
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
            unknown: '#d6ec91'
        };
        return colors[result] || '#88c580';
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
            'unknown': 'Unknown'
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
            }]
        } : null;

        const reasonChartData = reasonStats && reasonStats.length > 0 ? {
            labels: reasonStats.map(item => getReasonLabel(item.result_reason)),
            datasets: [{
                data: reasonStats.map(item => parseInt(item.count)),
                backgroundColor: [
                    '#00876c', '#3d9c73', '#63b179', '#88c580', '#aed987',
                    '#d6ec91', '#ffff9d', '#fee17e', '#fcc267', '#f7a258',
                    '#ef8250', '#e4604e', '#d43d51'
                ],
                borderColor: [
                    '#00876c', '#3d9c73', '#63b179', '#88c580', '#aed987',
                    '#d6ec91', '#ffff9d', '#fee17e', '#fcc267', '#f7a258',
                    '#ef8250', '#e4604e', '#d43d51'
                ],
                borderWidth: 2,
                hoverBorderWidth: 3,
            }]
        } : null;

        // Debug logging to see what data we're getting
        console.log('Stats data:', stats);
        console.log('Reason stats data:', reasonStats);
        console.log('WooCommerce Chart data:', chartData);
        console.log('WooCommerce Reason chart data:', reasonChartData);

    if (loading) {
        return (
            <div style={{ padding: '20px' }}>
                <Section>
                    <SectionHeader title={__('Email Verification Analytics', 'kickbox-integration')} />
                    <div style={{ padding: '20px' }}>
                        <Spinner message={__('Loading statistics...', 'kickbox-integration')} />
                    </div>
                </Section>
            </div>
        );
    }

    if (!stats) {
        return (
            <div style={{ padding: '20px' }}>
                <Section>
                    <SectionHeader title={__('Email Verification Analytics', 'kickbox-integration')} />
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
        <div style={{ padding: '20px' }}>
            <Section>
                <SectionHeader title={__('Email Verification Analytics', 'kickbox-integration')} />
                <div style={{ padding: '20px' }}>
                    {/* Statistics Grid */}
                    <div style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                        gap: '20px',
                        marginBottom: '30px'
                    }}>
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
                                margin: '20px 0'
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
                </Section>
            </div>
    );
};

export default EmailVerificationReport;