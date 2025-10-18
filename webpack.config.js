const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';
  
  return {
    entry: {
      'admin': './src/js/admin/index.js',
      'checkout': './src/js/checkout/index.js',
      'dashboard': './src/js/dashboard/index.js',
      'analytics': './src/js/analytics/index.js'
    },
    output: {
      path: path.resolve(__dirname, 'assets/js'),
      filename: '[name].js',
      clean: true
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env', '@babel/preset-react']
            }
          }
        },
        {
          test: /\.css$/,
          use: [
            isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
            'css-loader'
          ]
        },
        {
          test: /\.(png|jpg|gif|svg)$/,
          use: {
            loader: 'file-loader',
            options: {
              outputPath: '../images/',
              publicPath: 'images/'
            }
          }
        }
      ]
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: '../css/[name].css'
      })
    ],
    resolve: {
      extensions: ['.js', '.jsx']
    },
    externals: {
      'jquery': 'jQuery',
      'react': 'React',
      'react-dom': 'ReactDOM',
      '@wordpress/hooks': 'wp.hooks',
      '@wordpress/i18n': 'wp.i18n',
      '@wordpress/element': 'wp.element',
      '@wordpress/components': 'wp.components',
      '@wordpress/data': 'wp.data',
      '@wordpress/date': 'wp.date',
      '@wordpress/dom-ready': 'wp.domReady',
      '@wordpress/editor': 'wp.editor',
      '@wordpress/block-editor': 'wp.blockEditor',
      '@wordpress/blocks': 'wp.blocks',
      '@wordpress/compose': 'wp.compose',
      '@wordpress/format-library': 'wp.formatLibrary',
      '@wordpress/notices': 'wp.notices',
      '@wordpress/nux': 'wp.nux',
      '@wordpress/plugins': 'wp.plugins',
      '@wordpress/rich-text': 'wp.richText',
      '@wordpress/server-side-render': 'wp.serverSideRender',
      '@wordpress/url': 'wp.url',
      '@wordpress/viewport': 'wp.viewport',
      '@woocommerce/components': 'wc.components',
      '@woocommerce/data': 'wc.data'
    },
    devtool: isProduction ? 'source-map' : 'eval-source-map'
  };
};
