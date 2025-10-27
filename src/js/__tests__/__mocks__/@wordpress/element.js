// Mock for @wordpress/element
const React = require('react');
module.exports = {
  ...React,
  Fragment: React.Fragment,
  useRef: React.useRef,
  useState: React.useState,
  useEffect: React.useEffect
};

