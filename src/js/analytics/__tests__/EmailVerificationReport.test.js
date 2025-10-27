import React from 'react';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';
import '@testing-library/jest-dom';
import EmailVerificationReport from '../EmailVerificationReport';

// Mock fetch globally
global.fetch = jest.fn();

// Mock window.location
const mockLocation = {
  search: '',
  href: 'http://localhost:3000',
  pathname: '/test',
  origin: 'http://localhost:3000'
};

Object.defineProperty(window, 'location', {
  value: mockLocation,
  writable: true
});

// Mock URL constructor
global.URL = jest.fn((url) => ({
  searchParams: {
    delete: jest.fn(),
    toString: () => ''
  },
  toString: () => url
}));

// Mock window.history
const mockHistory = {
  pushState: jest.fn()
};

Object.defineProperty(window, 'history', {
  value: mockHistory,
  writable: true
});

// Mock URLSearchParams
global.URLSearchParams = jest.fn((search) => {
  const params = new Map();
  if (search && typeof search === 'string') {
    search.split('&').forEach(param => {
      const [k, v] = param.split('=');
      if (k && v) params.set(k, v);
    });
  }
  return {
    get: (key) => params.get(key),
    delete: (key) => params.delete(key),
    toString: () => Array.from(params.entries()).map(([k, v]) => `${k}=${v}`).join('&')
  };
});

// Mock global WordPress variables
global.kickboxAnalytics = {
  ajaxUrl: 'http://localhost/wp-admin/admin-ajax.php',
  nonce: 'test-nonce'
};

// Mock alert
global.alert = jest.fn();

