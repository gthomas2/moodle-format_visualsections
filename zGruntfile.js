/**
 * Gruntfile for compiling plugin .scss files AND .vue files.
 *
 * This file configures tasks to be run by Grunt
 * http://gruntjs.com/ for the current theme.
 *
 * Requirements:
 * nodejs, npm, grunt-cli.
 *
 * Installation:
 * node and npm: instructions at http://nodejs.org/
 * grunt-cli: `[sudo] npm install -g grunt-cli`
 * node dependencies: run `npm install` in the root directory.
 *
 * Usage:
 * Default behaviour is to watch all .less files and compile
 * into compressed CSS when a change is detected to any and then
 * clear the theme's caches. Invoke either `grunt` or `grunt watch`
 * in the theme's root directory.
 *
 * To separately compile only moodle or editor .less files
 * run `grunt less:moodle` or `grunt less:editor` respectively.
 *
 * To only clear the theme caches invoke `grunt exec:decache` in
 * the theme's root directory.
 *
 * @author Joby Harding / David Scotson / Stuart Lamour / Guy Thomas (vue files and es5 transpilation)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const sass = require('node-sass');

module.exports = function(grunt) {

    // We need to include the core Moodle grunt file too, so that we can run the tasks that are defined there.
    require("grunt-load-gruntfile")(grunt);
    grunt.loadGruntfile("../../../Gruntfile.js");

    // PHP strings for exec task.
    const moodleroot = 'dirname(dirname(dirname(__DIR__)))',
        configfile = moodleroot + ' . "/config.php"';
    let decachephp = '';

    decachephp += "define(\"CLI_SCRIPT\", true);";
    decachephp += "require(" + configfile + ");";

    // The previously used theme_reset_all_caches() stopped working for us, we investigated but couldn't figure out why.
    // Using purge_all_caches() is a bit of a nuclear option, as it clears more than we should need to
    // but it gets the job done.
    decachephp += "purge_all_caches();";

    grunt.mergeConfig = grunt.config.merge;

    grunt.mergeConfig({
        sass: {
            compile: {
                options: {
                    compress: false,
                    implementation: sass,
                    sourceMap: true
                },
                files: {
                    "styles.css": "scss/styles.scss",
                }
            }
        },
        sasslint: {
            src: "styles.css",
            target: "scss/styles.scss"
        },
        autoprefixer: {
            options: {
                browsers: [
                    'Android 2.3',
                    'Android >= 4',
                    'Chrome >= 20',
                    'Firefox >= 24', // Firefox 24 is the latest ESR.
                    'Explorer >= 9',
                    'iOS >= 6',
                    'Opera >= 12.1',
                    'Safari >= 6'
                ]
            },
            core: {
                options: {
                    map: false
                },
                src: ['styles.css'],
            },
        },
        exec: {
            decache: {
                cmd: "php -r '" + decachephp + "'",
                callback: function(error, stdout, stderror) {
                    // Exec will output error messages.
                    // Just add one to confirm success.
                    if (!error) {
                        grunt.log.writeln("Moodle theme cache reset.");
                    }
                }
            }
        },
        watch: {
            // Watch for any changes to sass files and compile.
            sass: {
                files: ["scss/*.scss"],
                tasks: ["compile"],
                options: {
                    spawn: false
                }
            }
        }
    });

    // Load contrib tasks.
    grunt.loadNpmTasks("grunt-autoprefixer");
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-sass-lint');
    grunt.loadNpmTasks("grunt-sass");
    grunt.loadNpmTasks("grunt-contrib-watch");
    grunt.loadNpmTasks("grunt-exec");

    // Register tasks.
    grunt.registerTask("default", ["watch"]);
    grunt.registerTask("compile", ["sass:compile", "autoprefixer", "decache"]);
    grunt.registerTask("decache", ["exec:decache"]);
};
