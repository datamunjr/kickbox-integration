// Mock for moment.js
const moment = (date) => {
  const mockMoment = {
    isValid: () => true,
    format: (format) => {
      if (date) {
        return date;
      }
      return '2024-01-01';
    },
    isAfter: () => false,
    isSameOrBefore: () => true,
    add: () => mockMoment,
    subtract: () => mockMoment
  };
  return mockMoment;
};

moment.isValid = () => true;

module.exports = moment;

