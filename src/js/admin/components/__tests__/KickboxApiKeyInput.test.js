import React from 'react';
import { render, screen, act, waitFor } from '@testing-library/react';
import KickboxApiKeyInput from '../KickboxApiKeyInput';

// Mock global WordPress variables
global.kickbox_integration_admin = {
  ajax_url: 'http://localhost/wp-admin/admin-ajax.php',
  nonce: 'test-nonce',
  strings: {
    api_success: 'API connection successful',
    api_error: 'API connection failed'
  }
};

// Mock fetch for API calls
global.fetch = jest.fn();

describe('KickboxApiKeyInput Component', () => {
  beforeEach(() => {
    global.fetch.mockClear();
  });

  it('renders without crashing', async () => {
    await act(async () => {
      render(<KickboxApiKeyInput />);
    });
    // Wait for any async operations to complete
    await waitFor(() => {
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });
  });

  it('renders the API key input field', async () => {
    await act(async () => {
      render(<KickboxApiKeyInput />);
    });
    // Wait for any async operations to complete
    await waitFor(() => {
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });
    
    const input = screen.getByRole('textbox');
    expect(input).toHaveAttribute('type', 'text');
  });

  it('renders the test connection button', async () => {
    await act(async () => {
      render(<KickboxApiKeyInput />);
    });
    // Wait for any async operations to complete
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /test connection/i })).toBeInTheDocument();
    });
  });
});
