<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use Config;
use DataValues\DataValue;
use InvalidArgumentException;
use Language;
use LogicException;
use Message;
use MessageLocalizer;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;

/**
 * Render a {@link ViolationMessage} into a localized string.
 *
 * Note: This class does <em>not</em> support multilingual text arguments –
 * for that, use {@link MultilingualTextViolationMessageRenderer}.
 *
 * @license GPL-2.0-or-later
 */
class ViolationMessageRenderer {

	/**
	 * @var EntityIdFormatter
	 */
	private $entityIdFormatter;

	/**
	 * @var ValueFormatter
	 */
	private $dataValueFormatter;

	/**
	 * @var MessageLocalizer
	 */
	protected $messageLocalizer;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var int
	 */
	private $maxListLength;

	/**
	 * @param EntityIdFormatter $entityIdFormatter
	 * @param ValueFormatter $dataValueFormatter
	 * @param MessageLocalizer $messageLocalizer
	 * @param Config $config
	 * @param int $maxListLength The maximum number of elements to be rendered in a list parameter.
	 * Longer lists are truncated to this length and then rendered with an ellipsis in the HMTL list.
	 */
	public function __construct(
		EntityIdFormatter $entityIdFormatter,
		ValueFormatter $dataValueFormatter,
		MessageLocalizer $messageLocalizer,
		Config $config,
		$maxListLength = 10
	) {
		$this->entityIdFormatter = $entityIdFormatter;
		$this->dataValueFormatter = $dataValueFormatter;
		$this->messageLocalizer = $messageLocalizer;
		$this->config = $config;
		$this->maxListLength = $maxListLength;
	}

	/**
	 * @param ViolationMessage $violationMessage
	 * @return string
	 */
	public function render( ViolationMessage $violationMessage ) {
		$messageKey = $violationMessage->getMessageKey();
		$paramsLists = [ [] ];
		foreach ( $violationMessage->getArguments() as $argument ) {
			$params = $this->renderArgument( $argument );
			$paramsLists[] = $params;
		}
		$allParams = call_user_func_array( 'array_merge', $paramsLists );
		return $this->messageLocalizer
			->msg( $messageKey )
			->params( $allParams )
			->escaped();
	}

	/**
	 * @param string $value HTML
	 * @param string|null $role one of the Role::* constants
	 * @return string HTML
	 */
	protected function addRole( $value, $role ) {
		if ( $role === null ) {
			return $value;
		}

		return '<span class="wbqc-role wbqc-role-' . htmlspecialchars( $role ) . '">' .
			$value .
			'</span>';
	}

	/**
	 * @param string $key message key
	 * @return string HTML
	 */
	protected function msgEscaped( $key ) {
		return $this->messageLocalizer->msg( $key )->escaped();
	}

	/**
	 * @param array $argument
	 * @return array[] params (for Message::params)
	 */
	protected function renderArgument( array $argument ) {
		$methods = [
			ViolationMessage::TYPE_ENTITY_ID => 'renderEntityId',
			ViolationMessage::TYPE_ENTITY_ID_LIST => 'renderEntityIdList',
			ViolationMessage::TYPE_ITEM_ID_SNAK_VALUE => 'renderItemIdSnakValue',
			ViolationMessage::TYPE_ITEM_ID_SNAK_VALUE_LIST => 'renderItemIdSnakValueList',
			ViolationMessage::TYPE_DATA_VALUE => 'renderDataValue',
			ViolationMessage::TYPE_DATA_VALUE_TYPE => 'renderDataValueType',
			ViolationMessage::TYPE_INLINE_CODE => 'renderInlineCode',
			ViolationMessage::TYPE_CONSTRAINT_SCOPE => 'renderConstraintScope',
			ViolationMessage::TYPE_CONSTRAINT_SCOPE_LIST => 'renderConstraintScopeList',
			ViolationMessage::TYPE_PROPERTY_SCOPE => 'renderPropertyScope',
			ViolationMessage::TYPE_PROPERTY_SCOPE_LIST => 'renderPropertyScopeList',
			ViolationMessage::TYPE_LANGUAGE => 'renderLanguage',
			ViolationMessage::TYPE_LANGUAGE_LIST => 'renderLanguageList',
		];

		$type = $argument['type'];
		$value = $argument['value'];
		$role = $argument['role'];

		if ( array_key_exists( $type, $methods ) ) {
			$method = $methods[$type];
			$params = $this->$method( $value, $role );
		} else {
			throw new InvalidArgumentException(
				'Unknown ViolationMessage argument type ' . $type . '!'
			);
		}

		return $params;
	}

