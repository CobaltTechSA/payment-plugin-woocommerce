const path = require('path');
const DependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');

module.exports = {
    mode: process.env.NODE_ENV || 'development',
    entry: {
        'cbo-standard': './assets/js/blocks/cbo-standard.js',
        'cbo-telered': './assets/js/blocks/cbo-telered.js',
    },
    output: {
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js',
    },
    plugins: [
        new DependencyExtractionWebpackPlugin()
    ],
    module: {
        rules: [
            {
                test: /\.svg$/i,
                type: 'asset/resource'
            },
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-react', '@babel/preset-env'],
                    },
                },
            }
        ]
    },
    resolve: {
       extensions: ['.js', '.jsx', '.json', '.svg'],
    }
};
