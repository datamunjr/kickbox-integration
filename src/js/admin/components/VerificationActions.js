import React from 'react';

const VerificationActions = ({ settings, onSettingChange }) => {
  const actionOptions = [
    { value: 'allow', label: 'Allow checkout' },
    { value: 'block', label: 'Block checkout' },
    { value: 'review', label: 'Allow but flag for review' }
  ];

  const verificationTypes = [
    {
      key: 'deliverableAction',
      label: 'Deliverable Emails',
      description: 'Emails that are confirmed to be deliverable'
    },
    {
      key: 'undeliverableAction',
      label: 'Undeliverable Emails',
      description: 'Emails that are confirmed to be undeliverable'
    },
    {
      key: 'riskyAction',
      label: 'Risky Emails',
      description: 'Emails that may be risky or suspicious'
    },
    {
      key: 'unknownAction',
      label: 'Unknown Emails',
      description: 'Emails that could not be verified'
    }
  ];

  return (
    <div className="wckb-verification-actions">
      <p>Configure what action to take for each verification result:</p>
      
      <table className="form-table">
        <tbody>
          {verificationTypes.map(type => (
            <tr key={type.key}>
              <th scope="row">
                <label htmlFor={`wckb_${type.key}`}>{type.label}</label>
              </th>
              <td>
                <select
                  id={`wckb_${type.key}`}
                  value={settings[type.key]}
                  onChange={(e) => onSettingChange(type.key, e.target.value)}
                >
                  {actionOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                <p className="description">{type.description}</p>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      
      <div className="wckb-action-info">
        <h4>Action Explanations:</h4>
        <ul>
          <li><strong>Allow checkout:</strong> The customer can complete their purchase regardless of verification result.</li>
          <li><strong>Block checkout:</strong> The customer will be prevented from completing their purchase.</li>
          <li><strong>Allow but flag for review:</strong> The customer can complete their purchase, but the order will be flagged for admin review.</li>
        </ul>
      </div>
    </div>
  );
};

export default VerificationActions;
