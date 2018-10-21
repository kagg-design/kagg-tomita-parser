<?php

/**
 * Yandex Tomita-parser interface.
 *
 * Class Tomita_Parser
 */
class Tomita_Parser {
	/**
	 * @var string Path to Yandex`s Tomita-parser binary
	 */
	protected $exec_path;

	/**
	 * @var string Path to Yandex`s Tomita-parser configuration file
	 */
	protected $config_path;

	/**
	 * @param string $exec_path Path to Yandex`s Tomita-parser binary
	 * @param string $config_path Path to Yandex`s Tomita-parser configuration file
	 */
	public function __construct( $exec_path, $config_path ) {
		$this->exec_path   = $exec_path;
		$this->config_path = $config_path;
	}

	public function run( $text ) {
		$descriptors = array(
			0 => array( 'pipe', 'r' ), // stdin
			1 => array( 'pipe', 'w' ), // stdout
			2 => array( 'pipe', 'w' ), // stderr
		);

		$cmd     = sprintf( '%s %s', $this->exec_path, $this->config_path );
		$process = proc_open( $cmd, $descriptors, $pipes, dirname( $this->config_path ) );

		if ( is_resource( $process ) ) {
			fwrite( $pipes[0], $text );
			fclose( $pipes[0] );

			$output = stream_get_contents( $pipes[1] );
			$errors = stream_get_contents( $pipes[2] );

			fclose( $pipes[1] );
			fclose( $pipes[2] );
			proc_close( $process );

			return array(
				'output' => $this->process_text_result( $output ),
				'errors' => $errors,
			);
		} else {
			return false;
		}
	}

	/**
	 * Обработка текстового результата
	 *
	 * @param string $text
	 *
	 * @return string[]
	 */
	public function process_text_result( $text ) {
		return array_filter( explode( "\n", $text ) );
	}
}
