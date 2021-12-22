<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use HashConfig;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
trait DefaultConfig {

	/**
	 * @var HashConfig
	 */
	private $defaultConfig;

	public function getDefaultConfig() {
		if ( $this->defaultConfig === null ) {
			$this->defaultConfig = new HashConfig();
			$extensionJsonFile = __DIR__ . '/../../extension.json';
			$extensionJsonText = file_get_contents( $extensionJsonFile );
			$extensionJson = json_decode( $extensionJsonText, /* assoc = */ true );
			foreach ( $extensionJson['config'] as $key => $value ) {
				$this->defaultConfig->set( $key, $value['value'] );
			}
			// reduce some limits to make tests run faster
			$this->defaultConfig->set( 'WBQualityConstraintsTypeCheckMaxEntities', 10 );
			// never query remote servers
			$this->defaultConfig->set( 'WBQualityConstraintsSparqlEndpoint', 'http://localhost:65536/' );
		}

		return $this->defaultConfig;
	}

}
