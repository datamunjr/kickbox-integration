// Mock for @wordpress/viewport
const React = require('react');

const withViewportMatch = (config) => (Component) => {
  const WrappedComponent = (props) => {
    const viewportProps = {};
    Object.keys(config).forEach(key => {
      viewportProps[key] = false; // Default to false for all viewport matches
    });
    return React.createElement(Component, { ...props, ...viewportProps });
  };
  WrappedComponent.displayName = `withViewportMatch(${Component.displayName || Component.name})`;
  return WrappedComponent;
};

module.exports = {
  withViewportMatch
};
