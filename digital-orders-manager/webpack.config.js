const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'admin-settings': './assets/js/src/admin-settings.js',
        'frontend': './assets/js/src/frontend.js',
        'admin-style': './assets/css/src/admin-style.scss',
        'frontend-style': './assets/css/src/frontend-style.scss',
    },
    output: {
        filename: '[name].js',
        path: __dirname + '/assets/js',
    },
};
