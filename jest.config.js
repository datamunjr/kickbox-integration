module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/src/js/__tests__/setup.js'],
  testMatch: [
    '<rootDir>/src/js/**/__tests__/**/*.test.js',
    '<rootDir>/src/js/**/__tests__/**/*.test.jsx'
  ],
  moduleNameMapper: {
    '\\.(css|less|scss|sass)$': 'identity-obj-proxy'
  },
  transform: {
    '^.+\\.(js|jsx)$': 'babel-jest'
  },
  moduleFileExtensions: ['js', 'jsx', 'json'],
  collectCoverageFrom: [
    'src/js/**/*.{js,jsx}',
    '!src/js/**/__tests__/**',
    '!src/js/**/*.test.{js,jsx}'
  ],
  transformIgnorePatterns: [
    'node_modules/(?!(react-chartjs-2|chart.js)/)'
  ]
};
