import React from 'react';
import { render, screen, act, waitFor } from '@testing-library/react';
import AllowList from '../AllowList';

// Mock global WordPress variables
global.kickbox_integration_admin = {
  ajax_url: 'http://localhost/wp-admin/admin-ajax.php',
  nonce: 'test-nonce',
  strings: {
    add_success: 'Email added successfully',
    remove_success: 'Email removed successfully',
    add_error: 'Failed to add email',
    remove_error: 'Failed to remove email'
  }
};

// Mock fetch for API calls
global.fetch = jest.fn();

describe('AllowList Component', () => {
  beforeEach(() => {
    global.fetch.mockClear();
  });

  it('renders without crashing', async () => {
    await act(async () => {
      render(<AllowList />);
    });
    // Wait for async operations to complete and check for actual content
    await waitFor(() => {
      expect(screen.getByText('Add Email to Allow List')).toBeInTheDocument();
    });
  });

  it('shows empty state after loading', async () => {
    await act(async () => {
      render(<AllowList />);
    });
    // Wait for async operations to complete and check for empty state
    await waitFor(() => {
      expect(screen.getByText('No emails in the allow list. Add emails above to skip verification for specific addresses.')).toBeInTheDocument();
    });
  });
});
