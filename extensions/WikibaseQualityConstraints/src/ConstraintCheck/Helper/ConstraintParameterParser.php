<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use DataValues\DataValue;
use DataValues\MonolingualTextValue;
use DataValues\MultilingualTextValue;
use DataValues\StringValue;
use DataValues\UnboundedQuantityValue;
use LogicException;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Deserializers\SnakDeserializer;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\Role;

/**
 * Helper for parsing constraint parameters
 * that were imported from constraint statements.
 *
 * All public methods of this class expect constraint parameters
 * (see {@link \WikibaseQuality\ConstraintReport\Constraint::getConstraintParameters()})
 * and return parameter objects or throw {@link ConstraintParameterException}s.
 * The results are used by the checkers,
 * which may include rendering them into violation messages.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class ConstraintParameterParser {

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var SnakDeserializer
	 */
	private $snakDeserializer;

	/**
	 * @var string
	 */
	private $unitItemConceptBaseUri;

	/**
	 * @param Config $config
	 *   contains entity IDs used in constraint parameters (constraint statement qualifiers)
	 * @param DeserializerFactory $factory
	 *   used to parse constraint statement qualifiers into constraint parameters
	 * @param string $unitItemConceptBaseUri
	 *   concept base URI of items used for units
	 */
	public function __construct(
		Config $config,
		DeserializerFactory $factory,
		string $unitItemConceptBaseUri
	) {
		$this->config = $config;
		$this->snakDeserializer = $factory->newSnakDeserializer();
		$this->unitItemConceptBaseUri = $unitItemConceptBaseUri;
	}

	/**
	 * Check if any errors are recorded in the constraint parameters.
	 * @param array $parameters
	 * @throws ConstraintParameterException
	 */
	public function checkError( array $parameters ) {
		if ( array_key_exists( '@error', $parameters ) ) {
			$error = $parameters['@error'];
			if ( array_key_exists( 'toolong', $error ) && $error['toolong'] ) {
				$msg = 'wbqc-violation-message-parameters-error-toolong';
			} else {
				$msg = 'wbqc-violation-message-parameters-error-unknown';
			}
			throw new ConstraintParameterException( new ViolationMessage( $msg ) );
		}
	}

	/**
	 * Require that $parameters contains exactly one $parameterId parameter.
	 * @param array $parameters
	 * @param string $parameterId
	 * @throws ConstraintParameterException
	 */
	private function requireSingleParameter( array $parameters, $parameterId ) {
		if ( count( $parameters[$parameterId] ) !== 1 ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-single' ) )
					->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}
	}

	/**
	 * Require that $snak is a {@link PropertyValueSnak}.
	 * @param Snak $snak
	 * @param string $parameterId
	 * @return void
	 * @throws ConstraintParameterException
	 */
	private function requireValueParameter( Snak $snak, $parameterId ) {
		if ( !( $snak instanceof PropertyValueSnak ) ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-value' ) )
					->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}
	}

	/**
	 * Parse a single entity ID parameter.
	 * @param array $snakSerialization
	 * @param string $parameterId
	 * @throws ConstraintParameterException
	 * @return EntityId
	 */
	private function parseEntityIdParameter( array $snakSerialization, $parameterId ) {
		$snak = $this->snakDeserializer->deserialize( $snakSerialization );
		$this->requireValueParameter( $snak, $parameterId );
		$value = $snak->getDataValue();
		if ( $value instanceof EntityIdValue ) {
			return $value->getEntityId();
		} else {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-entity' ) )
					->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
					->withDataValue( $value, Role::CONSTRAINT_PARAMETER_VALUE )
			);
		}
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\ConstraintReport\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string[] class entity ID serializations
	 */
	public function parseClassParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$classId = $this->config->get( 'WBQualityConstraintsClassId' );
		if ( !array_key_exists( $classId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-needed' ) )
					->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
					->withEntityId( new PropertyId( $classId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}

		$classes = [];
		foreach ( $constraintParameters[$classId] as $class ) {
			$classes[] = $this->parseEntityIdParameter( $class, $classId )->getSerialization();
		}
		return $classes;
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string 'instance', 'subclass', or 'instanceOrSubclass'
	 */
	public function parseRelationParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$relationId = $this->config->get( 'WBQualityConstraintsRelationId' );
		if ( !array_key_exists( $relationId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-needed' ) )
					->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
					->withEntityId( new PropertyId( $relationId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}

		$this->requireSingleParameter( $constraintParameters, $relationId );
		$relationEntityId = $this->parseEntityIdParameter( $constraintParameters[$relationId][0], $relationId );
		$instanceId = $this->config->get( 'WBQualityConstraintsInstanceOfRelationId' );
		$subclassId = $this->config->get( 'WBQualityConstraintsSubclassOfRelationId' );
		$instanceOrSubclassId = $this->config->get( 'WBQualityConstraintsInstanceOrSubclassOfRelationId' );
		switch ( $relationEntityId ) {
			case $instanceId:
				return 'instance';
			case $subclassId:
				return 'subclass';
			case $instanceOrSubclassId:
				return 'instanceOrSubclass';
			default:
				throw new ConstraintParameterException(
					( new ViolationMessage( 'wbqc-violation-message-parameter-oneof' ) )
						->withEntityId( new PropertyId( $relationId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
						->withEntityIdList(
							[
								new ItemId( $instanceId ),
								new ItemId( $subclassId ),
								new ItemId( $instanceOrSubclassId ),
							],
							Role::CONSTRAINT_PARAMETER_VALUE
						)
				);
		}
	}

	/**
	 * Parse a single property ID parameter.
	 * @param array $snakSerialization
	 * @param string $parameterId used in error messages
	 * @throws ConstraintParameterException
	 * @return PropertyId
	 */
	private function parsePropertyIdParameter( array $snakSerialization, $parameterId ) {
		$snak = $this->snakDeserializer->deserialize( $snakSerialization );
		$this->requireValueParameter( $snak, $parameterId );
		$value = $snak->getDataValue();
		if ( $value instanceof EntityIdValue ) {
			$id = $value->getEntityId();
			if ( $id instanceof PropertyId ) {
				return $id;
			}
		}
		throw new ConstraintParameterException(
			( new ViolationMessage( 'wbqc-violation-message-parameter-property' ) )
				->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
				->withDataValue( $value, Role::CONSTRAINT_PARAMETER_VALUE )
		);
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 *
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return PropertyId
	 */
	public function parsePropertyParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$propertyId = $this->config->get( 'WBQualityConstraintsPropertyId' );
		if ( !array_key_exists( $propertyId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-needed' ) )
					->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
					->withEntityId( new PropertyId( $propertyId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}

		$this->requireSingleParameter( $constraintParameters, $propertyId );
		return $this->parsePropertyIdParameter( $constraintParameters[$propertyId][0], $propertyId );
	}

	private function parseItemIdParameter( PropertyValueSnak $snak, $parameterId ) {
		$dataValue = $snak->getDataValue();
		if ( $dataValue instanceof EntityIdValue ) {
			$entityId = $dataValue->getEntityId();
			if ( $entityId instanceof ItemId ) {
				return ItemIdSnakValue::fromItemId( $entityId );
			}
		}
		throw new ConstraintParameterException(
			( new ViolationMessage( 'wbqc-violation-message-parameter-item' ) )
				->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
				->withDataValue( $dataValue, Role::CONSTRAINT_PARAMETER_VALUE )
		);
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @param bool $required whether the parameter is required (error if absent) or not ([] if absent)
	 * @param string|null $parameterId the property ID to use, defaults to 'qualifier of property constraint'
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return ItemIdSnakValue[] array of values
	 */
	public function parseItemsParameter(
		array $constraintParameters,
		$constraintTypeItemId,
		$required,
		$parameterId = null
	) {
		$this->checkError( $constraintParameters );
		if ( $parameterId === null ) {
			$parameterId = $this->config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
		}
		if ( !array_key_exists( $parameterId, $constraintParameters ) ) {
			if ( $required ) {
				throw new ConstraintParameterException(
					( new ViolationMessage( 'wbqc-violation-message-parameter-needed' ) )
						->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
						->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
				);
			} else {
				return [];
			}
		}

		$values = [];
		foreach ( $constraintParameters[$parameterId] as $parameter ) {
			$snak = $this->snakDeserializer->deserialize( $parameter );
			switch ( true ) {
				case $snak instanceof PropertyValueSnak:
					$values[] = $this->parseItemIdParameter( $snak, $parameterId );
					break;
				case $snak instanceof PropertySomeValueSnak:
					$values[] = ItemIdSnakValue::someValue();
					break;
				case $snak instanceof PropertyNoValueSnak:
					$values[] = ItemIdSnakValue::noValue();
					break;
			}
		}
		return $values;
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return PropertyId[]
	 */
	public function parsePropertiesParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$propertyId = $this->config->get( 'WBQualityConstraintsPropertyId' );
		if ( !array_key_exists( $propertyId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-needed' ) )
					->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
					->withEntityId( new PropertyId( $propertyId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}

		$parameters = $constraintParameters[$propertyId];
		if ( count( $parameters ) === 1 &&
			$this->snakDeserializer->deserialize( $parameters[0] ) instanceof PropertyNoValueSnak
		) {
			return [];
		}

		$properties = [];
		foreach ( $parameters as $parameter ) {
			$properties[] = $this->parsePropertyIdParameter( $parameter, $propertyId );
		}
		return $properties;
	}

	/**
	 * @param array $snakSerialization
	 * @param string $parameterId
	 * @throws ConstraintParameterException
	 * @return DataValue|null
	 */
	private function parseValueOrNoValueParameter( array $snakSerialization, $parameterId ) {
		$snak = $this->snakDeserializer->deserialize( $snakSerialization );
		if ( $snak instanceof PropertyValueSnak ) {
			return $snak->getDataValue();
		} elseif ( $snak instanceof PropertyNoValueSnak ) {
			return null;
		} else {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-value-or-novalue' ) )
					->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}
	}

	/**
	 * @param array $snakSerialization
	 * @param string $parameterId
	 * @return DataValue|null
	 */
	private function parseValueOrNoValueOrNowParameter( array $snakSerialization, $parameterId ) {
		try {
			return $this->parseValueOrNoValueParameter( $snakSerialization, $parameterId );
		} catch ( ConstraintParameterException $e ) {
			// unknown value means “now”
			return new NowValue();
		}
	}

	/**
	 * Checks whether there is exactly one non-null quantity with the given unit.
	 * @param ?DataValue $min
	 * @param ?DataValue $max
	 * @param string $unit
	 * @return bool
	 */
	private function exactlyOneQuantityWithUnit( ?DataValue $min, ?DataValue $max, $unit ) {
		if ( !( $min instanceof UnboundedQuantityValue ) ||
			!( $max instanceof UnboundedQuantityValue )
		) {
			return false;
		}

		return ( $min->getUnit() === $unit ) !== ( $max->getUnit() === $unit );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $minimumId
	 * @param string $maximumId
	 * @param string $constraintTypeItemId used in error messages
	 * @param string $type 'quantity' or 'time' (can be data type or data value type)
	 *
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return DataValue[] if the parameter is invalid or missing
	 */
	private function parseRangeParameter( array $constraintParameters, $minimumId, $maximumId, $constraintTypeItemId, $type ) {
		$this->checkError( $constraintParameters );
		if ( !array_key_exists( $minimumId, $constraintParameters ) ||
			!array_key_exists( $maximumId, $constraintParameters )
		) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-range-parameters-needed' ) )
					->withDataValueType( $type )
					->withEntityId( new PropertyId( $minimumId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
					->withEntityId( new PropertyId( $maximumId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
					->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
			);
		}

		$this->requireSingleParameter( $constraintParameters, $minimumId );
		$this->requireSingleParameter( $constraintParameters, $maximumId );
		$parseFunction = $type === 'time' ? 'parseValueOrNoValueOrNowParameter' : 'parseValueOrNoValueParameter';
		$min = $this->$parseFunction( $constraintParameters[$minimumId][0], $minimumId );
		$max = $this->$parseFunction( $constraintParameters[$maximumId][0], $maximumId );

		$yearUnit = $this->config->get( 'WBQualityConstraintsYearUnit' );
		if ( $this->exactlyOneQuantityWithUnit( $min, $max, $yearUnit ) ) {
			throw new ConstraintParameterException(
				new ViolationMessage( 'wbqc-violation-message-range-parameters-one-year' )
			);
		}
		if ( $min === null && $max === null ||
			$min !== null && $max !== null && $min->equals( $max ) ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-range-parameters-same' ) )
					->withEntityId( new PropertyId( $minimumId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
					->withEntityId( new PropertyId( $maximumId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}

		return [ $min, $max ];
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 *
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return DataValue[] a pair of two data values, either of which may be null to signify an open range
	 */
	public function parseQuantityRangeParameter( array $constraintParameters, $constraintTypeItemId ) {
		return $this->parseRangeParameter(
			$constraintParameters,
			$this->config->get( 'WBQualityConstraintsMinimumQuantityId' ),
			$this->config->get( 'WBQualityConstraintsMaximumQuantityId' ),
			$constraintTypeItemId,
			'quantity'
		);
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 *
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return DataValue[] a pair of two data values, either of which may be null to signify an open range
	 */
	public function parseTimeRangeParameter( array $constraintParameters, $constraintTypeItemId ) {
		return $this->parseRangeParameter(
			$constraintParameters,
			$this->config->get( 'WBQualityConstraintsMinimumDateId' ),
			$this->config->get( 'WBQualityConstraintsMaximumDateId' ),
			$constraintTypeItemId,
			'time'
		);
	}

	/**
	 * Parse a single string parameter.
	 * @param array $snakSerialization
	 * @param string $parameterId
	 * @throws ConstraintParameterException
	 * @return string
	 */
	private function parseStringParameter( array $snakSerialization, $parameterId ) {
		$snak = $this->snakDeserializer->deserialize( $snakSerialization );
		$this->requireValueParameter( $snak, $parameterId );
		$value = $snak->getDataValue();
		if ( $value instanceof StringValue ) {
			return $value->getValue();
		} else {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-string' ) )
					->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
					->withDataValue( $value, Role::CONSTRAINT_PARAMETER_VALUE )
			);
		}
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string
	 */
	public function parseNamespaceParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$namespaceId = $this->config->get( 'WBQualityConstraintsNamespaceId' );
		if ( !array_key_exists( $namespaceId, $constraintParameters ) ) {
			return '';
		}

		$this->requireSingleParameter( $constraintParameters, $namespaceId );
		return $this->parseStringParameter( $constraintParameters[$namespaceId][0], $namespaceId );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string
	 */
	public function parseFormatParameter( array $constraintParameters, $constraintTypeItemId ) {
		$this->checkError( $constraintParameters );
		$formatId = $this->config->get( 'WBQualityConstraintsFormatAsARegularExpressionId' );
		if ( !array_key_exists( $formatId, $constraintParameters ) ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-needed' ) )
					->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
					->withEntityId( new PropertyId( $formatId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}

		$this->requireSingleParameter( $constraintParameters, $formatId );
		return $this->parseStringParameter( $constraintParameters[$formatId][0], $formatId );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return EntityId[]
	 */
	public function parseExceptionParameter( array $constraintParameters ) {
		$this->checkError( $constraintParameters );
		$exceptionId = $this->config->get( 'WBQualityConstraintsExceptionToConstraintId' );
		if ( !array_key_exists( $exceptionId, $constraintParameters ) ) {
			return [];
		}

		return array_map(
			function( $snakSerialization ) use ( $exceptionId ) {
				return $this->parseEntityIdParameter( $snakSerialization, $exceptionId );
			},
			$constraintParameters[$exceptionId]
		);
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return string|null 'mandatory', 'suggestion' or null
	 */
	public function parseConstraintStatusParameter( array $constraintParameters ) {
		$this->checkError( $constraintParameters );
		$constraintStatusId = $this->config->get( 'WBQualityConstraintsConstraintStatusId' );
		if ( !array_key_exists( $constraintStatusId, $constraintParameters ) ) {
			return null;
		}

		$mandatoryId = $this->config->get( 'WBQualityConstraintsMandatoryConstraintId' );
		$supportedStatuses = [ new ItemId( $mandatoryId ) ];
		if ( $this->config->get( 'WBQualityConstraintsEnableSuggestionConstraintStatus' ) ) {
			$suggestionId = $this->config->get( 'WBQualityConstraintsSuggestionConstraintId' );
			$supportedStatuses[] = new ItemId( $suggestionId );
		} else {
			$suggestionId = null;
		}

		$this->requireSingleParameter( $constraintParameters, $constraintStatusId );
		$snak = $this->snakDeserializer->deserialize( $constraintParameters[$constraintStatusId][0] );
		$this->requireValueParameter( $snak, $constraintStatusId );
		'@phan-var \Wikibase\DataModel\Snak\PropertyValueSnak $snak';
		$dataValue = $snak->getDataValue();
		'@phan-var EntityIdValue $dataValue';
		$entityId = $dataValue->getEntityId();
		$statusId = $entityId->getSerialization();

		if ( $statusId === $mandatoryId ) {
			return 'mandatory';
		} elseif ( $statusId === $suggestionId ) {
			return 'suggestion';
		} else {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-oneof' ) )
					->withEntityId( new PropertyId( $constraintStatusId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
					->withEntityIdList(
						$supportedStatuses,
						Role::CONSTRAINT_PARAMETER_VALUE
					)
			);
		}
	}

	/**
	 * Require that $dataValue is a {@link MonolingualTextValue}.
	 * @param DataValue $dataValue
	 * @param string $parameterId
	 * @return void
	 * @throws ConstraintParameterException
	 */
	private function requireMonolingualTextParameter( DataValue $dataValue, $parameterId ) {
		if ( !( $dataValue instanceof MonolingualTextValue ) ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-monolingualtext' ) )
					->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
					->withDataValue( $dataValue, Role::CONSTRAINT_PARAMETER_VALUE )
			);
		}
	}

	/**
	 * Parse a series of monolingual text snaks (serialized) into a map from language code to string.
	 *
	 * @param array $snakSerializations
	 * @param string $parameterId
	 * @throws ConstraintParameterException if invalid snaks are found or a language has multiple texts
	 * @return MultilingualTextValue
	 */
	private function parseMultilingualTextParameter( array $snakSerializations, $parameterId ) {
		$result = [];

		foreach ( $snakSerializations as $snakSerialization ) {
			$snak = $this->snakDeserializer->deserialize( $snakSerialization );
			$this->requireValueParameter( $snak, $parameterId );

			$value = $snak->getDataValue();
			$this->requireMonolingualTextParameter( $value, $parameterId );
			/** @var MonolingualTextValue $value */
			'@phan-var MonolingualTextValue $value';

			$code = $value->getLanguageCode();
			if ( array_key_exists( $code, $result ) ) {
				throw new ConstraintParameterException(
					( new ViolationMessage( 'wbqc-violation-message-parameter-single-per-language' ) )
						->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
						->withLanguage( $code )
				);
			}

			$result[$code] = $value;
		}

		return new MultilingualTextValue( $result );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return MultilingualTextValue
	 */
	public function parseSyntaxClarificationParameter( array $constraintParameters ) {
		$syntaxClarificationId = $this->config->get( 'WBQualityConstraintsSyntaxClarificationId' );

		if ( !array_key_exists( $syntaxClarificationId, $constraintParameters ) ) {
			return new MultilingualTextValue( [] );
		}

		$syntaxClarifications = $this->parseMultilingualTextParameter(
			$constraintParameters[$syntaxClarificationId],
			$syntaxClarificationId
		);

		return $syntaxClarifications;
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @param string[]|null $validScopes a list of Context::TYPE_* constants which are valid where this parameter appears.
	 * If this is not null and one of the specified scopes is not in this list, a ConstraintParameterException is thrown.
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return string[]|null Context::TYPE_* constants
	 */
	public function parseConstraintScopeParameter( array $constraintParameters, $constraintTypeItemId, array $validScopes = null ) {
		$contextTypes = [];
		$parameterId = $this->config->get( 'WBQualityConstraintsConstraintScopeId' );
		$items = $this->parseItemsParameter(
			$constraintParameters,
			$constraintTypeItemId,
			false,
			$parameterId
		);

		if ( $items === [] ) {
			return null;
		}

		foreach ( $items as $item ) {
			$contextTypes[] = $this->parseContextTypeItem( $item, 'constraint scope', $parameterId );
		}

		if ( $validScopes !== null ) {
			$invalidScopes = array_diff( $contextTypes, $validScopes );
			if ( $invalidScopes !== [] ) {
				$invalidScope = array_pop( $invalidScopes );
				throw new ConstraintParameterException(
					( new ViolationMessage( 'wbqc-violation-message-invalid-scope' ) )
						->withConstraintScope( $invalidScope, Role::CONSTRAINT_PARAMETER_VALUE )
						->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
						->withConstraintScopeList( $validScopes, Role::CONSTRAINT_PARAMETER_VALUE )
				);
			}
		}

		return $contextTypes;
	}

	/**
	 * Turn an item ID into a full unit string (using the concept URI).
	 *
	 * @param ItemId $unitId
	 * @return string unit
	 */
	private function parseUnitParameter( ItemId $unitId ) {
		return $this->unitItemConceptBaseUri . $unitId->getSerialization();
	}

	/**
	 * Turn an ItemIdSnakValue into a single unit parameter.
	 *
	 * @param ItemIdSnakValue $item
	 * @return UnitsParameter
	 * @throws ConstraintParameterException
	 */
	private function parseUnitItem( ItemIdSnakValue $item ) {
		switch ( true ) {
			case $item->isValue():
				$unit = $this->parseUnitParameter( $item->getItemId() );
				return new UnitsParameter(
					[ $item->getItemId() ],
					[ UnboundedQuantityValue::newFromNumber( 1, $unit ) ],
					false
				);
			case $item->isSomeValue():
				$qualifierId = $this->config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );
				throw new ConstraintParameterException(
					( new ViolationMessage( 'wbqc-violation-message-parameter-value-or-novalue' ) )
						->withEntityId( new PropertyId( $qualifierId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
				);
			case $item->isNoValue():
				return new UnitsParameter( [], [], true );
		}
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return UnitsParameter
	 */
	public function parseUnitsParameter( array $constraintParameters, $constraintTypeItemId ) {
		$items = $this->parseItemsParameter( $constraintParameters, $constraintTypeItemId, true );
		$unitItems = [];
		$unitQuantities = [];
		$unitlessAllowed = false;

		foreach ( $items as $item ) {
			$unit = $this->parseUnitItem( $item );
			$unitItems = array_merge( $unitItems, $unit->getUnitItemIds() );
			$unitQuantities = array_merge( $unitQuantities, $unit->getUnitQuantities() );
			$unitlessAllowed = $unitlessAllowed || $unit->getUnitlessAllowed();
		}

		if ( $unitQuantities === [] && !$unitlessAllowed ) {
			throw new LogicException(
				'The "units" parameter is required, and yet we seem to be missing any allowed unit'
			);
		}

		return new UnitsParameter( $unitItems, $unitQuantities, $unitlessAllowed );
	}

	/**
	 * Turn an ItemIdSnakValue into a single entity type parameter.
	 *
	 * @param ItemIdSnakValue $item
	 * @return EntityTypesParameter
	 * @throws ConstraintParameterException
	 */
	private function parseEntityTypeItem( ItemIdSnakValue $item ) {
		$parameterId = $this->config->get( 'WBQualityConstraintsQualifierOfPropertyConstraintId' );

		if ( !$item->isValue() ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-value' ) )
					->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}

		$itemId = $item->getItemId();
		switch ( $itemId->getSerialization() ) {
			case $this->config->get( 'WBQualityConstraintsWikibaseItemId' ):
				$entityType = 'item';
				break;
			case $this->config->get( 'WBQualityConstraintsWikibasePropertyId' ):
				$entityType = 'property';
				break;
			case $this->config->get( 'WBQualityConstraintsWikibaseLexemeId' ):
				$entityType = 'lexeme';
				break;
			case $this->config->get( 'WBQualityConstraintsWikibaseFormId' ):
				$entityType = 'form';
				break;
			case $this->config->get( 'WBQualityConstraintsWikibaseSenseId' ):
				$entityType = 'sense';
				break;
			case $this->config->get( 'WBQualityConstraintsWikibaseMediaInfoId' ):
				$entityType = 'mediainfo';
				break;
			default:
				$allowed = [
					new ItemId( $this->config->get( 'WBQualityConstraintsWikibaseItemId' ) ),
					new ItemId( $this->config->get( 'WBQualityConstraintsWikibasePropertyId' ) ),
					new ItemId( $this->config->get( 'WBQualityConstraintsWikibaseLexemeId' ) ),
					new ItemId( $this->config->get( 'WBQualityConstraintsWikibaseFormId' ) ),
					new ItemId( $this->config->get( 'WBQualityConstraintsWikibaseSenseId' ) ),
					new ItemId( $this->config->get( 'WBQualityConstraintsWikibaseMediaInfoId' ) ),
				];
				throw new ConstraintParameterException(
					( new ViolationMessage( 'wbqc-violation-message-parameter-oneof' ) )
						->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
						->withEntityIdList( $allowed, Role::CONSTRAINT_PARAMETER_VALUE )
				);
		}

		return new EntityTypesParameter( [ $entityType ], [ $itemId ] );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return EntityTypesParameter
	 */
	public function parseEntityTypesParameter( array $constraintParameters, $constraintTypeItemId ) {
		$entityTypes = [];
		$entityTypeItemIds = [];
		$items = $this->parseItemsParameter( $constraintParameters, $constraintTypeItemId, true );

		foreach ( $items as $item ) {
			$entityType = $this->parseEntityTypeItem( $item );
			$entityTypes = array_merge( $entityTypes, $entityType->getEntityTypes() );
			$entityTypeItemIds = array_merge( $entityTypeItemIds, $entityType->getEntityTypeItemIds() );
		}

		if ( empty( $entityTypes ) ) {
			// @codeCoverageIgnoreStart
			throw new LogicException(
				'The "entity types" parameter is required, ' .
				'and yet we seem to be missing any allowed entity type'
			);
			// @codeCoverageIgnoreEnd
		}

		return new EntityTypesParameter( $entityTypes, $entityTypeItemIds );
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @throws ConstraintParameterException if the parameter is invalid
	 * @return PropertyId[]
	 */
	public function parseSeparatorsParameter( array $constraintParameters ) {
		$separatorId = $this->config->get( 'WBQualityConstraintsSeparatorId' );

		if ( !array_key_exists( $separatorId, $constraintParameters ) ) {
			return [];
		}

		$parameters = $constraintParameters[$separatorId];
		$separators = [];

		foreach ( $parameters as $parameter ) {
			$separators[] = $this->parsePropertyIdParameter( $parameter, $separatorId );
		}

		return $separators;
	}

	/**
	 * Turn an ItemIdSnakValue into a single context type parameter.
	 *
	 * @param ItemIdSnakValue $item
	 * @param string $use 'constraint scope' or 'property scope'
	 * @param string $parameterId used in error messages
	 * @return string one of the Context::TYPE_* constants
	 * @throws ConstraintParameterException
	 */
	private function parseContextTypeItem( ItemIdSnakValue $item, $use, $parameterId ) {
		if ( !$item->isValue() ) {
			throw new ConstraintParameterException(
				( new ViolationMessage( 'wbqc-violation-message-parameter-value' ) )
					->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
			);
		}

		if ( $use === 'constraint scope' ) {
			$mainSnakId = $this->config->get( 'WBQualityConstraintsConstraintCheckedOnMainValueId' );
			$qualifiersId = $this->config->get( 'WBQualityConstraintsConstraintCheckedOnQualifiersId' );
			$referencesId = $this->config->get( 'WBQualityConstraintsConstraintCheckedOnReferencesId' );
		} else {
			$mainSnakId = $this->config->get( 'WBQualityConstraintsAsMainValueId' );
			$qualifiersId = $this->config->get( 'WBQualityConstraintsAsQualifiersId' );
			$referencesId = $this->config->get( 'WBQualityConstraintsAsReferencesId' );
		}

		$itemId = $item->getItemId();
		switch ( $itemId->getSerialization() ) {
			case $mainSnakId:
				return Context::TYPE_STATEMENT;
			case $qualifiersId:
				return Context::TYPE_QUALIFIER;
			case $referencesId:
				return Context::TYPE_REFERENCE;
			default:
				$allowed = [ $mainSnakId, $qualifiersId, $referencesId ];
				throw new ConstraintParameterException(
					( new ViolationMessage( 'wbqc-violation-message-parameter-oneof' ) )
						->withEntityId( new PropertyId( $parameterId ), Role::CONSTRAINT_PARAMETER_PROPERTY )
						->withEntityIdList( $allowed, Role::CONSTRAINT_PARAMETER_VALUE )
				);
		}
	}

	/**
	 * @param array $constraintParameters see {@link \WikibaseQuality\Constraint::getConstraintParameters()}
	 * @param string $constraintTypeItemId used in error messages
	 * @throws ConstraintParameterException if the parameter is invalid or missing
	 * @return string[] list of Context::TYPE_* constants
	 */
	public function parsePropertyScopeParameter( array $constraintParameters, $constraintTypeItemId ) {
		$contextTypes = [];
		$parameterId = $this->config->get( 'WBQualityConstraintsPropertyScopeId' );
		$items = $this->parseItemsParameter(
			$constraintParameters,
			$constraintTypeItemId,
			true,
			$parameterId
		);

		foreach ( $items as $item ) {
			$contextTypes[] = $this->parseContextTypeItem( $item, 'property scope', $parameterId );
		}

		if ( empty( $contextTypes ) ) {
			// @codeCoverageIgnoreStart
			throw new LogicException(
				'The "property scope" parameter is required, ' .
				'and yet we seem to be missing any allowed scope'
			);
			// @codeCoverageIgnoreEnd
		}

		return $contextTypes;
	}

}
