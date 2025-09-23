import React from 'react';
import { createRoot } from 'react-dom/client';
import AdminSettings from './components/AdminSettings';
import '../../css/admin.css';

// Initialize the admin settings React app
document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('kickbox_integration-admin-app');
  if (container) {
    const root = createRoot(container);
    root.render(<AdminSettings />);
  }
});