	/**
	 * @param array $list
	 * @param string|null $role one of the Role::* constants
	 * @param callable $render must accept $list elements and $role as parameters
	 * and return a single-element array with a raw message param (i. e. [ Message::rawParam( … ) ])
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderList( array $list, $role, callable $render ) {
		if ( $list === [] ) {
			return [
				Message::numParam( 0 ),
				Message::rawParam( '<ul></ul>' ),
			];
		}

		if ( count( $list ) > $this->maxListLength ) {
			$list = array_slice( $list, 0, $this->maxListLength );
			$truncated = true;
		}

		$renderedParamsLists = array_map(
			$render,
			$list,
			array_fill( 0, count( $list ), $role )
		);
		$renderedParams = array_map(
			static function ( $params ) {
				return $params[0];
			},
			$renderedParamsLists
		);
		$renderedElements = array_map(
			static function ( $param ) {
				return $param['raw'];
			},
			$renderedParams
		);
		if ( isset( $truncated ) ) {
			$renderedElements[] = $this->msgEscaped( 'ellipsis' );
		}

		return array_merge(
			[
				Message::numParam( count( $list ) ),
				Message::rawParam(
					'<ul><li>' .
					implode( '</li><li>', $renderedElements ) .
					'</li></ul>'
				),
			],
			$renderedParams
		);
	}

	/**
	 * @param EntityId $entityId
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of a single raw message param (i. e. [ Message::rawParam( … ) ])
	 */
	private function renderEntityId( EntityId $entityId, $role ) {
		return [ Message::rawParam( $this->addRole(
			$this->entityIdFormatter->formatEntityId( $entityId ),
			$role
		) ) ];
	}

