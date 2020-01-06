<?php
namespace JsonConfig;

use Exception;
use stdClass;
use TitleValue;

/**
 * A value object class that contains namespace ID, title, and
 * the corresponding jsonconfig configuration
 * @package JsonConfig
 */
final class JCTitle extends TitleValue {

	/**
	 * @var stdClass
	 */
	private $config;

	/**
	 * JCTitle constructor.
	 * @param int $namespace Possibly belonging to a foreign wiki
	 * @param string $dbkey
	 * @param stdClass $config JsonConfig configuration object
	 * @throws Exception
	 */
	public function __construct( $namespace, $dbkey, stdClass $config ) {
		if ( $namespace !== $config->namespace ) {
			throw new Exception( 'Namespace does not match config' );
		}
		parent::__construct( $namespace, $dbkey );
		$this->config = $config;
	}

	public function getConfig() {
		return $this->config;
	}
}
