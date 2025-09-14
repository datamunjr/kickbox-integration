import React from 'react';
import { createRoot } from 'react-dom/client';
import EmailVerification from './components/EmailVerification';

// Initialize the checkout email verification React app
document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('wckb-checkout-verification');
  if (container) {
    const root = createRoot(container);
    root.render(<EmailVerification />);
  }
});
