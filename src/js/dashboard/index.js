import React from 'react';
import { createRoot } from 'react-dom/client';
import DashboardWidget from './components/DashboardWidget';

document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('kickbox-integration-dashboard-widget');
  if (container) {
    const root = createRoot(container);
    root.render(<DashboardWidget />);
  }
});
