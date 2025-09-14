import React from 'react';

const ApiSettings = ({ settings, onSettingChange, loading, onTestApi }) => {
  return (
    <div className="wckb-api-settings">
      <table className="form-table">
        <tbody>
          <tr>
            <th scope="row">
              <label htmlFor="wckb_api_key">API Key</label>
            </th>
            <td>
              <input
                type="password"
                id="wckb_api_key"
                className="regular-text"
                value={settings.apiKey}
                onChange={(e) => onSettingChange('apiKey', e.target.value)}
                placeholder="Enter your Kickbox API key"
              />
              <p className="description">
                Enter your Kickbox API key. You can find this in your Kickbox dashboard.
              </p>
              <button
                type="button"
                className="button button-secondary"
                onClick={onTestApi}
                disabled={loading || !settings.apiKey}
              >
                {loading ? 'Testing...' : 'Test API Connection'}
              </button>
            </td>
          </tr>
          
          <tr>
            <th scope="row">
              <label htmlFor="wckb_sandbox_mode">Sandbox Mode</label>
            </th>
            <td>
              <input
                type="checkbox"
                id="wckb_sandbox_mode"
                checked={settings.sandboxMode}
                onChange={(e) => onSettingChange('sandboxMode', e.target.checked)}
              />
              <label htmlFor="wckb_sandbox_mode">
                Enable sandbox mode for testing (recommended)
              </label>
            </td>
          </tr>
          
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
              <label htmlFor="wckb_enable_customer_verification">Customer Verification</label>
            </th>
            <td>
              <input
                type="checkbox"
                id="wckb_enable_customer_verification"
                checked={settings.enableCustomerVerification}
                onChange={(e) => onSettingChange('enableCustomerVerification', e.target.checked)}
              />
              <label htmlFor="wckb_enable_customer_verification">
                Enable batch verification for existing customers
              </label>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  );
};

export default ApiSettings;
