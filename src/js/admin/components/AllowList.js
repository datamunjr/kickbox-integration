import React, {useState, useEffect} from 'react';
import ReactDOM from 'react-dom';

// Notice component for displaying messages after wp-header-end
const AllowListNotice = ({message, type, onDismiss}) => {
    return (
        <div className={`notice notice-${type} is-dismissible kickbox-allowlist-notice`}>
            <p dangerouslySetInnerHTML={{__html: message}} />
            <button 
                type="button" 
                className="notice-dismiss"
                onClick={onDismiss}
            >
                <span className="screen-reader-text">Dismiss this notice.</span>
            </button>
        </div>
    );
};

const AllowList = ({settings}) => {
    const [allowList, setAllowList] = useState([]);
    const [newEmail, setNewEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    useEffect(() => {
        // Load allow list on mount and when settings change
        loadAllowList();
    }, []);

    // Display notices after wp-header-end
    useEffect(() => {
        const wpHeaderEnd = document.querySelector('.wp-header-end');
        if (!wpHeaderEnd) return;

        // Create container for the notice if there's a message
        if (error || success) {
            const noticeContainer = document.createElement('div');
            wpHeaderEnd.parentNode.insertBefore(noticeContainer, wpHeaderEnd.nextSibling);

            const handleDismiss = () => {
                if (error) setError('');
                if (success) setSuccess('');
            };

            // Render React component into the container
            ReactDOM.render(
                <AllowListNotice 
                    message={error || success}
                    type={error ? 'error' : 'success'}
                    onDismiss={handleDismiss}
                />,
                noticeContainer
            );

            // Cleanup function
            return () => {
                ReactDOM.unmountComponentAtNode(noticeContainer);
                noticeContainer.remove();
            };
        }
    }, [error, success]);

    const addEmail = async () => {
        if (!newEmail.trim()) {
            setError('Please enter an email address.');
            return;
        }

        if (!isValidEmail(newEmail)) {
            setError('Please enter a valid email address.');
            return;
        }

        const emailToAdd = newEmail.trim();
        setLoading(true);
        setError('');
        setSuccess('');

        try {
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_add_to_allow_list',
                    nonce: kickbox_integration_admin.nonce,
                    email: emailToAdd
                })
            });

            const data = await response.json();

            if (data.success) {
                setSuccess(`<strong>${emailToAdd}</strong> successfully added to allow list.`);
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
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_remove_from_allow_list',
                    nonce: kickbox_integration_admin.nonce,
                    email: email
                })
            });

            const data = await response.json();

            if (data.success) {
                setSuccess(`<strong>${email}</strong> successfully removed from allow list.`);
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
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_get_allow_list',
                    nonce: kickbox_integration_admin.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                setAllowList(data.data);
            }
        } catch (error) {
            // Silently fail - allow list will show as empty
            setAllowList([]);
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
        <div className="kickbox_integration-allow-list">
            <div className="kickbox_integration-allow-list-header">
                <h3>Email Allow List</h3>
                <p className="description">
                    Emails in this list will skip Kickbox verification during checkout.
                    This is useful for trusted customers, test accounts, or special cases.
                </p>
            </div>

            <div className="kickbox_integration-add-email-section">
                <h4>Add Email to Allow List</h4>
                <div className="kickbox_integration-add-email-form">
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

            <div className="kickbox_integration-allow-list-section">
                <h4>Current Allow List ({allowList.length} emails)</h4>

                {allowList.length === 0 ? (
                    <div className="kickbox_integration-empty-allow-list">
                        <p>No emails in the allow list. Add emails above to skip verification for specific
                            addresses.</p>
                    </div>
                ) : (
                    <div className="kickbox_integration-allow-list-items">
                        {allowList.map((email, index) => (
                            <div key={index} className="kickbox_integration-allow-list-item">
                                <span className="kickbox_integration-email-address">{email}</span>
                                <button
                                    type="button"
                                    onClick={() => removeEmail(email)}
                                    disabled={loading}
                                    className="button button-secondary"
                                    title="Remove from allow list"
                                >
                                    Remove
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};

export default AllowList;
