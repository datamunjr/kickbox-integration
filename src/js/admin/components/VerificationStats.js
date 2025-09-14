import React, { useState, useEffect } from 'react';

const VerificationStats = () => {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadStats();
  }, []);

  const loadStats = async () => {
    try {
      const response = await fetch(wckb_admin.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_get_stats',
          nonce: wckb_admin.nonce
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
    return <div className="wckb-loading">Loading statistics...</div>;
  }

  if (!stats) {
    return <div className="wckb-error">Unable to load statistics.</div>;
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

  return (
    <div className="wckb-verification-stats">
      <div className="wckb-stats-grid">
        <div className="wckb-stat-card">
          <h3>Total Users</h3>
          <div className="wckb-stat-number">{stats.total_users || 0}</div>
        </div>
        
        <div className="wckb-stat-card">
          <h3>Verified Users</h3>
          <div className="wckb-stat-number">{stats.verified_users || 0}</div>
        </div>
        
        <div className="wckb-stat-card">
          <h3>Verification Rate</h3>
          <div className="wckb-stat-number">
            {stats.total_users > 0 
              ? Math.round((stats.verified_users / stats.total_users) * 100) 
              : 0}%
          </div>
        </div>
      </div>

      {stats.verification_results && stats.verification_results.length > 0 && (
        <div className="wckb-results-breakdown">
          <h3>Verification Results Breakdown</h3>
          <div className="wckb-results-list">
            {stats.verification_results.map((result, index) => (
              <div key={index} className="wckb-result-item">
                <div 
                  className="wckb-result-color" 
                  style={{ backgroundColor: getResultColor(result.verification_result) }}
                ></div>
                <div className="wckb-result-info">
                  <span className="wckb-result-label">
                    {getResultLabel(result.verification_result)}
                  </span>
                  <span className="wckb-result-count">{result.count}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="wckb-stats-actions">
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
