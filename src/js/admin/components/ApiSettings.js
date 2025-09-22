import React, {useState, useEffect} from 'react';

const ApiSettings = ({
                         settings,
                         onSettingChange,
                         loading,
                         onTestApi,
                         apiKeyValidatedOnSave,
                         onApiKeyValidatedOnSave
                     }) => {
    const [showApiKey, setShowApiKey] = useState(false);
    const [isLoadingFullKey, setIsLoadingFullKey] = useState(false);
    const [isApiKeyValidated, setIsApiKeyValidated] = useState(false);

    // Handle API key validation on save
    useEffect(() => {
        if (apiKeyValidatedOnSave && settings.apiKey) {
            setIsApiKeyValidated(true);
            onApiKeyValidatedOnSave(); // Reset the flag
        }
    }, [apiKeyValidatedOnSave, settings.apiKey, onApiKeyValidatedOnSave]);

    // Auto-validate API key on page load if it exists
    useEffect(() => {
        if (settings.apiKey && !isApiKeyValidated) {
            // If we have any API key and haven't validated yet, assume it's validated
            // because it came from the database, meaning it was previously validated and saved
            setIsApiKeyValidated(true);
        }
    }, [settings.apiKey, isApiKeyValidated]);


    // Function to handle API key changes
    const handleApiKeyChange = (newValue) => {
        onSettingChange('apiKey', newValue);
        if (!settings.hasApiKeyChanged) {
            console.log("API key changed");
            settings.hasApiKeyChanged = true;
        }
    };

    // Function to handle successful API test
    const handleApiTestSuccess = () => {
        setIsApiKeyValidated(true);
    };

    // Function to fetch full API key
    const fetchFullApiKey = async () => {
        setIsLoadingFullKey(true);
        try {
            const response = await fetch(wckb_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wckb_get_full_api_key',
                    nonce: wckb_admin.nonce,
                }),
            });

            const data = await response.json();
            if (data.success) {
                // Directly update the settings without going through handleApiKeyChange
                // to avoid triggering validation state changes
                onSettingChange('apiKey', data.data.apiKey);
                setShowApiKey(true);
            } else {
                console.error('Failed to fetch full API key:', data.data.message);
            }
        } catch (error) {
            console.error('Error fetching full API key:', error);
        } finally {
            setIsLoadingFullKey(false);
        }
    };

    // Function to mask API key (same logic as backend)
    const maskApiKey = (apiKey) => {
        if (!apiKey) return '';

        // Extract prefix (test_ or live_)
        const prefix = apiKey.startsWith('test_') ? 'test_' :
            apiKey.startsWith('live_') ? 'live_' : '';

        if (prefix) {
            const remaining = apiKey.substring(prefix.length);
            if (remaining.length <= 4) {
                return prefix + remaining;
            }
            const lastFour = remaining.slice(-4);
            const masked = '*'.repeat(remaining.length - 4);
            return prefix + masked + lastFour;
        }

        // If no prefix, just show last 4 characters
        if (apiKey.length <= 4) return apiKey;
        const lastFour = apiKey.slice(-4);
        const masked = '*'.repeat(apiKey.length - 4);
        return masked + lastFour;
    };

    // Handle eye icon click
    const handleToggleVisibility = () => {
        if (settings.apiKey.includes('*')) {
            // API key is masked, fetch full key
            fetchFullApiKey();
        } else {
            // API key is not masked, mask it
            const maskedKey = maskApiKey(settings.apiKey);
            onSettingChange('apiKey', maskedKey);
            setShowApiKey(false);
        }
    };

    return (
        <div className="wckb-api-settings">
            <table className="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <label htmlFor="wckb_api_key">API Key</label>
                    </th>
                    <td>
                        <div className="wckb-api-key-container">
                            <input
                                type="text"
                                id="wckb_api_key"
                                className="regular-text"
                                value={settings.apiKey}
                                onChange={(e) => handleApiKeyChange(e.target.value)}
                                placeholder="Enter your Kickbox API key"
                                // disabled={settings.apiKey.includes("*")}
                            />
                            {settings.apiKey && (
                                <button
                                    type="button"
                                    className="wckb-toggle-visibility"
                                    onClick={handleToggleVisibility}
                                    disabled={isLoadingFullKey}
                                    title={
                                        isLoadingFullKey
                                            ? "Loading..."
                                            : settings.apiKey.includes('*')
                                                ? "Show full API key"
                                                : showApiKey
                                                    ? "Hide API key"
                                                    : "Show API key"
                                    }
                                >
                                    {isLoadingFullKey ? (
                                        <span className="dashicons dashicons-update"
                                              style={{animation: 'spin 1s linear infinite'}}></span>
                                    ) : settings.apiKey.includes('*') ? (
                                        <span className="dashicons dashicons-visibility"></span>
                                    ) : showApiKey ? (
                                        <span className="dashicons dashicons-hidden"></span>
                                    ) : (
                                        <span className="dashicons dashicons-visibility"></span>
                                    )}
                                </button>
                            )}
                        </div>
                        <p className="description">
                            Enter your Kickbox API key. You can find this in your Kickbox dashboard.
                        </p>
                        <button
                            type="button"
                            className="button button-secondary"
                            onClick={() => {
                                onTestApi(handleApiTestSuccess);
                            }}
                            disabled={loading || !settings.apiKey || settings.apiKey.includes("*")}
                        >
                            {loading ? 'Testing...' : 'Test API Connection'}
                        </button>
                        {settings.apiKey && isApiKeyValidated && (
                            <div className="wckb-api-mode-notice">
                                {settings.apiKey.startsWith('test_') ? (
                                    <div className="notice notice-info inline">
                                        <p><strong>Sandbox Mode:</strong> Using test API key. No credits will be
                                            consumed.</p>
                                    </div>
                                ) : settings.apiKey.startsWith('live_') ? (
                                    <div className="notice notice-warning inline">
                                        <p><strong>Live Mode:</strong> Using production API key. Credits will be
                                            consumed.</p>
                                    </div>
                                ) : (
                                    <div className="notice notice-error inline">
                                        <p><strong>Invalid API Key:</strong> API key should start with "test_" or
                                            "live_".</p>
                                    </div>
                                )}
                            </div>
                        )}
                    </td>
                </tr>

                {/* Balance Information */}
                {settings.apiKey && isApiKeyValidated && settings.hasBalanceBeenDetermined && (
                    <tr>
                        <th scope="row">
                            <label>Account Balance</label>
                        </th>
                        <td>
                            <div className={`wckb-balance-info ${settings.isBalanceLow ? 'low-balance' : ''}`}>
                                <p className={`balance-message ${settings.isBalanceLow ? 'low-balance-warning' : ''}`}>
                                    {settings.balanceMessage}
                                </p>
                                {settings.isBalanceLow && (
                                    <div className="balance-warning">
                                        <p>
                                            <strong>⚠️ Low Balance Alert:</strong> Your verification balance is running low. 
                                            Please add more credits to continue email verification.
                                        </p>
                                        <p>
                                            <a 
                                                href="https://kickbox.com" 
                                                target="_blank" 
                                                rel="noopener noreferrer"
                                                className="button button-primary"
                                            >
                                                Add Credits to Kickbox Account
                                            </a>
                                        </p>
                                    </div>
                                )}
                            </div>
                        </td>
                    </tr>
                )}

                <tr>
                    <th scope="row">
                        <label htmlFor="wckb_enable_checkout_verification">Checkout Verification</label>
                    </th>
                    <td>
                        <input
                            type="checkbox"
                            id="wckb_enable_checkout_verification"
                            checked={settings.enableCheckoutVerification}
                            onChange={(e) => onSettingChange('enableCheckoutVerification', e.target.checked)}
                        />
                        <label htmlFor="wckb_enable_checkout_verification">
                            Enable email verification during checkout
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label htmlFor="wckb_enable_registration_verification">Registration Verification</label>
                    </th>
                    <td>
                        <input
                            type="checkbox"
                            id="wckb_enable_registration_verification"
                            checked={settings.enableRegistrationVerification}
                            onChange={(e) => onSettingChange('enableRegistrationVerification', e.target.checked)}
                        />
                        <label htmlFor="wckb_enable_registration_verification">
                            Enable email verification during user registration
                        </label>
                    </td>
                </tr>

                </tbody>
            </table>
        </div>
    );
};

export default ApiSettings;
