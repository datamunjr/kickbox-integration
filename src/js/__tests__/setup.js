require('@testing-library/jest-dom');

// Mock global WordPress variables that the component expects
global.kickbox_integration_admin = {
  ajax_url: 'http://localhost/wp-admin/admin-ajax.php',
  nonce: 'test-nonce',
  strings: {
    api_success: 'API connection successful',
    api_error: 'API connection failed'
  }
};

// Mock window.location for URL parameter handling
Object.defineProperty(window, 'location', {
  value: {
    search: '?tab=api',
    href: 'http://localhost/wp-admin/admin.php?page=kickbox-integration-settings&tab=api'
  },
  writable: true
});

// Mock window.history for navigation
Object.defineProperty(window, 'history', {
  value: {
    pushState: jest.fn(),
    replaceState: jest.fn()
  },
  writable: true
});

// Mock fetch for API calls
global.fetch = jest.fn();

// Mock URLSearchParams
global.URLSearchParams = class URLSearchParams {
  constructor(search) {
    this.params = new Map();
    if (search && typeof search === 'string') {
      search.split('&').forEach(param => {
        const [key, value] = param.split('=');
        if (key && value) {
          this.params.set(key, value);
        }
      });
    }
  }
  
  get(key) {
    return this.params.get(key);
  }
  
  set(key, value) {
    this.params.set(key, value);
  }
};

// Mock URL class
global.URL = class URL {
  constructor(url) {
    this.href = url;
    this.searchParams = new URLSearchParams();
  }
};
