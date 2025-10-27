// Mock for @woocommerce/components
const React = require('react');

const DateRange = ({ after, before, onUpdate, focusedInput, afterText, beforeText, afterError, beforeError, shortDateFormat, shortDateFormatPlaceholder, losesFocusTo }) => (
  <div data-testid="date-range">
    <input 
      data-testid="start-date-input"
      value={afterText || ''}
      placeholder={shortDateFormatPlaceholder}
      onChange={(e) => onUpdate({ afterText: e.target.value, focusedInput: 'startDate' })}
    />
    <input 
      data-testid="end-date-input"
      value={beforeText || ''}
      placeholder={shortDateFormatPlaceholder}
      onChange={(e) => onUpdate({ beforeText: e.target.value, focusedInput: 'endDate' })}
    />
    {afterError && <div data-testid="start-date-error">{afterError}</div>}
    {beforeError && <div data-testid="end-date-error">{beforeError}</div>}
  </div>
);

module.exports = {
  DateRange
};

