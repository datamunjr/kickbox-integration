import React, {useState, useEffect} from 'react';

const EmailVerification = () => {
    const [email, setEmail] = useState('');
    const [verificationStatus, setVerificationStatus] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [shouldBlockSubmission, setShouldBlockSubmission] = useState(false);
    const [lastVerifiedEmail, setLastVerifiedEmail] = useState('');


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
                    const newEmail = e.target.value;
                    setEmail(newEmail);
                    // Clear verification status when email changes
                    setVerificationStatus(null);
                    setError('');
                    setShouldBlockSubmission(false);
                    setLastVerifiedEmail('');
                };

                // Only listen to input events to track email changes, not for verification
                emailField.addEventListener('input', handleEmailChange);

                return () => {
                    emailField.removeEventListener('input', handleEmailChange);
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
    if (!kickbox_integration_checkout || !kickbox_integration_checkout.verification_enabled) {
        return null;
    }


    const isValidEmail = (email) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    };


    const getStatusMessage = (result) => {
        const messages = {
            deliverable: {
                text: kickbox_integration_checkout.strings.deliverable,
                type: 'success'
            },
            undeliverable: {
                text: kickbox_integration_checkout.strings.undeliverable,
                type: 'error'
            },
            risky: {
                text: kickbox_integration_checkout.strings.risky,
                type: 'warning'
            },
            unknown: {
                text: kickbox_integration_checkout.strings.unknown,
                type: 'info'
            }
        };

        return messages[result] || messages.unknown;
    };


    // Function to verify email (called on Place Order click)
    const verifyEmailOnSubmit = async () => {
        // If verification is not enabled, always allow submission
        if (!kickbox_integration_checkout || !kickbox_integration_checkout.verification_enabled) {
            return true;
        }

        if (!email || !isValidEmail(email)) {
            return false; // Don't verify if there's no email or if it's invalid
        }

        // If we've already verified this email and should block, prevent submission
        if (lastVerifiedEmail === email && shouldBlockSubmission) {
            return false; // Block submission
        }

        // If we've already verified this email and it's allowed, allow submission
        if (lastVerifiedEmail === email && !shouldBlockSubmission && verificationStatus) {
            return true; // Allow submission
        }

        setLoading(true);
        setError('');

        try {
            const response = await fetch(kickbox_integration_checkout.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kickbox_integration_verify_email',
                    nonce: kickbox_integration_checkout.nonce,
                    email: email
                })
            });

            const data = await response.json();

            if (data.success) {
                setVerificationStatus(data.data);
                setLastVerifiedEmail(email); // Store the email we just verified

                // Check if this result should block submission based on backend settings
                const result = data.data.result;
                const action = kickbox_integration_checkout.verification_actions[result] || 'allow';
                const shouldBlock = action === 'block';
                setShouldBlockSubmission(shouldBlock);


                return !shouldBlock; // Return true if submission should proceed
            } else {
                setError(data.data.message || kickbox_integration_checkout.strings.verification_error);
                return true; // Allow submission on API error
            }
        } catch (error) {
            setError(kickbox_integration_checkout.strings.verification_error);
            return true; // Allow submission on network error
        } finally {
            setLoading(false);
        }
    };

    // Make verifyEmailOnSubmit available globally for Place Order buttons
    useEffect(() => {
        window.kickboxIntegrationVerifyEmailOnSubmit = verifyEmailOnSubmit;
        return () => {
            delete window.kickboxIntegrationVerifyEmailOnSubmit;
        };
    }, [email, lastVerifiedEmail, shouldBlockSubmission, verificationStatus]);

    // Show blocking banner if verification failed and should block
    if (shouldBlockSubmission && verificationStatus) {
        return (
            <div className="kickbox_integration-checkout-blocked-banner">
                <div className="kickbox_integration-banner-content">
                    <span className="kickbox_integration-banner-icon">⚠️</span>
                    <div className="kickbox_integration-banner-text">
                        <strong>Checkout cannot continue</strong>
                        <p>{getStatusMessage(verificationStatus.result).text}</p>
                        <p>Please use a different email address to complete your order.</p>
                    </div>
                </div>
            </div>
        );
    }

    // Show loading banner if verification is in progress
    if (loading) {
        return (
            <div className="kickbox_integration-checkout-verifying-banner">
                <div className="kickbox_integration-banner-content">
                    <span className="spinner is-active"></span>
                    <div className="kickbox_integration-banner-text">
                        <strong>Verifying email address...</strong>
                        <p>Please wait while we verify your email address.</p>
                    </div>
                </div>
            </div>
        );
    }

    // Show error banner if verification failed
    if (error) {
        return (
            <div className="kickbox_integration-checkout-error-banner">
                <div className="kickbox_integration-banner-content">
                    <span className="kickbox_integration-banner-icon">❌</span>
                    <div className="kickbox_integration-banner-text">
                        <strong>Email verification failed</strong>
                        <p>{error}</p>
                        <p>You can still proceed with checkout.</p>
                    </div>
                </div>
            </div>
        );
    }

    // Don't render anything if no email or verification hasn't been triggered
    return null;
};

export default EmailVerification;
