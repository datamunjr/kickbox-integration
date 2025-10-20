import React, { useState } from 'react';

const FlaggedEmailsModal = ({ isOpen, onClose, emailData, onSubmit }) => {
    const [decision, setDecision] = useState('');
    const [notes, setNotes] = useState(emailData?.admin_notes || '');

    if (!isOpen || !emailData) {
        return null;
    }

    const kickboxResult = emailData.kickbox_result || {};
    const isEditMode = emailData.admin_decision !== 'pending';

    const handleSubmit = (e) => {
        e.preventDefault();
        if (decision) {
            onSubmit({
                item_id: emailData.id,
                decision: decision,
                notes: notes,
                action: isEditMode ? 'modal_edit' : 'modal_decision'
            });
        }
    };

    const handleAllow = () => {
        setDecision('allow');
    };

    const handleBlock = () => {
        setDecision('block');
    };

    return (
        <div className="kickbox_integration-modal-overlay">
            <div className="kickbox_integration-modal">
                <div className="kickbox_integration-modal-header">
                    <h3>
                        {isEditMode
                            ? `Edit Decision for: ${emailData.email}`
                            : `Review Flagged Email: ${emailData.email}`
                        }
                    </h3>
                    <button
                        className="kickbox_integration-modal-close"
                        onClick={onClose}
                    >
                        ×
                    </button>
                </div>

                <div className="kickbox_integration-modal-content">
                    <div className="kickbox_integration-review-details">
                        <h4>Kickbox Verification Details</h4>
                        <div className="kickbox_integration-kickbox-details">
                            <p><strong>Result:</strong> {kickboxResult.result || 'N/A'}</p>
                            <p><strong>Reason:</strong> {kickboxResult.reason || 'N/A'}</p>
                            <p><strong>Sendex Score:</strong> {kickboxResult.sendex || 'N/A'}</p>
                            <p><strong>Role:</strong> {kickboxResult.role ? 'Yes' : 'No'}</p>
                            <p><strong>Free:</strong> {kickboxResult.free ? 'Yes' : 'No'}</p>
                            <p><strong>Disposable:</strong> {kickboxResult.disposable ? 'Yes' : 'No'}</p>
                            <p><strong>Accept All:</strong> {kickboxResult.accept_all ? 'Yes' : 'No'}</p>
                            {kickboxResult.did_you_mean && (
                                <p><strong>Did you mean:</strong> {kickboxResult.did_you_mean}</p>
                            )}
                        </div>
                    </div>

                    <div className="kickbox_integration-decision-section">
                        <h4>{isEditMode ? 'Edit Decision' : 'Admin Decision'}</h4>
                        
                        <form onSubmit={handleSubmit}>
                            <div className="kickbox_integration-decision-options">
                                <button
                                    type="button"
                                    className={`button kickbox-allow-btn ${decision === 'allow' ? 'button-primary' : ''}`}
                                    onClick={handleAllow}
                                >
                                    <span className="kickbox-icon kickbox-icon-check">✓</span>
                                    Allow Email
                                </button>
                                <button
                                    type="button"
                                    className={`button kickbox-block-btn ${decision === 'block' ? 'button-primary' : ''}`}
                                    onClick={handleBlock}
                                >
                                    <span className="kickbox-icon kickbox-icon-x">✕</span>
                                    Block Email
                                </button>
                            </div>

                            <div className="kickbox_integration-notes-section">
                                <label htmlFor="decision-notes">Admin Notes (Optional):</label>
                                <textarea
                                    id="decision-notes"
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    rows="3"
                                    placeholder="Add any notes about this decision..."
                                />
                            </div>

                            <div className="kickbox_integration-submit-section">
                                <button
                                    type="submit"
                                    className="button button-primary"
                                    disabled={!decision}
                                >
                                    Submit Decision
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default FlaggedEmailsModal;
