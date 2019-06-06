const path = require('path');

module.exports = {
    entry: {
        app: __dirname + '/src/index.js'
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: '[name].js'
    }
};
