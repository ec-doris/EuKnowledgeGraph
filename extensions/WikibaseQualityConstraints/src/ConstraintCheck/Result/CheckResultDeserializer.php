<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Result;

use DataValues\TimeValue;
use Wikibase\DataModel\Entity\EntityIdParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursorDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageDeserializer;
use WikibaseQuality\ConstraintReport\ConstraintDeserializer;

/**
 * A deserializer for {@link CheckResult}s.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class CheckResultDeserializer {

	/**
	 * @var ConstraintDeserializer
	 */
	private $constraintDeserializer;

	/**
	 * @var ContextCursorDeserializer
	 */
	private $contextCursorDeserializer;

	/**
	 * @var ViolationMessageDeserializer
	 */
	private $violationMessageDeserializer;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	public function __construct(
		ConstraintDeserializer $constraintDeserializer,
		ContextCursorDeserializer $contextCursorDeserializer,
		ViolationMessageDeserializer $violationMessageDeserializer,
		EntityIdParser $entityIdParser
	) {
		$this->constraintDeserializer = $constraintDeserializer;
		$this->contextCursorDeserializer = $contextCursorDeserializer;
		$this->violationMessageDeserializer = $violationMessageDeserializer;
		$this->entityIdParser = $entityIdParser;
	}

	/**
	 * @param array $serialization
	 * @return CheckResult
	 */
	public function deserialize( array $serialization ) {
		$contextCursor = $this->contextCursorDeserializer->deserialize(
			$serialization[CheckResultSerializer::KEY_CONTEXT_CURSOR]
		);

		if ( array_key_exists( CheckResultSerializer::KEY_NULL_RESULT, $serialization ) ) {
			$result = new NullResult( $contextCursor );
			$cachingMetadata = CachingMetadata::fresh();
		} else {
			$constraint = $this->constraintDeserializer->deserialize(
				$serialization[CheckResultSerializer::KEY_CONSTRAINT]
			);

			$parameters = []; // serialization of parameters not supported yet

			$status = $serialization[CheckResultSerializer::KEY_CHECK_RESULT_STATUS];

			$violationMessage = $this->getViolationMessageFromSerialization( $serialization );

			$result = new CheckResult(
				$contextCursor,
				$constraint,
				$parameters,
				$status,
				$violationMessage
			);

			$cachingMetadata = $this->deserializeCachingMetadata(
				$serialization[CheckResultSerializer::KEY_CACHING_METADATA]
			);
		}

		$dependencyMetadata = $this->getDependencyMetadataFromSerialization( $serialization );

		return $result->withMetadata(
			Metadata::merge( [
				Metadata::ofCachingMetadata( $cachingMetadata ),
				Metadata::ofDependencyMetadata( $dependencyMetadata ),
			] )
		);
	}

	/**
	 * @param array $serialization
	 * @return null|ViolationMessage
	 */
	private function getViolationMessageFromSerialization( array $serialization ) {
		if ( array_key_exists( CheckResultSerializer::KEY_VIOLATION_MESSAGE, $serialization ) ) {
			return $this->violationMessageDeserializer->deserialize(
				$serialization[CheckResultSerializer::KEY_VIOLATION_MESSAGE]
			);
		} else {
			return null;
		}
	}

	/**
	 * @param array $serialization
	 * @return DependencyMetadata
	 */
	private function getDependencyMetadataFromSerialization( array $serialization ) {
		if ( array_key_exists( CheckResultSerializer::KEY_DEPENDENCY_METADATA, $serialization ) ) {
			return $this->deserializeDependencyMetadata(
				$serialization[CheckResultSerializer::KEY_DEPENDENCY_METADATA]
			);
		} else {
			return DependencyMetadata::blank();
		}
	}

	/**
	 * @param array $serialization
	 * @return CachingMetadata
	 */
	private function deserializeCachingMetadata( array $serialization ) {
		if (
			array_key_exists(
				CheckResultSerializer::KEY_CACHING_METADATA_MAX_AGE,
				$serialization
			)
		) {
			return CachingMetadata::ofMaximumAgeInSeconds(
				$serialization[CheckResultSerializer::KEY_CACHING_METADATA_MAX_AGE]
			);
		} else {
			return CachingMetadata::fresh();
		}
	}

	/**
	 * @param array $serialization
	 * @return DependencyMetadata
	 */
	private function deserializeDependencyMetadata( array $serialization ) {
		if (
			array_key_exists(
				CheckResultSerializer::KEY_DEPENDENCY_METADATA_FUTURE_TIME,
				$serialization
			)
		) {
			$futureTime = TimeValue::newFromArray(
				$serialization[CheckResultSerializer::KEY_DEPENDENCY_METADATA_FUTURE_TIME]
			);
			$futureTimeDependencyMetadata = DependencyMetadata::ofFutureTime( $futureTime );
		} else {
			$futureTimeDependencyMetadata = DependencyMetadata::blank();
		}

		$dependencyMetadata = array_reduce(
			$serialization[CheckResultSerializer::KEY_DEPENDENCY_METADATA_ENTITY_IDS],
			function ( DependencyMetadata $metadata, $entityIdSerialization ) {
				$entityId = $this->entityIdParser->parse( $entityIdSerialization );

				return DependencyMetadata::merge( [
					$metadata,
					DependencyMetadata::ofEntityId( $entityId )
				] );
			},
			$futureTimeDependencyMetadata
		);

		return $dependencyMetadata;
	}

}
