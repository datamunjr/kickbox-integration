import React from 'react';
import { render, screen, act, waitFor } from '@testing-library/react';
import FlaggedEmails from '../FlaggedEmails';

// Mock global WordPress variables
global.kickbox_integration_admin = {
  ajax_url: 'http://localhost/wp-admin/admin-ajax.php',
  nonce: 'test-nonce',
  strings: {
    approve_success: 'Email approved successfully',
    reject_success: 'Email rejected successfully',
    approve_error: 'Failed to approve email',
    reject_error: 'Failed to reject email'
  }
};

// Mock fetch for API calls
global.fetch = jest.fn();

describe('FlaggedEmails Component', () => {
  beforeEach(() => {
    global.fetch.mockClear();
  });

  it('renders without crashing', async () => {
    await act(async () => {
      render(<FlaggedEmails />);
    });
    // Wait for async operations to complete and check for actual content
    await waitFor(() => {
      expect(screen.getByText('Search Email:')).toBeInTheDocument();
    });
  });

  it('shows error state after failed API call', async () => {
    await act(async () => {
      render(<FlaggedEmails />);
    });
    // Wait for async operations to complete and check for error state
    await waitFor(() => {
      expect(screen.getByText('Error loading flagged emails.')).toBeInTheDocument();
    });
  });
});