describe('EmailVerificationReport', () => {
  beforeEach(() => {
    fetch.mockClear();
    mockHistory.pushState.mockClear();
    mockLocation.search = '';
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('Loading State', () => {
    it('should show loading spinner when loading is true', async () => {
      // Mock a delayed response to ensure loading state is visible
      fetch.mockImplementationOnce(() => 
        new Promise(resolve => 
          setTimeout(() => resolve({
            json: () => Promise.resolve({ success: true, data: { verification_stats: [] } })
          }), 100)
        )
      );

      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      // Check for loading state immediately after render
      expect(screen.getByTestId('spinner')).toBeInTheDocument();
      expect(screen.getByText('Loading statistics...')).toBeInTheDocument();
    });
  });

  describe('Error State', () => {
    it('should show error message when stats are null', async () => {
      fetch.mockResolvedValueOnce({
        json: () => Promise.resolve({ success: false })
      });

      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(screen.getByText('Unable to load statistics.')).toBeInTheDocument();
      });
    });
  });

  describe('Data Rendering', () => {
    const mockStats = [
      { verification_result: 'deliverable', count: 100 },
      { verification_result: 'undeliverable', count: 50 },
      { verification_result: 'risky', count: 25 }
    ];

    const mockRates = {
      success_rate: 66.7,
      failure_rate: 33.3,
      successful_verifications: 100,
      failed_verifications: 75
    };

    beforeEach(() => {
      fetch.mockResolvedValueOnce({
        json: () => Promise.resolve({
          success: true,
          data: {
            verification_stats: mockStats,
            reason_stats: [],
            success_failure_rates: mockRates
          }
        })
      });
    });

    it('should render verification statistics when data is available', async () => {
      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(screen.getByText('Total Verifications')).toBeInTheDocument();
        expect(screen.getByText('175')).toBeInTheDocument(); // Total count
        expect(screen.getByText('Deliverable')).toBeInTheDocument();
        expect(screen.getByText('100')).toBeInTheDocument();
        expect(screen.getByText('Undeliverable')).toBeInTheDocument();
        expect(screen.getByText('50')).toBeInTheDocument();
      });
    });

    it('should render success and failure rates', async () => {
      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(screen.getByText('Success Rate')).toBeInTheDocument();
        expect(screen.getByText('66.7%')).toBeInTheDocument();
        expect(screen.getByText('Failure Rate')).toBeInTheDocument();
        expect(screen.getByText('33.3%')).toBeInTheDocument();
      });
    });
  });

  describe('Empty State', () => {
    it('should show no verifications found message when total is 0', async () => {
      fetch.mockResolvedValueOnce({
        json: () => Promise.resolve({
          success: true,
          data: {
            verification_stats: [],
            reason_stats: [],
            success_failure_rates: { success_rate: 0, failure_rate: 0, successful_verifications: 0, failed_verifications: 0 }
          }
        })
      });

      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(screen.getByText('No Verifications Found')).toBeInTheDocument();
        expect(screen.getByText('Could not find any verifications for this time range!')).toBeInTheDocument();
      });
    });
  });

  describe('URL Parameters', () => {
    it('should make AJAX request with date parameters when URL params exist', async () => {
      // Mock URL with date parameters
      mockLocation.search = 'start_date=2024-01-01&end_date=2024-01-31';
      
      fetch.mockResolvedValueOnce({
        json: () => Promise.resolve({
          success: true,
          data: { verification_stats: [] }
        })
      });

      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(fetch).toHaveBeenCalledWith(
          'http://localhost/wp-admin/admin-ajax.php',
          expect.objectContaining({
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: expect.any(Object) // URLSearchParams object
          })
        );
      });
    });

    it('should not make AJAX request with date parameters when URL params are invalid', async () => {
      // Mock URL with invalid date parameters
      mockLocation.search = 'start_date=invalid&end_date=2024-01-31';
      
      fetch.mockResolvedValueOnce({
        json: () => Promise.resolve({
          success: true,
          data: { verification_stats: [] }
        })
      });

      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(fetch).toHaveBeenCalledWith(
          'http://localhost/wp-admin/admin-ajax.php',
          expect.objectContaining({
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: expect.not.stringContaining('start_date=invalid')
          })
        );
      });
    });
  });

  describe('Date Range Picker', () => {
    beforeEach(() => {
      fetch.mockResolvedValueOnce({
        json: () => Promise.resolve({
          success: true,
          data: { verification_stats: [] }
        })
      });
    });

    it('should render date range picker button', async () => {
      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(screen.getByText('Select date range')).toBeInTheDocument();
      });
    });

    it('should open popover when date range button is clicked', async () => {
      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        const button = screen.getByText('Select date range');
        fireEvent.click(button);
        
        expect(screen.getByTestId('popover')).toBeInTheDocument();
        expect(screen.getByTestId('date-range')).toBeInTheDocument();
      });
    });

    it('should render Clear Dates button', async () => {
      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(screen.getByText('Clear Dates')).toBeInTheDocument();
      });
    });

    it('should call clear dates function when Clear Dates button is clicked', async () => {
      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        const clearButton = screen.getByText('Clear Dates');
        fireEvent.click(clearButton);
        
        // Should call URL constructor to create new URL without date parameters
        expect(global.URL).toHaveBeenCalledWith(window.location);
      });
    });
  });

  describe('Date Range Input', () => {
    beforeEach(() => {
      fetch.mockResolvedValueOnce({
        json: () => Promise.resolve({
          success: true,
          data: { verification_stats: [] }
        })
      });
    });

    it('should update start date when input changes', async () => {
      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        const button = screen.getByText('Select date range');
        fireEvent.click(button);
        
        const startDateInput = screen.getByTestId('start-date-input');
        fireEvent.change(startDateInput, { target: { value: '2024-01-01' } });
        
        expect(startDateInput.value).toBe('2024-01-01');
      });
    });

    it('should update end date when input changes', async () => {
      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        const button = screen.getByText('Select date range');
        fireEvent.click(button);
        
        const endDateInput = screen.getByTestId('end-date-input');
        fireEvent.change(endDateInput, { target: { value: '2024-01-31' } });
        
        expect(endDateInput.value).toBe('2024-01-31');
      });
    });
  });

  describe('Error Handling', () => {
    it('should handle fetch errors gracefully', async () => {
      fetch.mockRejectedValueOnce(new Error('Network error'));

      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(screen.getByText('Unable to load statistics.')).toBeInTheDocument();
      });
    });

    it('should handle API error responses', async () => {
      fetch.mockResolvedValueOnce({
        json: () => Promise.resolve({
          success: false,
          data: { message: 'Database error occurred' }
        })
      });

      await act(async () => {
        render(<EmailVerificationReport />);
      });
      
      await waitFor(() => {
        expect(global.alert).toHaveBeenCalledWith(
          expect.stringContaining('Error loading statistics: Database error occurred')
        );
      });
    });
  });
});
