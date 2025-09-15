const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';
  
  return {
    entry: {
      'admin': './src/js/admin/index.js',
      'checkout': './src/js/checkout/index.js',
      'customer': './src/js/customer/index.js'
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
      'jquery': 'jQuery'
    },
    devtool: isProduction ? 'source-map' : 'eval-source-map'
  };
};
