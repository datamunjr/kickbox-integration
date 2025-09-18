import React, { useState, useEffect } from 'react';

const AllowList = ({ settings, onSettingChange }) => {
  const [allowList, setAllowList] = useState([]);
  const [newEmail, setNewEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    if (settings.allowList) {
      setAllowList(settings.allowList);
    }
  }, [settings.allowList]);

  const addEmail = async () => {
    if (!newEmail.trim()) {
      setError('Please enter an email address.');
      return;
    }

    if (!isValidEmail(newEmail)) {
      setError('Please enter a valid email address.');
      return;
    }

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const response = await fetch(wckb_admin.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_add_to_allow_list',
          nonce: wckb_admin.nonce,
          email: newEmail.trim()
        })
      });

      const data = await response.json();

      if (data.success) {
        setSuccess(data.data.message);
        setNewEmail('');
        // Refresh the allow list
        loadAllowList();
      } else {
        setError(data.data.message || 'Failed to add email to allow list.');
      }
    } catch (error) {
      setError('An error occurred while adding the email.');
    } finally {
      setLoading(false);
    }
  };

  const removeEmail = async (email) => {
    if (!confirm(`Are you sure you want to remove "${email}" from the allow list?`)) {
      return;
    }

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const response = await fetch(wckb_admin.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_remove_from_allow_list',
          nonce: wckb_admin.nonce,
          email: email
        })
      });

      const data = await response.json();

      if (data.success) {
        setSuccess(data.data.message);
        // Refresh the allow list
        loadAllowList();
      } else {
        setError(data.data.message || 'Failed to remove email from allow list.');
      }
    } catch (error) {
      setError('An error occurred while removing the email.');
    } finally {
      setLoading(false);
    }
  };

  const loadAllowList = async () => {
    try {
      const response = await fetch(wckb_admin.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_get_allow_list',
          nonce: wckb_admin.nonce
        })
      });

      const data = await response.json();

      if (data.success) {
        setAllowList(data.data);
      }
    } catch (error) {
      console.error('Failed to load allow list:', error);
    }
  };

  const isValidEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const handleKeyPress = (e) => {
    if (e.key === 'Enter') {
      addEmail();
    }
  };

  return (
    <div className="wckb-allow-list">
      <div className="wckb-allow-list-header">
        <h3>Email Allow List</h3>
        <p className="description">
          Emails in this list will skip Kickbox verification during checkout. 
          This is useful for trusted customers, test accounts, or special cases.
        </p>
      </div>

      {error && (
        <div className="notice notice-error inline">
          <p>{error}</p>
        </div>
      )}

      {success && (
        <div className="notice notice-success inline">
          <p>{success}</p>
        </div>
      )}

      <div className="wckb-add-email-section">
        <h4>Add Email to Allow List</h4>
        <div className="wckb-add-email-form">
          <input
            type="email"
            value={newEmail}
            onChange={(e) => setNewEmail(e.target.value)}
            onKeyPress={handleKeyPress}
            placeholder="Enter email address"
            className="regular-text"
            disabled={loading}
          />
          <button
            type="button"
            onClick={addEmail}
            disabled={loading || !newEmail.trim()}
            className="button button-primary"
          >
            {loading ? 'Adding...' : 'Add Email'}
          </button>
        </div>
      </div>

      <div className="wckb-allow-list-section">
        <h4>Current Allow List ({allowList.length} emails)</h4>
        
        {allowList.length === 0 ? (
          <div className="wckb-empty-allow-list">
            <p>No emails in the allow list. Add emails above to skip verification for specific addresses.</p>
          </div>
        ) : (
          <div className="wckb-allow-list-items">
            {allowList.map((email, index) => (
              <div key={index} className="wckb-allow-list-item">
                <span className="wckb-email-address">{email}</span>
                <button
                  type="button"
                  onClick={() => removeEmail(email)}
                  disabled={loading}
                  className="button button-link-delete"
                  title="Remove from allow list"
                >
                  Remove
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="wckb-allow-list-info">
        <h4>How the Allow List Works</h4>
        <ul>
          <li><strong>Skip Verification:</strong> Emails in this list bypass Kickbox verification entirely</li>
          <li><strong>Case Insensitive:</strong> Email matching is case-insensitive</li>
          <li><strong>Immediate Effect:</strong> Changes take effect immediately for new checkouts</li>
          <li><strong>Use Cases:</strong> Trusted customers, test accounts, VIP customers, or special business cases</li>
        </ul>
      </div>
    </div>
  );
};

export default AllowList;
