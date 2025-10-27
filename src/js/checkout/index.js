import React from 'react';
import { createRoot } from 'react-dom/client';
import EmailVerification from './components/EmailVerification';

// Initialize the checkout email verification React app
function initCheckoutVerification() {
  const container = document.getElementById('kickbox_integration-checkout-verification');
  if (container && !container.hasAttribute('data-kickbox_integration-initialized')) {
    const root = createRoot(container);
    root.render(<EmailVerification />);
    container.setAttribute('data-kickbox_integration-initialized', 'true');
  }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initCheckoutVerification);

// Make function globally available for blocks checkout
window.kickboxIntegrationInitCheckoutVerification = initCheckoutVerification;
