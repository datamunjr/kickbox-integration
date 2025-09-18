import React from 'react';
import { createRoot } from 'react-dom/client';
import DashboardWidget from './components/DashboardWidget';
import '../../css/dashboard.css';

document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('wckb-dashboard-widget');
  if (container) {
    const root = createRoot(container);
    root.render(<DashboardWidget />);
  }
});
