import React from 'react';
import { render, screen, act, waitFor } from '@testing-library/react';
import VerificationStats from '../VerificationStats';

// Mock global WordPress variables
global.kickbox_integration_admin = {
  ajax_url: 'http://localhost/wp-admin/admin-ajax.php',
  nonce: 'test-nonce',
  strings: {
    stats_loading: 'Loading statistics...',
    stats_error: 'Failed to load statistics'
  }
};

// Mock fetch for API calls
global.fetch = jest.fn();

describe('VerificationStats Component', () => {
  beforeEach(() => {
    global.fetch.mockClear();
  });

  it('renders without crashing', async () => {
    await act(async () => {
      render(<VerificationStats />);
    });
    // Wait for async operations to complete and check for actual content
    await waitFor(() => {
      expect(screen.getByText('Unable to load statistics.')).toBeInTheDocument();
    });
  });

  it('shows error state after failed API call', async () => {
    await act(async () => {
      render(<VerificationStats />);
    });
    // Wait for async operations to complete and check for error state
    await waitFor(() => {
      expect(screen.getByText('Unable to load statistics.')).toBeInTheDocument();
    });
  });
});
