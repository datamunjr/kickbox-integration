// Mock for @wordpress/components
const React = require('react');

const Button = ({ children, onClick, variant, disabled, style, ...props }) => (
  <button 
    onClick={onClick} 
    disabled={disabled}
    style={style}
    data-variant={variant}
    {...props}
  >
    {children}
  </button>
);

const Card = ({ children, style, ...props }) => (
  <div style={style} {...props}>
    {children}
  </div>
);

const Spinner = () => <div data-testid="spinner">Loading...</div>;

const Popover = ({ children, anchorRef, position, offset }) => (
  <div data-testid="popover" data-position={position} data-offset={offset}>
    {children}
  </div>
);

module.exports = {
  Button,
  Card,
  Spinner,
  Popover
};
