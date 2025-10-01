import React from 'react';
import { render, screen, act, waitFor } from '@testing-library/react';
import AdminSettings from '../AdminSettings';

// Mock the child components to avoid complex dependencies
jest.mock('../ApiSettings', () => {
  return function MockApiSettings() {
    return <div data-testid="api-settings">API Settings Component</div>;
  };
});

jest.mock('../VerificationActions', () => {
  return function MockVerificationActions() {
    return <div data-testid="verification-actions">Verification Actions Component</div>;
  };
});

jest.mock('../VerificationStats', () => {
  return function MockVerificationStats() {
    return <div data-testid="verification-stats">Verification Stats Component</div>;
  };
});

jest.mock('../AllowList', () => {
  return function MockAllowList() {
    return <div data-testid="allow-list">Allow List Component</div>;
  };
});

jest.mock('../FlaggedEmails', () => {
  return function MockFlaggedEmails() {
    return <div data-testid="flagged-emails">Flagged Emails Component</div>;
  };
});

describe('AdminSettings Component', () => {
  beforeEach(() => {
    // Reset fetch mock before each test
    global.fetch.mockClear();
    
    // Mock successful API responses for both settings and pending count
    global.fetch.mockImplementation((url, options) => {
      const body = new URLSearchParams(options.body);
      const action = body.get('action');
      
      if (action === 'kickbox_integration_get_settings') {
        return Promise.resolve({
          json: () => Promise.resolve({
            success: true,
            data: {
              apiKey: '',
              deliverableAction: 'allow',
              undeliverableAction: 'allow',
              riskyAction: 'allow',
              unknownAction: 'allow',
              enableCheckoutVerification: false,
              enableRegistrationVerification: false,
              enableCustomerVerification: false
            }
          })
        });
      } else if (action === 'kickbox_integration_get_pending_count') {
        return Promise.resolve({
          json: () => Promise.resolve({
            success: true,
            data: {
              pending_count: 0
            }
          })
        });
      }
      
      return Promise.resolve({
        json: () => Promise.resolve({ success: false })
      });
    });
  });

  it('renders without crashing', async () => {
    await act(async () => {
      expect(() => render(<AdminSettings />)).not.toThrow();
    });
  });

  it('renders the main container', async () => {
    await act(async () => {
      render(<AdminSettings />);
    });
    
    const container = document.querySelector('.kickbox_integration-admin-container');
    expect(container).toBeInTheDocument();
  });

  it('renders the header title', async () => {
    await act(async () => {
      render(<AdminSettings />);
    });
    
    const title = screen.getByText('Configure your Kickbox Integration Settings here!');
    expect(title).toBeInTheDocument();
  });

  it('renders the description text', async () => {
    await act(async () => {
      render(<AdminSettings />);
    });
    
    const description = screen.getByText(/For more information on how to get started with kickbox/);
    expect(description).toBeInTheDocument();
  });

  it('renders without errors', async () => {
    // This test ensures the component renders without throwing any errors
    await act(async () => {
      expect(() => render(<AdminSettings />)).not.toThrow();
    });
  });

  it('renders the default API Settings tab content after loading', async () => {
    await act(async () => {
      render(<AdminSettings />);
    });
    
    // Wait for the component to finish loading
    await waitFor(() => {
      expect(screen.getByTestId('api-settings')).toBeInTheDocument();
    });
  });
});
