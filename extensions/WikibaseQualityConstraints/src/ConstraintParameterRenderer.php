<?php
namespace WikibaseQuality\ConstraintReport;

use Config;
use DataValues\DataValue;
use MessageLocalizer;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

/**
 * Used to format the constraint values for output.
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConstraintParameterRenderer {

	/**
	 * Maximum number of displayed values for parameters with multiple ones.
	 *
	 * @var int
	 */
	const MAX_PARAMETER_ARRAY_LENGTH = 10;

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdLabelFormatter;

	/**
	 * @var ValueFormatter
	 */
	private $dataValueFormatter;

	/**
	 * @var MessageLocalizer
	 */
	private $messageLocalizer;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param EntityIdFormatter $entityIdFormatter should return HTML
	 * @param ValueFormatter $dataValueFormatter should return HTML
	 * @param MessageLocalizer $messageLocalizer
	 * @param Config $config used to look up item IDs of constraint scopes (Context::TYPE_* constants)
	 */
	public function __construct(
		EntityIdFormatter $entityIdFormatter,
		ValueFormatter $dataValueFormatter,
		MessageLocalizer $messageLocalizer,
		Config $config
	) {
		$this->entityIdLabelFormatter = $entityIdFormatter;
		$this->dataValueFormatter = $dataValueFormatter;
		$this->messageLocalizer = $messageLocalizer;
		$this->config = $config;
	}

	/**
	 * Formats parameter values of constraints.
	 *
	 * @param string|ItemId|PropertyId|DataValue $value
	 *
	 * @return string HTML
	 */
	public function formatValue( $value ) {
		if ( is_string( $value ) ) {
			// Cases like 'Format' 'pattern' or 'minimum'/'maximum' values, which we have stored as
			// strings
			return htmlspecialchars( $value );
		} elseif ( $value instanceof EntityId ) {
			// Cases like 'Conflicts with' 'property', to which we can link
			return $this->formatEntityId( $value );
		} elseif ( $value instanceof ItemIdSnakValue ) {
			// Cases like EntityId but can also be somevalue or novalue
			return $this->formatItemIdSnakValue( $value );
		} else {
			// Cases where we format a DataValue
			return $this->formatDataValue( $value );
		}
	}

	/**
	 * Formats constraint parameters.
	 *
	 * @param (string|ItemId|PropertyId|DataValue)[][]|null $parameters
	 *
	 * @return string HTML
	 */
	public function formatParameters( $parameters ) {
		if ( $parameters === null || $parameters === [] ) {
			return null;
		}

		$valueFormatter = function ( $value ) {
			return $this->formatValue( $value );
		};

		$formattedParameters = [];
		foreach ( $parameters as $parameterName => $parameterValue ) {
			$formattedParameterValues = implode( ', ',
				$this->limitArrayLength( array_map( $valueFormatter, $parameterValue ) ) );
			$formattedParameters[] = sprintf( '%s: %s', $parameterName, $formattedParameterValues );
		}

		return implode( '; ', $formattedParameters );
	}

	/**
	 * Cuts an array after n values and appends dots if needed.
	 *
	 * @param string[] $array
	 *
	 * @return string[]
	 */
	private function limitArrayLength( array $array ) {
		if ( count( $array ) > self::MAX_PARAMETER_ARRAY_LENGTH ) {
			$array = array_slice( $array, 0, self::MAX_PARAMETER_ARRAY_LENGTH );
			array_push( $array, '...' );
		}

		return $array;
	}

	/**
	 * @param DataValue $value
	 * @return string HTML
	 */
	public function formatDataValue( DataValue $value ) {
		return $this->dataValueFormatter->format( $value );
	}

	/**
	 * @param EntityId $entityId
	 * @return string HTML
	 */
	public function formatEntityId( EntityId $entityId ) {
		return $this->entityIdLabelFormatter->formatEntityId( $entityId );
	}

	/**
	 * Format an {@link ItemIdSnakValue} (known value, unknown value, or no value).
	 *
	 * @param ItemIdSnakValue $value
	 * @return string HTML
	 */
	public function formatItemIdSnakValue( ItemIdSnakValue $value ) {
		switch ( true ) {
			case $value->isValue():
				return $this->formatEntityId( $value->getItemId() );
			case $value->isSomeValue():
				return $this->messageLocalizer
					->msg( 'wikibase-snakview-snaktypeselector-somevalue' )
					->escaped();
			case $value->isNoValue():
				return $this->messageLocalizer
					->msg( 'wikibase-snakview-snaktypeselector-novalue' )
					->escaped();
		}
	}

}
