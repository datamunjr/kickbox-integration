import React from 'react';
import { createRoot } from 'react-dom/client';
import CustomerVerification from './components/CustomerVerification';
import '../../css/customer.css';

// Initialize the customer verification React app
document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('wckb-customer-verification');
  if (container) {
    const root = createRoot(container);
    root.render(<CustomerVerification />);
  }
});
