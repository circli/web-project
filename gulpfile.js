// ## Globals
const argv         = require('minimist')(process.argv.slice(2));
const autoprefixer = require('gulp-autoprefixer');
const browserSync  = require('browser-sync').create();
const changed      = require('gulp-changed');
const concat       = require('gulp-concat');
const flatten      = require('gulp-flatten');
const gulp         = require('gulp');
const gulpif       = require('gulp-if');
const imagemin     = require('gulp-imagemin');
const jshint       = require('gulp-jshint');
const lazypipe     = require('lazypipe');
const merge        = require('merge-stream');
const cssNano      = require('gulp-cssnano');
const plumber      = require('gulp-plumber');
const rev          = require('gulp-rev');
const runSequence  = require('run-sequence');
const sass         = require('gulp-sass');
const uglify       = require('gulp-terser');
const fs           = require('fs');
const webpack = require('webpack-stream');

// See https://github.com/austinpray/asset-builder
let manifest = require('asset-builder')('./assets/manifest.json');

// `path` - Paths to base asset directories. With trailing slashes.
// - `path.source` - Path to the source files. Default: `assets/`
// - `path.dist` - Path to the build directory. Default: `dist/`
const path = manifest.paths;

// `config` - Store arbitrary configuration values here.
const config = manifest.config || {};

// `globs` - These ultimately end up in their respective `gulp.src`.
// - `globs.js` - Array of asset-builder JS dependency objects. Example:
//   ```
//   {type: 'js', name: 'main.js', globs: []}
//   ```
// - `globs.css` - Array of asset-builder CSS dependency objects. Example:
//   ```
//   {type: 'css', name: 'main.css', globs: []}
//   ```
// - `globs.fonts` - Array of font path globs.
// - `globs.images` - Array of image path globs.
// - `globs.bower` - Array of all the main Bower files.
const globs = manifest.globs;

// `project` - paths to first-party assets.
// - `project.js` - Array of first-party JS assets.
// - `project.css` - Array of first-party CSS assets.
const project = manifest.getProjectGlobs();

// CLI options
const enabled = {
    // Enable static asset revisioning when `--production`
    rev: argv.production,
    // Disable source maps when `--production`
    maps: !argv.production,
    // Fail styles task on error when `--production`
    failStyleTask: argv.production,
    // Fail due to JSHint warnings only when `--production`
    failJSHint: argv.production,
    // Strip debug statments from javascript when `--production`
    stripJSDebug: argv.production
};

// Path to the compiled assets manifest in the dist directory
let revManifest = path.dist + 'assets.json';

// Error checking; produce an error rather than crashing.
const onError = function(err) {
    console.log(err.toString());
    this.emit('end');
};

// ### CSS processing pipeline
// Example
// ```
// gulp.src(cssFiles)
//   .pipe(cssTasks('main.css')
//   .pipe(gulp.dest(path.dist + 'styles'))
// ```
const cssTasks = function (filename) {
    return lazypipe()
        .pipe(function () {
            return gulpif(!enabled.failStyleTask, plumber());
        })
        .pipe(function () {
            return gulpif('*.scss', sass({
                outputStyle: 'nested', // libsass doesn't support expanded yet
                precision: 10,
                includePaths: ['.', './assets/styles/.fallback'],
                errLogToConsole: !enabled.failStyleTask
            }));
        })
        .pipe(concat, filename)
        .pipe(autoprefixer, {
            overrideBrowserslist: [
                'last 2 versions',
            ]
        })
        .pipe(cssNano, {
            safe: true
        })
        .pipe(function () {
            return gulpif(enabled.rev, rev());
        })();
};

// ### JS processing pipeline
// Example
// ```
// gulp.src(jsFiles)
//   .pipe(jsTasks('main.js')
//   .pipe(gulp.dest(path.dist + 'scripts'))
// ```
const jsTasks = function (filename) {
    return lazypipe()
        .pipe(webpack())
        .pipe(function () {
            return gulpif(enabled.rev, rev());
        });
};

// ### Write to rev manifest
// If there are any revved files then write them to the rev manifest.
// See https://github.com/sindresorhus/gulp-rev
const writeToManifest = function(directory) {
    return lazypipe()
        .pipe(gulp.dest, path.dist + directory, {'sourcemaps': '.'})
        .pipe(browserSync.stream, {match: '**/*.{js,css}'})
        .pipe(rev.manifest, revManifest, {
            base: path.dist,
            merge: true
        })
        .pipe(gulp.dest, path.dist)();
};

// ### JSHint
// `gulp jshint` - Lints configuration JSON and project JS.
const jsLint = function() {
    return gulp.src([
        'gulpfile.js',
        '!assets/scripts/libs/*'
    ].concat(project.js))
        .pipe(jshint({
            "esversion": 6
        }))
        .pipe(jshint.reporter('jshint-stylish'))
        .pipe(gulpif(enabled.failJSHint, jshint.reporter('fail')));
};
gulp.task('jsLint', jsLint);