	/**
	 * @param EntityId[] $entityIdList
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderEntityIdList( array $entityIdList, $role ) {
		return $this->renderList( $entityIdList, $role, [ $this, 'renderEntityId' ] );
	}

	/**
	 * @param ItemIdSnakValue $value
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of a single raw message param (i. e. [ Message::rawParam( … ) ])
	 */
	private function renderItemIdSnakValue( ItemIdSnakValue $value, $role ) {
		switch ( true ) {
			case $value->isValue():
				return $this->renderEntityId( $value->getItemId(), $role );
			case $value->isSomeValue():
				return [ Message::rawParam( $this->addRole(
					'<span class="wikibase-snakview-variation-somevaluesnak">' .
						$this->msgEscaped( 'wikibase-snakview-snaktypeselector-somevalue' ) .
						'</span>',
					$role
				) ) ];
			case $value->isNoValue():
				return [ Message::rawParam( $this->addRole(
					'<span class="wikibase-snakview-variation-novaluesnak">' .
					$this->msgEscaped( 'wikibase-snakview-snaktypeselector-novalue' ) .
						'</span>',
					$role
				) ) ];
			default:
				// @codeCoverageIgnoreStart
				throw new LogicException(
					'ItemIdSnakValue should guarantee that one of is{,Some,No}Value() is true'
				);
				// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * @param ItemIdSnakValue[] $valueList
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderItemIdSnakValueList( array $valueList, $role ) {
		return $this->renderList( $valueList, $role, [ $this, 'renderItemIdSnakValue' ] );
	}

	/**
	 * @param DataValue $dataValue
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderDataValue( DataValue $dataValue, $role ) {
		return [ Message::rawParam( $this->addRole(
			$this->dataValueFormatter->format( $dataValue ),
			$role
		) ) ];
	}

	/**
	 * @param string $dataValueType
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderDataValueType( $dataValueType, $role ) {
		$messageKeys = [
			'string' => 'datatypes-type-string',
			'monolingualtext' => 'datatypes-type-monolingualtext',
			'time' => 'datatypes-type-time',
			'quantity' => 'datatypes-type-quantity',
			'wikibase-entityid' => 'wbqc-dataValueType-wikibase-entityid',
		];

		if ( array_key_exists( $dataValueType, $messageKeys ) ) {
			return [ Message::rawParam( $this->addRole(
				$this->msgEscaped( $messageKeys[$dataValueType] ),
				$role
			) ) ];
		} else {
			// @codeCoverageIgnoreStart
			throw new LogicException(
				'Unknown data value type ' . $dataValueType
			);
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * @param string $code (not yet HTML-escaped)
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderInlineCode( $code, $role ) {
		return [ Message::rawParam( $this->addRole(
			'<code>' . htmlspecialchars( $code ) . '</code>',
			$role
		) ) ];
	}

	/**
	 * @param string $scope one of the Context::TYPE_* constants
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of a single raw message param (i. e. [ Message::rawParam( … ) ])
	 */
	private function renderConstraintScope( $scope, $role ) {
		switch ( $scope ) {
			case Context::TYPE_STATEMENT:
				$itemId = $this->config->get(
					'WBQualityConstraintsConstraintCheckedOnMainValueId'
				);
				break;
			case Context::TYPE_QUALIFIER:
				$itemId = $this->config->get(
					'WBQualityConstraintsConstraintCheckedOnQualifiersId'
				);
				break;
			case Context::TYPE_REFERENCE:
				$itemId = $this->config->get(
					'WBQualityConstraintsConstraintCheckedOnReferencesId'
				);
				break;
			default:
				// callers should never let this happen, but if it does happen,
				// showing “unknown value” seems reasonable
				// @codeCoverageIgnoreStart
				return $this->renderItemIdSnakValue( ItemIdSnakValue::someValue(), $role );
				// @codeCoverageIgnoreEnd
		}
		return $this->renderEntityId( new ItemId( $itemId ), $role );
	}

	/**
	 * @param string[] $scopeList Context::TYPE_* constants
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderConstraintScopeList( array $scopeList, $role ) {
		return $this->renderList( $scopeList, $role, [ $this, 'renderConstraintScope' ] );
	}

	/**
	 * @param string $scope one of the Context::TYPE_* constants
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of a single raw message param (i. e. [ Message::rawParam( … ) ])
	 */
	private function renderPropertyScope( $scope, $role ) {
		switch ( $scope ) {
			case Context::TYPE_STATEMENT:
				$itemId = $this->config->get( 'WBQualityConstraintsAsMainValueId' );
				break;
			case Context::TYPE_QUALIFIER:
				$itemId = $this->config->get( 'WBQualityConstraintsAsQualifiersId' );
				break;
			case Context::TYPE_REFERENCE:
				$itemId = $this->config->get( 'WBQualityConstraintsAsReferencesId' );
				break;
			default:
				// callers should never let this happen, but if it does happen,
				// showing “unknown value” seems reasonable
				// @codeCoverageIgnoreStart
				return $this->renderItemIdSnakValue( ItemIdSnakValue::someValue(), $role );
				// @codeCoverageIgnoreEnd
		}
		return $this->renderEntityId( new ItemId( $itemId ), $role );
	}

	/**
	 * @param string[] $scopeList Context::TYPE_* constants
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderPropertyScopeList( array $scopeList, $role ) {
		return $this->renderList( $scopeList, $role, [ $this, 'renderPropertyScope' ] );
	}

	/**
	 * @param string $languageCode MediaWiki language code
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderLanguage( $languageCode, $role ) {
		return [
			// ::renderList (through ::renderLanguageList) requires 'raw' parameter
			// so we effectively build Message::plaintextParam here
			Message::rawParam( htmlspecialchars( Language::fetchLanguageName( $languageCode ) ) ),
			Message::plaintextParam( $languageCode ),
		];
	}

	/**
	 * @param string[] $languageCodes MediaWiki language codes
	 * @param string|null $role one of the Role::* constants
	 * @return array[] list of parameters as accepted by Message::params()
	 */
	private function renderLanguageList( $languageCodes, $role ) {
		return $this->renderList( $languageCodes, $role, [ $this, 'renderLanguage' ] );
	}

}
