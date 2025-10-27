// Mock for @wordpress/i18n
module.exports = {
  __: (text) => text,
  sprintf: (format, ...args) => {
    return format.replace(/%[sdj%]/g, (match) => {
      if (match === '%%') return '%';
      const arg = args.shift();
      return String(arg);
    });
  }
};

