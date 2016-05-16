<?php


function check_requirements() {
	//1. test for svn command
	exec( 'composer --version', $out );
	if ( empty( $out ) ) {
		exit( 'FATAL: composer command not found. Install Composer: https://getcomposer.org' );
	}
}


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

check_requirements();
exec( 'phpcs --version ', $o );
if ( empty( $o ) ) {
	echo "Installing php_codesniffer ...";

	exec( 'composer global require "squizlabs/php_codesniffer=*"' );

	echo "Done\n";

	echo 'Checking if phpcs command is available ... ';
	exec( 'phpcs --version', $output );
	if ( empty( $output ) ) {
		exit( 'FATAL: phpcs command not found. Make sure your PATH variable is setup correctly' );
	}

	echo "done\n";
}

/**
 * install wordpress testing suite for a plugin
 */
$default_tmp_dir = "C:\\tmp";
echo "Input a temp directory or ENTER for default (Default: {$default_tmp_dir}) : ";
$handle = fopen( 'php://stdin', 'r' );
$line   = trim( fgets( $handle ) );

$tmp_dir = $line ? $line : $default_tmp_dir;

while ( ! is_dir( $tmp_dir ) ) {
	echo "{$tmp_dir} Not a directory\nInput temp directory or ENTER for default (Default: {$default_tmp_dir}) : ";
	$line = trim( fgets( $handle ) );

	$tmp_dir = $line ? $line : $default_tmp_dir;
}

$tmp_dir = rtrim( $tmp_dir, '\\/' );

echo "downloading wordpress standards ... \n";

download( 'https://github.com/ThriveThemes/WordPress-Coding-Standards/archive/develop.zip', $tmp_dir . '/develop.zip' );
@exec( 'rm -rf ' . $tmp_dir . '/WordPress-Coding-Standards 2>&1' );
@exec( 'rd /s /q "' . $tmp_dir . '/WordPress-Coding-Standards" 2>&1' );
@exec( 'rd /s /q "' . $tmp_dir . '/WordPress-Coding-Standards" 2>&1' );
@exec( 'rd /s /q "' . $tmp_dir . '/WordPress-Coding-Standards" 2>&1' );

unzip( $tmp_dir . '/develop.zip' );
rename( $tmp_dir . '/WordPress-Coding-Standards-develop', $tmp_dir . '/WordPress-Coding-Standards' );

@unlink( $tmp_dir . 'develop.zip' );

exec( 'composer config --global home', $out );
while ( empty( $out ) || empty( $out[0] ) || ! is_dir( $out[0] ) ) {
	echo "Could not detect Composer home directory. Input it manually (on windows, it should be something like users/appdata/roaming/composer): ";
	$out[0] = fgets( $handle );
}

$composer_home = $out[0];

$cs_config = array(
	'default_standard' => 'WordPress-Extra',
	'installed_paths'  => $tmp_dir . '/WordPress-Coding-Standards/',
);

/* modify codesniffer to use the default WP standard */
$phpcs_path = rtrim( $composer_home, '/\\' ) . '/vendor/squizlabs/php_codesniffer/';
file_put_contents( $phpcs_path . 'CodeSniffer.conf', '<?php' . "\n" . '$phpCodeSnifferConfig = ' . var_export( $cs_config, true ) . ';' );

fclose( $handle );

echo "DONE. Run phpcs to code-sniff your code";
