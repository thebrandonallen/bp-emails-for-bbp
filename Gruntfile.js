/* jshint node:true */
module.exports = function(grunt) {

	var SOURCE_DIR = '',
		BUILD_DIR = 'build/',

		BPEBBP_JS = [
			'assets/js/*.js'
		],

		BPEBBP_EXCLUDED_JS = [
			'!assets/js/*.min.js'
		],

		BPEBBP_EXCLUDED_MISC = [
			'!**/assests/**',
			'!**/bin/**',
			'!**/build/**',
			'!**/coverage/**',
			'!**/node_modules/**',
			'!**/tests/**',
			'!Gruntfile.js*',
			'!package.json*',
			'!phpcs.xml*',
			'!phpunit.xml*',
			'!.*'
		];

	// Load tasks.
	require('matchdep').filterDev('grunt-*').forEach( grunt.loadNpmTasks );

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		checktextdomain: {
			options: {
				text_domain: 'bp-emails-for-bbp',
				correct_domain: false,
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'_n:1,2,4d',
					'_ex:1,2c,3d',
					'_nx:1,2,4c,5d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src: [ '**/*.php', '!node_modules/**/*' ],
				expand: true
			}
		},
		clean: {
			all: [ BUILD_DIR ],
			dynamic: {
				cwd: BUILD_DIR,
				dot: true,
				expand: true,
				src: []
			}
		},
		copy: {
			files: {
				files: [
					{
						cwd: '',
						dest: 'build/',
						dot: true,
						expand: true,
						src: ['**', '!**/.{svn,git}/**'].concat( BPEBBP_EXCLUDED_MISC )
					}
				]
			}
		},
		jshint: {
			options: {
				jshintrc: '.jshintrc'
			},
			all: ['Gruntfile.js']
		},
		makepot: {
			target: {
				options: {
					domainPath: '/languages',
					mainFile: 'bp-emails-for-bbp.php',
					potComments: 'Copyright (C) 2016-<%= grunt.template.today("UTC:yyyy") %> Brandon Allen\nThis file is distributed under the same license as the BP Emails for BBP package.',
					potFilename: 'bp-emails-for-bbp.pot',
					potHeaders: {
						poedit: true,
						'report-msgid-bugs-to': 'https://github.com/thebrandonallen/bp-emails-for-bbp',
						'last-translator': 'Brandon Allen <plugins@brandonallen.me>',
						'language-team': 'ENGLISH <plugins@brandonallen.me>'
					},
					processPot: function( pot ) {
						var translation, // Exclude meta data from pot.
							excluded_meta = [
								'Plugin Name of the plugin/theme',
								'Plugin URI of the plugin/theme',
								'Author of the plugin/theme',
								'Author URI of the plugin/theme'
								];
									for ( translation in pot.translations[''] ) {
										if ( 'undefined' !== typeof pot.translations[''][ translation ].comments.extracted ) {
											if ( excluded_meta.indexOf( pot.translations[''][ translation ].comments.extracted ) >= 0 ) {
												console.log( 'Excluded meta: ' + pot.translations[''][ translation ].comments.extracted );
													delete pot.translations[''][ translation ];
												}
											}
										}
						return pot;
					},
					type: 'wp-plugin'
				}
			}
		},
		phpunit: {
			'default': {
				cmd: 'phpunit',
				args: ['-c', 'phpunit.xml.dist']
			},
			multisite: {
				cmd: 'phpunit',
				args: ['-c', 'tests/phpunit/multisite.xml']
			}
		},
		'string-replace': {
			dev: {
				files: {
					'bp-emails-for-bbp.php': 'bp-emails-for-bbp.php',
				},
				options: {
					replacements: [{
						pattern: /(\*\sVersion:\s+).*/gm, // For plugin header
						replacement: '$1<%= pkg.version %>'
					}]
				}
			},
			build: {
				files: {
					'bp-emails-for-bbp.php': 'bp-emails-for-bbp.php',
					'readme.txt': 'readme.txt'
				},
				options: {
					replacements: [{
						pattern: /(\*\sVersion:\s+).*/gm, // For plugin header
						replacement: '$1<%= pkg.version %>'
					},
					{
						pattern: /(Stable tag:\s+).*/gm, // For readme.txt
						replacement: '$1<%= pkg.version %>'
					}]
				}
			}
		},
		watch: {
			js: {
				files: ['Gruntfile.js'],
				tasks: ['jshint']
			}
		},
		wp_readme_to_markdown: {
			core: {
				files: {
					'README.md': 'readme.txt'
				}
			}
		}
	});

	// Build tasks.
	grunt.registerTask( 'readme', [ 'wp_readme_to_markdown' ] );
	grunt.registerTask( 'src',    [ 'string-replace:dev' ] );
	grunt.registerTask( 'build',  [ 'clean:all', 'checktextdomain', 'string-replace:build', 'readme', 'makepot', 'copy:files' ] );

	// Register the default tasks.
	grunt.registerTask('default', ['watch']);

	// PHPUnit test task.
	grunt.registerMultiTask( 'phpunit', 'Runs PHPUnit tests, including the ajax and multisite tests.', function() {
		grunt.util.spawn( {
			cmd: this.data.cmd,
			args: this.data.args,
			opts: { stdio: 'inherit' }
		}, this.async() );
	} );
};
