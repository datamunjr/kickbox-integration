import React, { useState, useEffect } from 'react';

const EmailVerification = () => {
  const [email, setEmail] = useState('');
  const [verificationStatus, setVerificationStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');


  useEffect(() => {
    // Function to find and attach to email field
    const attachToEmailField = () => {
      // Try traditional checkout first
      let emailField = document.querySelector('#billing_email');
      
      // If not found, try blocks checkout
      if (!emailField) {
        emailField = document.querySelector('.wc-block-components-text-input input[type="email"]');
      }
      
      if (emailField) {
        const handleEmailChange = (e) => {
          setEmail(e.target.value);
          setVerificationStatus(null);
          setError('');
        };

        emailField.addEventListener('input', handleEmailChange);
        emailField.addEventListener('blur', handleEmailChange);

        return () => {
          emailField.removeEventListener('input', handleEmailChange);
          emailField.removeEventListener('blur', handleEmailChange);
        };
      }
      
      return null;
    };

    // Try to attach immediately
    const cleanup = attachToEmailField();
    
    // If not found, try again after a short delay (for blocks checkout)
    if (!cleanup) {
      const retryTimeout = setTimeout(() => {
        attachToEmailField();
      }, 500);
      
      return () => clearTimeout(retryTimeout);
    }
    
    return cleanup;
  }, []);

  // Don't render if verification is not enabled
  if (!wckb_checkout || !wckb_checkout.verification_enabled) {
    return null;
  }

  const verifyEmail = async (emailToVerify) => {
    if (!emailToVerify || !isValidEmail(emailToVerify)) {
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await fetch(wckb_checkout.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wckb_verify_email',
          nonce: wckb_checkout.nonce,
          email: emailToVerify
        })
      });

      const data = await response.json();

      console.log(`wckb-data: ${data}`);

      if (data.success) {
        setVerificationStatus(data.data);
      } else {
        setError(data.data.message || wckb_checkout.strings.verification_error);
      }
    } catch (error) {
      setError(wckb_checkout.strings.verification_error);
    } finally {
      setLoading(false);
    }
  };

  const isValidEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const getStatusMessage = (result) => {
    const messages = {
      deliverable: {
        text: wckb_checkout.strings.deliverable,
        type: 'success'
      },
      undeliverable: {
        text: wckb_checkout.strings.undeliverable,
        type: 'error'
      },
      risky: {
        text: wckb_checkout.strings.risky,
        type: 'warning'
      },
      unknown: {
        text: wckb_checkout.strings.unknown,
        type: 'info'
      }
    };

    return messages[result] || messages.unknown;
  };

  const getStatusIcon = (result) => {
    const icons = {
      deliverable: '✓',
      undeliverable: '✗',
      risky: '⚠',
      unknown: '?'
    };
    return icons[result] || '?';
  };

  // Auto-verify email when it changes (with debounce)
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (email && isValidEmail(email)) {
        verifyEmail(email);
      }
    }, 1000);

    return () => clearTimeout(timeoutId);
  }, [email]);

  if (!email || !isValidEmail(email)) {
    return null;
  }

  return (
    <div className="wckb-email-verification">
      {loading && (
        <div className="wckb-verification-loading">
          <span className="spinner is-active"></span>
          {wckb_checkout.strings.verifying}
        </div>
      )}

      {error && (
        <div className="wckb-verification-error">
          {error}
        </div>
      )}

      {verificationStatus && !loading && (
        <div className={`wckb-verification-status wckb-${verificationStatus.result}`}>
          <span className="wckb-status-icon">
            {getStatusIcon(verificationStatus.result)}
          </span>
          <span className="wckb-status-message">
            {getStatusMessage(verificationStatus.result).text}
          </span>
        </div>
      )}

      {verificationStatus && verificationStatus.reason && (
        <div className="wckb-verification-details">
          <small>{verificationStatus.reason}</small>
        </div>
      )}
    </div>
  );
};

export default EmailVerification;
