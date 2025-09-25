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
    const [initialLoading, setInitialLoading] = useState(true);
    const [message, setMessage] = useState({type: '', text: '', details: null, hasDetails: false});
    const [showErrorDetails, setShowErrorDetails] = useState(false);
    const [apiKeyValidatedOnSave, setApiKeyValidatedOnSave] = useState(false);
    const [pendingCount, setPendingCount] = useState(0);

    useEffect(() => {
        // Load current settings
        loadSettings();
        // Load pending count
        loadPendingCount();

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
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_get_settings',
                    nonce: kickbox_integration_admin.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                setSettings(data.data);
            }
        } catch (error) {
            console.error('Error loading settings:', error);
        } finally {
            setInitialLoading(false);
        }
    };

    const loadPendingCount = async () => {
        try {
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_get_pending_count',
                    nonce: kickbox_integration_admin.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                setPendingCount(data.data.pending_count);
            }
        } catch (error) {
            console.error('Error loading pending count:', error);
        }
    };

    const saveSettings = async () => {
        setLoading(true);
        setMessage({type: '', text: ''});

        try {
            console.log(settings);
            const body = {
                action: 'kickbox_integration_save_settings',
                nonce: kickbox_integration_admin.nonce,
                apiKey: settings.apiKey,
                deliverableAction: settings.deliverableAction,
                undeliverableAction: settings.undeliverableAction,
                riskyAction: settings.riskyAction,
                unknownAction: settings.unknownAction,
                enableCheckoutVerification: settings.enableCheckoutVerification,
                enableRegistrationVerification: settings.enableRegistrationVerification,
                enableCustomerVerification: settings.enableCustomerVerification,
                skipValidation: Boolean(!settings?.hasApiKeyChanged).toString()
            }
            console.log(body);
            const response = await fetch(kickbox_integration_admin.ajax_url, {
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
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_test_api',
                    nonce: kickbox_integration_admin.nonce,
                    api_key: settings.apiKey
                })
            });

            const data = await response.json();
            if (data.success) {
                setMessage({
                    type: 'success',
                    text: kickbox_integration_admin.strings.api_success,
                    details: null,
                    hasDetails: false
                });
                // Call the success callback if provided
                if (onSuccess) {
                    onSuccess();
                }
            } else {
                setMessage({
                    type: 'error',
                    text: data.data.message || kickbox_integration_admin.strings.api_error,
                    details: data.data.details || null,
                    hasDetails: data.data.has_details || false
                });
            }
        } catch (error) {
            setMessage({
                type: 'error',
                text: kickbox_integration_admin.strings.api_error,
                details: null,
                hasDetails: false
            });
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
            <div className="kickbox_integration-error-details" style={{
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

    const refreshPendingCount = () => {
        loadPendingCount();
    };

    const tabs = [
        {id: 'api', label: 'API Settings', component: ApiSettings},
        {id: 'actions', label: 'Verification Actions', component: VerificationActions},
        {id: 'allowlist', label: 'Allow List', component: AllowList},
        {id: 'flagged', label: 'Flagged Emails', component: FlaggedEmails},
        {id: 'stats', label: 'Statistics', component: VerificationStats}
    ];

    const ActiveComponent = tabs.find(tab => tab.id === activeTab)?.component;

    const kickboxIntegrationAdminHeadertitle = () => {
        return <div className="kickbox_integration-header-title">
            <h2 style={{display: 'flex', alignItems: 'center', gap: '12px'}}>
                Configure your Kickbox Integration Settings here!
            </h2>
            <p className="description">
                For more information on how to get started with kickbox, visit <a
                target="_blank"
                href="https://docs.kickbox.com/docs/getting-started">https://docs.kickbox.com/docs/getting-started</a>.
            </p>
        </div>
    }

    // Show loading state while initial data is being fetched
    if (initialLoading) {
        return (
            <div className="kickbox_integration-admin-container">
                {kickboxIntegrationAdminHeadertitle()}
                <div className="kickbox_integration-loading" style={{
                    textAlign: 'center',
                    padding: '40px 20px',
                    fontSize: '16px',
                    color: '#666'
                }}>
                    <div style={{
                        display: 'inline-block',
                        width: '20px',
                        height: '20px',
                        border: '2px solid #f3f3f3',
                        borderTop: '2px solid #0073aa',
                        borderRadius: '50%',
                        animation: 'spin 1s linear infinite',
                        marginRight: '10px'
                    }}></div>
                    Loading settings...
                </div>
                <style>{`
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `}</style>
            </div>
        );
    }

    return (
        <div className="kickbox_integration-admin-container">
            <div className="kickbox_integration-header">
                {kickboxIntegrationAdminHeadertitle()}
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

            <div className="kickbox_integration-tabs">
                {tabs.map(tab => (
                    <button
                        key={tab.id}
                        className={`kickbox_integration-tab ${activeTab === tab.id ? 'active' : ''}`}
                        onClick={() => handleTabChange(tab.id)}
                    >
                        {tab.label}
                        {tab.id === 'flagged' && pendingCount > 0 && (
                            <span className="kickbox_integration-pending-badge">
                                {pendingCount}
                            </span>
                        )}
                    </button>
                ))}
            </div>

            <div className="kickbox_integration-tab-content">
                {ActiveComponent && (
                    <ActiveComponent
                        settings={settings}
                        onSettingChange={handleSettingChange}
                        loading={loading}
                        onTestApi={testApiConnection}
                        apiKeyValidatedOnSave={apiKeyValidatedOnSave}
                        onApiKeyValidatedOnSave={() => setApiKeyValidatedOnSave(false)}
                        onRefreshPendingCount={refreshPendingCount}
                    />
                )}
            </div>

            <div className="kickbox_integration-actions">
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
