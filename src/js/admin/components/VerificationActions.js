import React, {useEffect} from 'react';

const VerificationActions = ({settings, onSettingChange}) => {
    const actionOptions = [
        {value: 'allow', label: 'Allow'},
        {value: 'block', label: 'Block'},
        {value: 'review', label: 'Allow but flag for review'}
    ];

    const deliverableActionOptions = [
        {value: 'allow', label: 'Allow'},
        {value: 'block', label: 'Block'}
    ];

    const verificationTypes = [
        {
            key: 'deliverableAction',
            label: 'Deliverable',
            description: 'The recipient\'s mail server confirmed the recipient exists. Kickbox has performed additional analysis and determined this address is safe to send to within our 95% Delivery Guarantee.'
        },
        {
            key: 'undeliverableAction',
            label: 'Undeliverable',
            description: 'The email address does not exist or is syntactically incorrect (and thus does not exist).'
        },
        {
            key: 'riskyAction',
            label: 'Risky',
            description: 'The email address has quality issues and may result in a bounce or low engagement. Use caution when sending to risky addresses. Accept All, Disposable, and Role addresses are classified as Risky.'
        },
        {
            key: 'unknownAction',
            label: 'Unknown',
            description: 'Kickbox was unable to get a response from the recipient\'s mail server. This often happens if the destination mail server is too slow or temporarily unavailable. Unknown addresses don\'t count against your verification balance.'
        }
    ];

    // Initialize WooCommerce help tips after component renders
    useEffect(() => {
        const initializeHelpTips = () => {
            if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.tipTip) {
                window.jQuery('.woocommerce-help-tip').tipTip({
                    'attribute': 'data-tip',
                    'fadeIn': 50,
                    'fadeOut': 50,
                    'delay': 200
                });
            }
        };

        // Initialize immediately
        initializeHelpTips();
    }, []);

    return (
        <div className="wckb-verification-actions">
            <p>Configure what action to take for each verification result:</p>
            <p className="description">
                For detailed information about each verification result type, see the{' '}
                <a href="https://docs.kickbox.com/docs/terminology" target="_blank" rel="noopener noreferrer">
                    Kickbox Terminology Documentation
                </a>.
            </p>

            <table className="form-table">
                <tbody>
                {verificationTypes.map(type => (
                    <tr key={type.key}>
                        <th scope="row">
                            <label htmlFor={`wckb_${type.key}`}>
                                {type.label}
                                <span className="woocommerce-help-tip" data-tip={type.description}></span>
                            </label>
                        </th>
                        <td>
                            <select
                                id={`wckb_${type.key}`}
                                value={settings[type.key]}
                                onChange={(e) => onSettingChange(type.key, e.target.value)}
                            >
                                {(type.key === 'deliverableAction' ? deliverableActionOptions : actionOptions).map(option => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                             {type.key === 'deliverableAction' && settings[type.key] === 'block' && (
                                 <div className="wckb-deliverable-block-warning">
                                     <p>
                                         <strong>⚠️ Warning:</strong> Blocking deliverable emails will prevent almost all
                                         users from proceeding
                                         and is counterproductive to your business. Deliverable emails are safe to send
                                         to and should
                                         typically be allowed.
                                     </p>
                                 </div>
                             )}
                        </td>
                    </tr>
                ))}
                </tbody>
            </table>

            <div className="wckb-action-info">
                <h4>Action Explanations:</h4>
                <ul>
                    <li><strong>Allow:</strong> The user can proceed regardless of verification result.</li>
                    <li><strong>Block:</strong> The user will be prevented from proceeding and the email will be automatically flagged for admin review.</li>
                    <li><strong>Allow but flag for review:</strong> The user can proceed, but the action will be flagged for admin review.</li>
                </ul>
            </div>
        </div>
    );
};

export default VerificationActions;
