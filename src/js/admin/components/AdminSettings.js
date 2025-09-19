import React, {useState, useEffect} from 'react';
import ApiSettings from './ApiSettings';
import VerificationActions from './VerificationActions';
import VerificationStats from './VerificationStats';
import AllowList from './AllowList';
import FlaggedEmails from './FlaggedEmails';

const AdminSettings = () => {
    const [activeTab, setActiveTab] = useState(() => {
        // Get tab from URL parameter, default to 'api'
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        return ['api', 'actions', 'allowlist', 'flagged', 'stats'].includes(tab) ? tab : 'api';
    });
    const [settings, setSettings] = useState({
        apiKey: '',
        deliverableAction: 'allow',
        undeliverableAction: 'allow',
        riskyAction: 'allow',
        unknownAction: 'allow',
        enableCheckoutVerification: false,
        enableCustomerVerification: false
    });
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState({type: '', text: '', details: null, hasDetails: false});
    const [showErrorDetails, setShowErrorDetails] = useState(false);
    const [apiKeyValidatedOnSave, setApiKeyValidatedOnSave] = useState(false);

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
        if (['api', 'actions', 'allowlist', 'flagged', 'stats'].includes(tab)) {
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
            console.log(data);
            if (data.success) {
                setSettings(data.data);
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        }
    };

    const saveSettings = async () => {
        setLoading(true);
        setMessage({type: '', text: ''});

        try {
            console.log(settings);
            const body = {
                action: 'wckb_save_settings',
                nonce: wckb_admin.nonce,
                apiKey: settings.apiKey,
                deliverableAction: settings.deliverableAction,
                undeliverableAction: settings.undeliverableAction,
                riskyAction: settings.riskyAction,
                unknownAction: settings.unknownAction,
                enableCheckoutVerification: settings.enableCheckoutVerification,
                enableCustomerVerification: settings.enableCustomerVerification,
                skipValidation: Boolean(!settings?.hasApiKeyChanged).toString()
            }
            console.log(body);
            const response = await fetch(wckb_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(body)
            });

            const data = await response.json();
            if (data.success) {
                let messageText = data.data.message;
                let apiModeInfo = data.data.api_mode_info;

                // If API key was validated, show mode information
                if (apiModeInfo && apiModeInfo.validated) {
                    messageText += ' ' + apiModeInfo.mode_text;
                }

                setMessage({
                    type: 'success',
                    text: messageText,
                    details: null,
                    hasDetails: false
                });

                // If API key was validated during save, trigger validation success
                if (apiModeInfo && apiModeInfo.validated) {
                    setApiKeyValidatedOnSave(true);
                }
            } else {
                setMessage({
                    type: 'error',
                    text: data.data.message || 'Error saving settings',
                    details: data.data.details || null,
                    hasDetails: data.data.has_details || false
                });
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            setMessage({
                type: 'error',
                text: 'Error saving settings',
                details: error,
                hasDetails: true
            });
        } finally {
            setLoading(false);
        }
    };

    const testApiConnection = async (onSuccess) => {
        setLoading(true);
        setMessage({type: '', text: ''});

        try {
            const response = await fetch(wckb_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wckb_test_api',
                    nonce: wckb_admin.nonce,
                    api_key: settings.apiKey
                })
            });

            const data = await response.json();
            if (data.success) {
                setMessage({type: 'success', text: wckb_admin.strings.api_success, details: null, hasDetails: false});
                // Call the success callback if provided
                if (onSuccess) {
                    onSuccess();
                }
            } else {
                setMessage({
                    type: 'error',
                    text: data.data.message || wckb_admin.strings.api_error,
                    details: data.data.details || null,
                    hasDetails: data.data.has_details || false
                });
            }
        } catch (error) {
            setMessage({type: 'error', text: wckb_admin.strings.api_error, details: null, hasDetails: false});
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

    // Function to render error details
    const renderErrorDetails = (details) => {
        if (!details) return null;

        return (
            <div className="wckb-error-details" style={{
                marginTop: '10px',
                padding: '10px',
                backgroundColor: '#f9f9f9',
                border: '1px solid #ddd',
                borderRadius: '3px'
            }}>
        <pre style={{margin: 0, fontSize: '12px', whiteSpace: 'pre-wrap', wordBreak: 'break-word'}}>
          {JSON.stringify(details, null, 2)}
        </pre>
            </div>
        );
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
        {id: 'api', label: 'API Settings', component: ApiSettings},
        {id: 'actions', label: 'Verification Actions', component: VerificationActions},
        {id: 'allowlist', label: 'Allow List', component: AllowList},
        {id: 'flagged', label: 'Review Flagged Emails', component: FlaggedEmails},
        {id: 'stats', label: 'Statistics', component: VerificationStats}
    ];

    const ActiveComponent = tabs.find(tab => tab.id === activeTab)?.component;

    return (
        <div className="wckb-admin-container">
            <div className="wckb-header">
                <h2>Kickbox Integration Settings</h2>
                {message.text && (
                    <div className={`notice notice-${message.type}`}>
                        <p>
                            {message.text}
                            {message.hasDetails && (
                                <span style={{marginLeft: '10px'}}>
                  <button
                      type="button"
                      className="button button-small"
                      onClick={() => setShowErrorDetails(!showErrorDetails)}
                      style={{fontSize: '11px', padding: '2px 8px', height: 'auto'}}
                  >
                    {showErrorDetails ? 'Hide details' : 'More details'}
                  </button>
                </span>
                            )}
                        </p>
                        {message.hasDetails && showErrorDetails && renderErrorDetails(message.details)}
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
                        apiKeyValidatedOnSave={apiKeyValidatedOnSave}
                        onApiKeyValidatedOnSave={() => setApiKeyValidatedOnSave(false)}
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
