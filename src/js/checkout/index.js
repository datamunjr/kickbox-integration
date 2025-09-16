import React from 'react';
import { createRoot } from 'react-dom/client';
import EmailVerification from './components/EmailVerification';
import '../../css/checkout.css';

// Initialize the checkout email verification React app
function initCheckoutVerification() {
  const container = document.getElementById('wckb-checkout-verification');
  if (container && !container.hasAttribute('data-wckb-initialized')) {
    const root = createRoot(container);
    root.render(<EmailVerification />);
    container.setAttribute('data-wckb-initialized', 'true');
  }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initCheckoutVerification);

// Make function globally available for blocks checkout
window.wckbInitCheckoutVerification = initCheckoutVerification;
