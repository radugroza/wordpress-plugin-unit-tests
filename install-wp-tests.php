<?php

function check_requirements() {
	//1. test for svn command
	exec( 'svn --version', $out );
	if ( empty( $out ) ) {
		exit( 'FATAL: svn command not found. Add svn to your PATH. Windows: See SO: http://stackoverflow.com/a/9874961/2388747' );
	}
	//2. test phpunit command
	exec( 'phpunit --version', $out2 );
	if ( empty( $out2 ) ) {
		exit( 'FATAL: phpunit command not found. How to set it up: https://phpunit.de/manual/current/en/installation.html' );
	}

}

check_requirements();

function download( $url, $path ) {
	$in  = fopen( $url, 'rb' );
	$out = fopen( $path, 'wb' );
	if ( ! $in ) {
		exit( 'FATAL: cannot open ' . $url );
	}
	if ( ! $out ) {
		exit( 'FATAL: cannot open ' . $path );
	}
	$size = 0;
	while ( ! feof( $in ) ) {
		$buffer = fgets( $in, 8096 );
		$size += fwrite( $out, $buffer );
		if ( $size % 100 == 0 ) {
			echo "\r" . sprintf( '%.2f', $size / 1024 / 1024 ) . "MB";
		}
	}
	fclose( $in );
	fclose( $out );
}

function unzip( $path ) {
	$zip = new ZipArchive();
	if ( ! $zip->open( $path ) ) {
		exit( 'FATAL: could not open: ' . $path );
	}
	if ( ! $zip->extractTo( dirname( $path ) ) ) {
		exit( 'FATAL: could not extract: ' . $path );
	}
	$zip->close();
}

echo "This will install the wordpress testing suite\n";

$wp_plugin_path = realpath( dirname( __DIR__ ) );
echo "Full plugin path is: {$wp_plugin_path}. (Enter to accept or type in new path): ";

$handle = fopen( 'php://stdin', 'r' );
$line   = trim( fgets( $handle ) );

$wp_plugin_path = $line ? $line : $wp_plugin_path;
$wp_plugin      = basename( $wp_plugin_path ) . '.php';
$wp_plugin_path = rtrim( $wp_plugin_path, '/\\ ' );
while ( ! is_dir( $wp_plugin_path ) || ! file_exists( "{$wp_plugin_path}/{$wp_plugin}" ) ) {
	echo "{$wp_plugin_path} not a directory or {$wp_plugin_path}/{$wp_plugin} not found\nTry again: ";
	$line           = trim( fgets( $handle ) );
	$wp_plugin_path = $line ? $line : $wp_plugin_path;
	$wp_plugin_path = rtrim( $wp_plugin_path, '/\\ ' );
	$wp_plugin      = basename( $wp_plugin_path );
}

$wp_plugin_tests = $wp_plugin_path . '/tests';

if ( ! is_dir( $wp_plugin_tests ) && ! @mkdir( $wp_plugin_tests, 0777 ) ) {
	exit( 'Fatal: could not create tests folder: ' . $wp_plugin_tests . ' Make sure the permissions are set correctly' );
}

/**
 * install wordpress testing suite for a plugin
 */
$default_tmp_dir = "C:\\tmp";
echo "Input temp directory or ENTER for default (Default: {$default_tmp_dir}) : ";

$line = trim( fgets( $handle ) );

$tmp_dir = $line ? $line : $default_tmp_dir;

while ( ! is_dir( $tmp_dir ) ) {
	echo "{$tmp_dir} Not a directory\nInput temp directory or ENTER for default (Default: {$default_tmp_dir}) : ";
	$line = trim( fgets( $handle ) );

	$tmp_dir = $line ? $line : $default_tmp_dir;
}

$tmp_dir = rtrim( $tmp_dir, '\\/' );

$wp_core_dir  = $tmp_dir . '/wordpress';
$wp_tests_dir = $tmp_dir . '/wordpress-tests-lib';

$default_wp_version = 'latest';

echo "Wordpress version to install: (Default: latest) : ";
$line       = trim( fgets( $handle ) );
$wp_version = $line ? $line : $default_wp_version;

echo "mysql username: (Default: root) : ";
$line     = trim( fgets( $handle ) );
$username = $line ? $line : 'root';

echo "mysql password: (Default: ) : ";
$line     = trim( fgets( $handle ) );
$password = $line ? $line : '';

echo "mysql database (will be dropped before creating it - do not use your wordpress database !): (Default: wordpress_tests_db) : ";
$line = trim( fgets( $handle ) );
$db   = $line ? $line : 'wordpress_tests_db';

if ( ! is_dir( $wp_core_dir ) && ! mkdir( $wp_core_dir, 0777, true ) ) {
	exit( 'FATAL: cannot create ' . $wp_core_dir );
}

if ( ! is_dir( $wp_tests_dir ) && ! mkdir( $wp_tests_dir, 0777, true ) ) {
	exit( 'FATAL: cannot create ' . $wp_tests_dir );
}

