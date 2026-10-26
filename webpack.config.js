const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
    entry: './views/src/index.js',
    output: {
        filename: 'bundle.[contenthash:8].js',
        path: path.resolve(__dirname, 'dist'),
        clean: true,
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env', '@babel/preset-react'],
                    },
                },
            },
            {
                test: /\.css$/i,
                use: [MiniCssExtractPlugin.loader, 'css-loader'],
            },
        ],
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: 'styles.[contenthash:8].css',
        }),
        new webpack.optimize.LimitChunkCountPlugin({
            maxChunks: 1,
        }),
    ],
    resolve: {
        extensions: ['.js', '.jsx'],
    },
    // WordPress provides wp.i18n (the script depends on wp-i18n): not bundled.
    externals: {
        '@wordpress/i18n': ['wp', 'i18n'],
    },
    optimization: {
        splitChunks: false,
        runtimeChunk: false,
        minimize: true,
    },
    mode: 'production',
};
