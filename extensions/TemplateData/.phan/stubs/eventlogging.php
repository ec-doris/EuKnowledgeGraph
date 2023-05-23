<?php

namespace MediaWiki\Extension\EventLogging;

class EventLogging {

	/**
	 * @param string $schemaName
	 * @param int $revId
	 * @param array $event
	 * @param int $options
	 * @return bool
	 */
	public static function logEvent( $schemaName, $revId, $event, $options = 0 ) {
		return true;
	}

}
