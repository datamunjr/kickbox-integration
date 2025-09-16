import React, { useState, useEffect } from 'react';

const CustomerVerification = () => {
  const [selectedUsers, setSelectedUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });

  // Don't render if customer verification is not enabled
  if (!wckb_customer || !wckb_customer.verification_enabled) {
    return null;
  }

  useEffect(() => {
    // Listen for bulk action changes
    const bulkActionSelect = document.querySelector('select[name="action"]');
    const bulkActionSelect2 = document.querySelector('select[name="action2"]');
    
    const handleBulkAction = (e) => {
      if (e.target.value === 'wckb_verify_emails') {
        e.preventDefault();
        handleBulkVerification();
      }
    };

    if (bulkActionSelect) {
      bulkActionSelect.addEventListener('change', handleBulkAction);
    }
    if (bulkActionSelect2) {
      bulkActionSelect2.addEventListener('change', handleBulkAction);
    }

    return () => {
      if (bulkActionSelect) {
        bulkActionSelect.removeEventListener('change', handleBulkAction);
      }
      if (bulkActionSelect2) {
        bulkActionSelect2.removeEventListener('change', handleBulkAction);
      }
    };
  }, []);

  const handleBulkVerification = async () => {
    const checkboxes = document.querySelectorAll('input[name="users[]"]:checked');
    const userIds = Array.from(checkboxes).map(cb => cb.value);

    if (userIds.length === 0) {
      setMessage({ type: 'error', text: wckb_customer.strings.no_emails_selected });
      return;
    }

    if (!confirm(wckb_customer.strings.confirm_batch)) {
      return;
    }

    setLoading(true);
    setMessage({ type: '', text: '' });

    try {
      const response = await fetch(wckb_customer.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_verify_customer_batch',
          nonce: wckb_customer.nonce,
          user_ids: userIds.join(',')
        })
      });

      const data = await response.json();
      
      if (data.success) {
        setMessage({ type: 'success', text: data.data.message });
        // Refresh the page after a short delay
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        setMessage({ type: 'error', text: data.data.message || wckb_customer.strings.verification_error });
      }
    } catch (error) {
      setMessage({ type: 'error', text: wckb_customer.strings.verification_error });
    } finally {
      setLoading(false);
    }
  };

  const verifySingleUser = async (userId, email) => {
    setLoading(true);
    setMessage({ type: '', text: '' });

    try {
      const response = await fetch(wckb_customer.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_verify_single_customer',
          nonce: wckb_customer.nonce,
          user_id: userId,
          email: email
        })
      });

      const data = await response.json();
      
      if (data.success) {
        setMessage({ type: 'success', text: `Email ${email} verified successfully!` });
        // Refresh the page after a short delay
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        setMessage({ type: 'error', text: data.data.message || wckb_customer.strings.verification_error });
      }
    } catch (error) {
      setMessage({ type: 'error', text: wckb_customer.strings.verification_error });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="wckb-customer-verification">
      {message.text && (
        <div className={`notice notice-${message.type}`}>
          <p>{message.text}</p>
        </div>
      )}

      {loading && (
        <div className="wckb-verification-loading">
          <span className="spinner is-active"></span>
          {wckb_customer.strings.verifying}
        </div>
      )}

      <div className="wckb-verification-info">
        <p>
          <strong>Bulk Email Verification:</strong> Select users from the table above and choose "Verify Emails" from the bulk actions dropdown.
        </p>
        <p>
          <strong>Individual Verification:</strong> Click the verification status in the "Verified" column to verify a single email.
        </p>
      </div>
    </div>
  );
};

export default CustomerVerification;