/* step 1 - install WP core */
$wp_url = "https://wordpress.org/wordpress-{$wp_version}.zip";
echo 'Downloading wordpress... ' . "\n";
download( $wp_url, $tmp_dir . '/' . basename( $wp_url ) );
echo "Done\nExtracting wordpress...";
unzip( $tmp_dir . '/' . basename( $wp_url ) );
echo "Done\n";
/* install DB */
download( 'https://raw.github.com/markoheijnen/wp-mysqli/master/db.php', "{$wp_core_dir}/wp-content/db.php" );

/* step 2 - download WP tests suite */
$wp_tests_tag = $wp_version === 'latest' ? 'trunk' : "tags/{$wp_version}";
echo 'Checking for test suite...';
if ( ! is_dir( $wp_tests_dir . '/includes' ) ) {
	exec( "svn co --quiet https://develop.svn.wordpress.org/{$wp_tests_tag}/tests/phpunit/includes/ {$wp_tests_dir}/includes" );
}
echo "Done\n";
if ( ! is_file( $wp_tests_dir . '/wp-tests-config.php' ) ) {
	download( "https://develop.svn.wordpress.org/{$wp_tests_tag}/wp-tests-config-sample.php", "{$wp_tests_dir}/wp-tests-config.php" );
	// modify the config file to include db credentials
	$contents = file_get_contents( $wp_tests_dir . '/wp-tests-config.php' );
	$contents = str_replace(
		array(
			'youremptytestdbnamehere',
			'yourusernamehere',
			'yourpasswordhere',
			"dirname( __FILE__ ) . '/src/'",
		),
		array(
			$db,
			$username,
			$password,
			"dirname( dirname( __FILE__ ) ) . '/wordpress/'",
		),
		$contents
	);
	file_put_contents( $wp_tests_dir . '/wp-tests-config.php', $contents );
}

/* creating the bootstrap.php file */
$bootstrap = '<?php

$_tests_dir = \'' . $wp_tests_dir . '\';

require_once $_tests_dir . \'/includes/functions.php\';

function _manually_load_plugin() {
	define( \'WP_ADMIN\', true );
	/** this is required to also include the admin-only functions from the plugin */
	$_SERVER[ \'PHP_SELF\' ] = \'/wp-admin/somerandompage\';
	require dirname( dirname( __FILE__ ) ) . \'/' . $wp_plugin . '\';
}

tests_add_filter( \'muplugins_loaded\', \'_manually_load_plugin\' );

require $_tests_dir . \'/includes/bootstrap.php\';
';
file_put_contents( $wp_plugin_tests . '/bootstrap.php', $bootstrap );

/* create phpunit.xml.dist file */
$phpunit = '<phpunit
	bootstrap="tests/bootstrap.php"
	backupGlobals="false"
	colors="true"
	convertErrorsToExceptions="true"
	convertNoticesToExceptions="true"
	convertWarningsToExceptions="true"
	>
	<testsuites>
		<testsuite>
			<directory prefix="test-" suffix=".php">./tests/</directory>
		</testsuite>
	</testsuites>
</phpunit>
';
file_put_contents( $wp_plugin_path . '/phpunit.xml.dist', $phpunit );

/* create the test-sample.php file */
$sample = '<?php

class SampleTest extends WP_UnitTestCase {

	function test_sample() {
		// replace this with some actual testing code
		$this->assertTrue( true );
	}
}

';
file_put_contents( $wp_plugin_tests . '/test-sample.php', $sample );

echo 'Creating database...';
/* step 3 - drop and re-create the tests table */
$driver = new mysqli( 'localhost', $username, $password );
$driver->query( "DROP DATABASE IF EXISTS `{$db}`" );
if ( ! $driver->query( "CREATE DATABASE `{$db}`" ) ) {
	exit( 'FATAL: could not create test database ' . $db );
}

if ( is_dir( $wp_plugin_path . '/.git/hooks' ) && ! file_exists( $wp_plugin_path . '/.git/hooks/pre-commit' ) ) {
	echo "Would you also like to install the phpunit pre-commit hook ( Default: Y ) ? (Y/n): ";

	$option = trim( fgets( $handle ) );
	$option = $option ? $option : 'Y';
	while ( $option !== 'Y' && $option !== 'n' ) {
		echo "Invalid option: {$option}. Try again: ";
		$option = trim( fgets( $handle ) );
		$option = $option ? $option : 'Y';
	}
	$pre_commit_hook = "#!/bin/sh\n\n";
	$pre_commit_hook .= ( stristr( PHP_OS, 'WIN' ) !== false ? 'phpunit.bat' : 'phpunit' );
	if ( $option === 'Y' ) {
		file_put_contents( $wp_plugin_path . '/.git/hooks/pre-commit', $pre_commit_hook );
	}
}

fclose( $handle );

echo "Done. Add phpunit.phar and {$wp_tests_dir} into your project's library\n";