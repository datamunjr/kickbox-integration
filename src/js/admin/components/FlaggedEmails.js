import React, {useState, useEffect} from 'react';
import LoadingSpinner from './LoadingSpinner';

// Custom debounce hook
const useDebounce = (value, delay) => {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    }, [value, delay]);

    return debouncedValue;
};

const FlaggedEmails = ({onRefreshPendingCount}) => {
    const [flaggedEmails, setFlaggedEmails] = useState([]);
    const [loading, setLoading] = useState(false);
    const [initialLoading, setInitialLoading] = useState(true);
    const [message, setMessage] = useState({type: '', text: ''});
    const [pagination, setPagination] = useState({
        current_page: 1,
        total_pages: 1,
        total_items: 0,
        per_page: 20
    });
    const [filters, setFilters] = useState({
        search: '',
        decision: '',
        origin: '',
        verification_action: '',
        orderby: 'flagged_date',
        order: 'DESC'
    });
    const [selectedEmail, setSelectedEmail] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [decisionNotes, setDecisionNotes] = useState('');

    // Debounce search input with 500ms delay
    const debouncedSearch = useDebounce(filters.search, 500);

    // Effect for non-search filters and pagination
    useEffect(() => {
        fetchFlaggedEmails();
    }, [pagination.current_page, filters.decision, filters.origin, filters.verification_action, filters.orderby, filters.order]);

    // Separate effect for debounced search
    useEffect(() => {
        fetchFlaggedEmails();
    }, [debouncedSearch]);

    const fetchFlaggedEmails = async () => {
        setLoading(true);
        try {
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'kickbox_integration_get_flagged_emails',
                    nonce: kickbox_integration_admin.nonce,
                    page: pagination.current_page,
                    per_page: pagination.per_page,
                    search: debouncedSearch,
                    decision: filters.decision,
                    origin: filters.origin,
                    verification_action: filters.verification_action,
                    orderby: filters.orderby,
                    order: filters.order
                }),
            });

            const data = await response.json();
            if (data.success) {
                setFlaggedEmails(data.data.items);
                setPagination(prev => ({
                    ...prev,
                    total_pages: data.data.total_pages,
                    total_items: data.data.total_items
                }));
            } else {
                setMessage({type: 'error', text: data.data.message || 'Failed to load flagged emails.'});
            }
        } catch (error) {
            setMessage({type: 'error', text: 'Error loading flagged emails.'});
        } finally {
            setLoading(false);
            setInitialLoading(false);
        }
    };

    const handleFilterChange = (key, value) => {
        setFilters(prev => ({...prev, [key]: value}));
        // Reset to first page when any filter changes
        setPagination(prev => ({...prev, current_page: 1}));
    };

    const handlePageChange = (page) => {
        setPagination(prev => ({...prev, current_page: page}));
    };

    const handleDecisionUpdate = async (id, decision) => {
        try {
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'kickbox_integration_update_flagged_decision',
                    nonce: kickbox_integration_admin.nonce,
                    id: id,
                    decision: decision,
                    notes: decisionNotes
                }),
            });

            const data = await response.json();
            if (data.success) {
                setMessage({type: 'success', text: data.data.message});
                setShowModal(false);
                setSelectedEmail(null);
                setDecisionNotes('');
                fetchFlaggedEmails();
                // Refresh pending count badge
                if (onRefreshPendingCount) {
                    onRefreshPendingCount();
                }
            } else {
                setMessage({type: 'error', text: data.data.message || 'Failed to update decision.'});
            }
        } catch (error) {
            setMessage({type: 'error', text: 'Error updating decision.'});
        }
    };

    const handleEditDecision = async (id, decision) => {
        try {
            const response = await fetch(kickbox_integration_admin.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'kickbox_integration_edit_flagged_decision',
                    nonce: kickbox_integration_admin.nonce,
                    id: id,
                    decision: decision,
                    notes: decisionNotes
                }),
            });

            const data = await response.json();
            if (data.success) {
                setMessage({type: 'success', text: data.data.message});
                setShowModal(false);
                setSelectedEmail(null);
                setDecisionNotes('');
                fetchFlaggedEmails();
                // Refresh pending count badge
                if (onRefreshPendingCount) {
                    onRefreshPendingCount();
                }
            } else {
                setMessage({type: 'error', text: data.data.message || 'Failed to update decision.'});
            }
        } catch (error) {
            setMessage({type: 'error', text: 'Error updating decision.'});
        }
    };

    const openDecisionModal = (email) => {
        setSelectedEmail(email);
        setDecisionNotes(email.admin_notes || '');
        setShowModal(true);
    };

    const getResultBadgeClass = (result) => {
        switch (result) {
            case 'undeliverable':
                return 'kickbox_integration-badge-undeliverable';
            case 'risky':
                return 'kickbox_integration-badge-risky';
            case 'unknown':
                return 'kickbox_integration-badge-unknown';
            default:
                return 'kickbox_integration-badge-default';
        }
    };

    const getDecisionBadgeClass = (decision) => {
        switch (decision) {
            case 'pending':
                return 'kickbox_integration-badge-pending';
            case 'allow':
                return 'kickbox_integration-badge-allow';
            case 'block':
                return 'kickbox_integration-badge-block';
            default:
                return 'kickbox_integration-badge-default';
        }
    };

    const getVerificationActionTerms = (action) => {
        switch (action) {
            case 'block':
                return 'blocked';
            case 'review':
                return 'flagged';
            default:
                return 'unknown'
        }
    }

    const getVerificationActionBadgeClass = (action) => {
        switch (action) {
            case 'block':
                return 'kickbox_integration-badge-block';
            case 'review':
                return 'kickbox_integration-badge-review';
            default:
                return 'kickbox_integration-badge-default';
        }
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleString();
    };

    const renderPagination = () => {
        const pages = [];
        const maxPages = Math.min(5, pagination.total_pages);
        const startPage = Math.max(1, pagination.current_page - Math.floor(maxPages / 2));

        for (let i = startPage; i < startPage + maxPages && i <= pagination.total_pages; i++) {
            pages.push(
                <button
                    key={i}
                    className={`button ${i === pagination.current_page ? 'button-primary' : ''}`}
                    onClick={() => handlePageChange(i)}
                    disabled={loading}
                >
                    {i}
                </button>
            );
        }

        return (
            <div className="kickbox_integration-pagination">
                <button
                    className="button"
                    onClick={() => handlePageChange(pagination.current_page - 1)}
                    disabled={pagination.current_page <= 1 || loading}
                >
                    Previous
                </button>
                {pages}
                <button
                    className="button"
                    onClick={() => handlePageChange(pagination.current_page + 1)}
                    disabled={pagination.current_page >= pagination.total_pages || loading}
                >
                    Next
                </button>
                <span className="kickbox_integration-pagination-info">
          Page {pagination.current_page} of {pagination.total_pages}
                    ({pagination.total_items} total items)
        </span>
            </div>
        );
    };

    if (initialLoading) {
        return <LoadingSpinner message="Loading flagged emails..."/>;
    }

    return (
        <div className="kickbox_integration-flagged-emails">
            {message.text && (
                <div className={`notice notice-${message.type === 'error' ? 'error' : 'success'} is-dismissible`}>
                    <p>{message.text}</p>
                </div>
            )}

            <div className="kickbox_integration-flagged-emails-filters">
                <div className="kickbox_integration-filter-row">
                    <div className="kickbox_integration-filter-group">
                        <label htmlFor="search-filter">Search Email:</label>
                        <input
                            id="search-filter"
                            type="text"
                            value={filters.search}
                            onChange={(e) => handleFilterChange('search', e.target.value)}
                            placeholder="Enter email address..."
                        />
                    </div>

                    <div className="kickbox_integration-filter-group">
                        <label htmlFor="verification-action-filter">Action at time of Verification:</label>
                        <select
                            id="verification-action-filter"
                            value={filters.verification_action}
                            onChange={(e) => handleFilterChange('verification_action', e.target.value)}
                        >
                            <option value="">All Actions</option>
                            <option value="block">Blocked</option>
                            <option value="review">Flagged</option>
                        </select>
                    </div>

                    <div className="kickbox_integration-filter-group">
                        <label htmlFor="decision-filter">Decision:</label>
                        <select
                            id="decision-filter"
                            value={filters.decision}
                            onChange={(e) => handleFilterChange('decision', e.target.value)}
                        >
                            <option value="">All Decisions</option>
                            <option value="pending">Pending</option>
                            <option value="allow">Allow</option>
                            <option value="block">Block</option>
                        </select>
                    </div>

                    <div className="kickbox_integration-filter-group">
                        <label htmlFor="origin-filter">Origin:</label>
                        <select
                            id="origin-filter"
                            value={filters.origin}
                            onChange={(e) => handleFilterChange('origin', e.target.value)}
                        >
                            <option value="">All Origins</option>
                            <option value="checkout">Checkout</option>
                            <option value="registration">Registration</option>
                        </select>
                    </div>

                    <div className="kickbox_integration-filter-group">
                        <label htmlFor="orderby-filter">Sort By:</label>
                        <select
                            id="orderby-filter"
                            value={filters.orderby}
                            onChange={(e) => handleFilterChange('orderby', e.target.value)}
                        >
                            <option value="flagged_date">Flagged Date</option>
                            <option value="email">Email</option>
                            <option value="admin_decision">Decision</option>
                        </select>
                    </div>
                </div>
            </div>

            {loading ? (
                <div className="kickbox_integration-loading">
                    <p>Loading flagged emails...</p>
                </div>
            ) : flaggedEmails.length === 0 ? (
                <div className="kickbox_integration-empty-state">
                    <p>No flagged emails found.</p>
                </div>
            ) : (
                <>
                    <div className="kickbox_integration-flagged-emails-table">
                        <table className="wp-list-table widefat fixed striped">
                            <thead>
                            <tr>
                                <th>Email</th>
                                <th>Kickbox Result</th>
                                <th>Action at time of Verification</th>
                                <th>Decision</th>
                                <th>Origin</th>
                                <th>Order ID</th>
                                <th>Flagged Date</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            {flaggedEmails.map((email) => (
                                <tr key={email.id}>
                                    <td>
                                        <strong>{email.email}</strong>
                                        {email.user_id && (
                                            <div className="kickbox_integration-user-info">
                                                User ID: {email.user_id}
                                            </div>
                                        )}
                                    </td>
                                    <td>
                      <span
                          className={`kickbox_integration-badge ${getResultBadgeClass(email.kickbox_result?.result)}`}>
                        {email.kickbox_result?.result || 'Unknown'}
                      </span>
                                        {email.kickbox_result?.reason && (
                                            <div className="kickbox_integration-reason">
                                                {email.kickbox_result.reason}
                                            </div>
                                        )}
                                    </td>
                                    <td>
                      <span
                          className={`kickbox_integration-badge ${getVerificationActionBadgeClass(email.verification_action)}`}>
                        {getVerificationActionTerms(email.verification_action)}
                      </span>
                                    </td>
                                    <td>
                      <span className={`kickbox_integration-badge ${getDecisionBadgeClass(email.admin_decision)}`}>
                        {email.admin_decision}
                      </span>
                                    </td>
                                    <td>{email.origin}</td>
                                    <td>
                                        {email.order_id ? (
                                            <a href={`/wp-admin/post.php?post=${email.order_id}&action=edit`}
                                               target="_blank">
                                                #{email.order_id}
                                            </a>
                                        ) : '-'}
                                    </td>
                                    <td>{formatDate(email.flagged_date)}</td>
                                    <td>
                                        {email.admin_decision === 'pending' ? (
                                            <button
                                                className="button button-primary"
                                                onClick={() => openDecisionModal(email)}
                                            >
                                                Review
                                            </button>
                                        ) : (
                                            <div className="kickbox_integration-reviewed-info">
                                                <div>Reviewed: {formatDate(email.reviewed_date)}</div>
                                                {email.admin_notes && (
                                                    <div className="kickbox_integration-notes">
                                                        <strong>Notes:</strong> {email.admin_notes}
                                                    </div>
                                                )}
                                                <button
                                                    className="button button-secondary kickbox_integration-edit-button"
                                                    onClick={() => openDecisionModal(email)}
                                                    style={{marginTop: '5px'}}
                                                >
                                                    Edit Decision
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            </tbody>
                        </table>
                    </div>

                    {pagination.total_pages > 1 && renderPagination()}
                </>
            )}

            {showModal && selectedEmail && (
                <div className="kickbox_integration-modal-overlay">
                    <div className="kickbox_integration-modal">
                        <div className="kickbox_integration-modal-header">
                            <h3>
                                {selectedEmail.admin_decision === 'pending'
                                    ? `Review Flagged Email: ${selectedEmail.email}`
                                    : `Edit Decision for: ${selectedEmail.email}`
                                }
                            </h3>
                            <button
                                className="kickbox_integration-modal-close"
                                onClick={() => setShowModal(false)}
                            >
                                ×
                            </button>
                        </div>

                        <div className="kickbox_integration-modal-content">
                            <div className="kickbox_integration-review-details">
                                <h4>Kickbox Verification Details</h4>
                                <div className="kickbox_integration-kickbox-details">
                                    <p><strong>Result:</strong> {selectedEmail.kickbox_result?.result}</p>
                                    <p><strong>Reason:</strong> {selectedEmail.kickbox_result?.reason || 'N/A'}</p>
                                    <p><strong>Sendex Score:</strong> {selectedEmail.kickbox_result?.sendex || 'N/A'}
                                    </p>
                                    <p><strong>Role:</strong> {selectedEmail.kickbox_result?.role ? 'Yes' : 'No'}</p>
                                    <p><strong>Free:</strong> {selectedEmail.kickbox_result?.free ? 'Yes' : 'No'}</p>
                                    <p>
                                        <strong>Disposable:</strong> {selectedEmail.kickbox_result?.disposable ? 'Yes' : 'No'}
                                    </p>
                                    <p><strong>Accept
                                        All:</strong> {selectedEmail.kickbox_result?.accept_all ? 'Yes' : 'No'}</p>
                                    {selectedEmail.kickbox_result?.did_you_mean && (
                                        <p><strong>Did you mean:</strong> {selectedEmail.kickbox_result.did_you_mean}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="kickbox_integration-decision-section">
                                <h4>{selectedEmail.admin_decision === 'pending' ? 'Admin Decision' : 'Edit Decision'}</h4>
                                <div className="kickbox_integration-decision-options">
                                    <button
                                        className="button button-primary kickbox_integration-allow-button"
                                        onClick={() => selectedEmail.admin_decision === 'pending'
                                            ? handleDecisionUpdate(selectedEmail.id, 'allow')
                                            : handleEditDecision(selectedEmail.id, 'allow')
                                        }
                                    >
                                        Allow Email
                                    </button>
                                    <button
                                        className="button kickbox_integration-block-button"
                                        onClick={() => selectedEmail.admin_decision === 'pending'
                                            ? handleDecisionUpdate(selectedEmail.id, 'block')
                                            : handleEditDecision(selectedEmail.id, 'block')
                                        }
                                    >
                                        Block Email
                                    </button>
                                </div>

                                <div className="kickbox_integration-notes-section">
                                    <label htmlFor="decision-notes">Admin Notes (Optional):</label>
                                    <textarea
                                        id="decision-notes"
                                        value={decisionNotes}
                                        onChange={(e) => setDecisionNotes(e.target.value)}
                                        rows="3"
                                        placeholder="Add any notes about this decision..."
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default FlaggedEmails;