// ### Styles
// `gulp styles` - Compiles, combines, and optimizes Bower CSS and project CSS.
// By default this task will only log a warning if a precompiler error is
// raised. If the `--production` flag is set: this task will fail outright.
const styles = function(clb) {
    let merged = merge();
    manifest.forEachDependency('css', function(dep) {
        const cssTasksInstance = cssTasks(dep.name);
        if (!enabled.failStyleTask) {
            cssTasksInstance.on('error', function(err) {
                console.error(err.message);
                this.emit('end');
            });
        }
        let options = {base: 'styles'};
        if (enabled.maps) {
            options.sourcemaps = true;
        }

        merged.add(gulp.src(dep.globs, options)
            .pipe(plumber({errorHandler: onError}))
            .pipe(cssTasksInstance))
            .on("end", clb);
    });
    return merged
        .pipe(writeToManifest('styles'));
};
gulp.task('styles', styles);

// ### Scripts
// `gulp scripts` - Runs JSHint then compiles, combines, and optimizes Bower JS
// and project JS.
const scriptsBuild = function(clb) {
    let merged = merge();
    const webpackConfig = require('./assets/scripts/webpack.config');
    webpackConfig.mode = enabled.rev ? 'production': 'development';

    manifest.forEachDependency('js', function(dep) {
        merged.add(
            gulp.src(dep.globs, {base: 'scripts', sourcemaps: true})
                .pipe(webpack(webpackConfig))
                .pipe(gulpif(enabled.rev, rev()))
        );
    });
    return merged
        .pipe(writeToManifest('scripts'))
        .on("end", clb);
};
const scripts = gulp.series(jsLint, scriptsBuild);
gulp.task('scripts', scripts);

// ### Fonts
// `gulp fonts` - Grabs all the fonts and outputs them in a flattened directory
// structure. See: https://github.com/armed/gulp-flatten
const fonts = function() {
    return gulp.src(globs.fonts)
        .pipe(flatten())
        .pipe(gulp.dest(path.dist + 'fonts'))
        .pipe(browserSync.stream());
};
gulp.task('fonts', fonts);

// ### Images
// `gulp images` - Run lossless compression on all the images.
const images = function() {
    return gulp.src(globs.images)
        .pipe(imagemin([
            imagemin.jpegtran({progressive: true}),
            imagemin.gifsicle({interlaced: true}),
            imagemin.svgo({plugins: [
                    {removeUnknownsAndDefaults: false},
                    {cleanupIDs: false}
                ]})
        ]))
        .pipe(gulp.dest(path.dist + 'images'))
        .pipe(browserSync.stream());
};
gulp.task('images', images);

const svg = function() {
    return gulp.src('assets/svg/**/*')
        .pipe(imagemin([
            imagemin.svgo({plugins: [
                    {removeUnknownsAndDefaults: false},
                    {cleanupIDs: false}
                ]})
        ]))
        .pipe(gulp.dest(path.dist + 'svg'))
        .pipe(browserSync.stream());
};
gulp.task('svg', svg);

// ### Clean
// `gulp clean` - Deletes the build folder entirely.
const clean = require('del').bind(null, [path.dist]);
gulp.task('clean', clean);

// ### Watch
// `gulp watch` - Use BrowserSync to proxy your dev server and synchronize code
// changes across devices. Specify the hostname of your dev server at
// `manifest.config.devUrl`. When a modification is made to an asset, run the
// build step for that asset and inject the changes into the page.
// See: http://www.browsersync.io
gulp.task('watch', function() {
    fs.exists(revManifest, function() {
        fs.unlink(revManifest, function() {});
    });

    browserSync.init({
        files: ['{templates}/**/*.php', '*.php'],
        proxy: config.devUrl,
        port: 4000,
        open: false,
        ghostMode: {
            clicks: false,
            forms: false,
            scroll: false
        },
        snippetOptions: {}
    });
    gulp.watch([path.source + 'styles/**/*'], styles);
    gulp.watch([path.source + 'scripts/**/*'], scripts);
    gulp.watch([path.source + 'images/**/*'], images);
    gulp.watch([path.source + 'svg/**/*'], svg);
    gulp.watch([path.source + 'fonts/**/*'], fonts);
    gulp.watch(['assets/manifest.json'], build);
});

// ### Build
// `gulp build` - Run all the build tasks but don't clean up beforehand.
// Generally you should be running `gulp` instead of `gulp build`.
const build = gulp.parallel(styles, scripts, fonts, images, svg);
gulp.task('build', build);

// ### Gulp
// `gulp` - Run a complete build. To compile for production run `gulp --production`.
gulp.task('default', gulp.series(clean, build));
