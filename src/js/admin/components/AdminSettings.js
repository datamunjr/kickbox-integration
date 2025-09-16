import React, { useState, useEffect } from 'react';
import ApiSettings from './ApiSettings';
import VerificationActions from './VerificationActions';
import VerificationStats from './VerificationStats';

const AdminSettings = () => {
  const [activeTab, setActiveTab] = useState(() => {
    // Get tab from URL parameter, default to 'api'
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    return ['api', 'actions', 'stats'].includes(tab) ? tab : 'api';
  });
  const [settings, setSettings] = useState({
    apiKey: '',
    sandboxMode: true,
    deliverableAction: 'allow',
    undeliverableAction: 'allow',
    riskyAction: 'allow',
    unknownAction: 'allow',
    enableCheckoutVerification: false,
    enableCustomerVerification: false
  });
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });

  useEffect(() => {
    // Load current settings
    loadSettings();
    
    // Set default tab if none is specified
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    
    if (!tab) {
      // No tab parameter, set default to 'api' and update URL
      const url = new URL(window.location);
      url.searchParams.set('tab', 'api');
      window.history.replaceState({}, '', url);
    }
    
    // Listen for browser back/forward navigation
    const handlePopState = () => {
      const urlParams = new URLSearchParams(window.location.search);
      const tab = urlParams.get('tab');
      if (['api', 'actions', 'stats'].includes(tab)) {
        setActiveTab(tab);
      }
    };
    
    window.addEventListener('popstate', handlePopState);
    
    return () => {
      window.removeEventListener('popstate', handlePopState);
    };
  }, []);

  // Set initial page title
  useEffect(() => {
    const tab = tabs.find(t => t.id === activeTab);
    if (tab) {
      document.title = `Kickbox Integration - ${tab.label} | WordPress Admin`;
    }
  }, [activeTab]);

  const loadSettings = async () => {
    try {
      const response = await fetch(wckb_admin.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_get_settings',
          nonce: wckb_admin.nonce
        })
      });
      
      const data = await response.json();
      if (data.success) {
        setSettings(data.data);
      }
    } catch (error) {
      console.error('Error loading settings:', error);
    }
  };

  const saveSettings = async () => {
    setLoading(true);
    setMessage({ type: '', text: '' });

    try {
      const response = await fetch(wckb_admin.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_save_settings',
          nonce: wckb_admin.nonce,
          ...settings
        })
      });
      
      const data = await response.json();
      if (data.success) {
        setMessage({ type: 'success', text: 'Settings saved successfully!' });
      } else {
        setMessage({ type: 'error', text: data.data.message || 'Error saving settings' });
      }
    } catch (error) {
      setMessage({ type: 'error', text: 'Error saving settings' });
    } finally {
      setLoading(false);
    }
  };

  const testApiConnection = async () => {
    setLoading(true);
    setMessage({ type: '', text: '' });

    try {
      const response = await fetch(wckb_admin.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_test_api',
          nonce: wckb_admin.nonce,
          api_key: settings.apiKey,
          sandbox_mode: settings.sandboxMode ? 'yes' : 'no'
        })
      });
      
      const data = await response.json();
      if (data.success) {
        setMessage({ type: 'success', text: wckb_admin.strings.api_success });
      } else {
        setMessage({ type: 'error', text: data.data.message || wckb_admin.strings.api_error });
      }
    } catch (error) {
      setMessage({ type: 'error', text: wckb_admin.strings.api_error });
    } finally {
      setLoading(false);
    }
  };

  const handleSettingChange = (key, value) => {
    setSettings(prev => ({
      ...prev,
      [key]: value
    }));
  };

  const handleTabChange = (tabId) => {
    setActiveTab(tabId);
    
    // Update URL parameter without page reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
    
    // Update page title to reflect current tab
    const tab = tabs.find(t => t.id === tabId);
    if (tab) {
      document.title = `Kickbox Integration - ${tab.label} | WordPress Admin`;
    }
  };

  const tabs = [
    { id: 'api', label: 'API Settings', component: ApiSettings },
    { id: 'actions', label: 'Verification Actions', component: VerificationActions },
    { id: 'stats', label: 'Statistics', component: VerificationStats }
  ];

  const ActiveComponent = tabs.find(tab => tab.id === activeTab)?.component;

  return (
    <div className="wckb-admin-container">
      <div className="wckb-header">
        <h2>Kickbox Integration Settings</h2>
        {message.text && (
          <div className={`notice notice-${message.type}`}>
            <p>{message.text}</p>
          </div>
        )}
      </div>

      <div className="wckb-tabs">
        {tabs.map(tab => (
          <button
            key={tab.id}
            className={`wckb-tab ${activeTab === tab.id ? 'active' : ''}`}
            onClick={() => handleTabChange(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="wckb-tab-content">
        {ActiveComponent && (
          <ActiveComponent
            settings={settings}
            onSettingChange={handleSettingChange}
            loading={loading}
            onTestApi={testApiConnection}
          />
        )}
      </div>

      <div className="wckb-actions">
        <button
          type="button"
          className="button button-primary"
          onClick={saveSettings}
          disabled={loading}
        >
          {loading ? 'Saving...' : 'Save Settings'}
        </button>
      </div>
    </div>
  );
};

export default AdminSettings;
