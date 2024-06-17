/*jshint node:true */
module.exports = function (grunt) {
	'use strict';

	const sass = require('sass');

	const isProduction = process.env.NODE_ENV === 'production';
	const mode = isProduction ? 'production' : 'development';
	const makeSourceMaps = mode === 'development';

	grunt.initConfig({
		// Gets the package vars
		pkg: grunt.file.readJSON('package.json'),

		// Setting folder templates
		dirs: {
			css: 'assets/css',
			fonts: 'assets/fonts',
			images: 'assets/images',
			js: 'assets/js',
		},

		// Compile all .scss files.
		sass: {
			compile: {
				options: {
					sourceMap: makeSourceMaps,
					implementation: sass,
				},
				files: [
					{
						expand: true,
						cwd: '<%= dirs.css %>/',
						src: ['**/*.scss'],
						dest: '<%= dirs.css %>/',
						ext: '.min.css',
					},
				],
			},
		},

		// Minify all .css files.
		cssmin: {
			options: {
				sourceMap: makeSourceMaps,
			},
			minify: {
				expand: true,
				cwd: '<%= dirs.css %>/',
				src: ['**/*.css'],
				dest: '<%= dirs.css %>/',
				ext: '.min.css',
			},
		},

		// Transpile ES6 to ES5
		babel: {
			options: {
				sourceMaps: makeSourceMaps,
				comments: false,
				minified: true,
				presets: [
					[
						'@babel/preset-env',
						{
							targets: {
								esmodules: true,
							},
						},
					],
				],
			},
			dist: {
				files: [
					{
						expand: true,
						cwd: '<%= dirs.js %>/',
						src: ['**/*.js'],
						dest: '<%= dirs.js %>/',
						ext: '.min.js',
					},
				],
			},
		},

		// Watch changes for assets
		watch: {
			js: {
				files: [
					'<%= dirs.js %>/**/*.js',
					'!<%= dirs.js %>/**/*.min.js',
				],
				tasks: ['babel'],
			},
			sass: {
				files: ['<%= dirs.css %>/**/*.scss'],
				tasks: ['sass', 'cssmin'],
			},
		},

		// Shell scripts
		shell: {
			options: {
				stdout: true,
				stderr: true,
			},
			txpull: {
				command: [
					'tx pull -a -f', // Transifex download .po files
				].join('&&'),
			},
			txpush: {
				command: [
					'tx push -s', // Transifex - send .pot file
				].join('&&'),
			},
		},

		// Generate POT files.
		makepot: {
			options: {
				type: 'wp-plugin',
				domainPath: 'i18n/languages',
				potHeaders: {
					'report-msgid-bugs-to':
						'https://wordpress.org/support/plugin/woocommerce-square',
					'language-team': 'LANGUAGE <EMAIL@ADDRESS>',
				},
			},
			dist: {
				options: {
					potFilename: 'woocommerce-square.pot',
					exclude: ['apigen/.*', 'tests/.*', 'tmp/.*'],
				},
			},
		},

		// Check textdomain errors.
		checktextdomain: {
			options: {
				text_domain: 'woocommerce-square',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d',
				],
			},
			files: {
				src: [
					'**/*.php', // Include all files
					'!apigen/**', // Exclude apigen/
					'!node_modules/**', // Exclude node_modules/
					'!tests/**', // Exclude tests/
					'!vendor/**', // Exclude vendor/
					'!tmp/**', // Exclude tmp/
					'!test-plugins/**', // Exclude test-plugins/
				],
				expand: true,
			},
		},
	});

	// Load NPM tasks to be used here
	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-shell');
	grunt.loadNpmTasks('grunt-phpcs');
	grunt.loadNpmTasks('grunt-rtlcss');
	grunt.loadNpmTasks('grunt-postcss');
	grunt.loadNpmTasks('grunt-stylelint');
	grunt.loadNpmTasks('grunt-wp-i18n');
	grunt.loadNpmTasks('grunt-checktextdomain');
	grunt.loadNpmTasks('grunt-babel');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-clean');

	// Register tasks
	grunt.registerTask('default', ['css', 'js', 'i18n']);

	grunt.registerTask('js', ['babel']);

	grunt.registerTask('i18n', ['checktextdomain', 'makepot']);

	grunt.registerTask('css', ['sass', 'cssmin']);
};
