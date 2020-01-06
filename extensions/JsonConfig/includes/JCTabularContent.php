<?php

namespace JsonConfig;

use Language;

/**
 * @package JsonConfig
 */
class JCTabularContent extends JCDataContent {

	protected function createDefaultView() {
		return new JCTabularContentView();
	}

	/**
	 * Returns wiki-table representation of the tabular data
	 *
	 * @return string|bool The raw text, or false if the conversion failed.
	 */
	public function getWikitextForTransclusion() {
		$toWiki = function ( $value ) {
			if ( is_object( $value ) ) {
				global $wgLang;
				$value = JCUtils::pickLocalizedString( $value, $wgLang );
			}
			if ( preg_match( '/^[ .\pL\pN]*$/i', $value ) ) {
				// Optimization: spaces, letters, numbers, and dots are returned without <nowiki>
				return $value;
			}
			return '<nowiki>' . htmlspecialchars( $value ) . '</nowiki>';
		};

		$data = $this->getData();
		$result = "{| class='wikitable sortable'\n";

		// Create header
		$result .= '!' . implode( "!!",
			array_map(
				function ( $field ) use ( $toWiki ) {
					return $toWiki( $field->title ? : $field->name );
				},
				$data->schema->fields
			)
		) . "\n";

		// Create table content
		foreach ( $data->data as $row ) {
			$result .= "|-\n|" . implode( '||', array_map( $toWiki, $row ) ) . "\n";
		}

		$result .= "\n|}\n";

		return $result;
	}

	/**
	 * Derived classes must implement this method to perform custom validation
	 * using the check(...) calls
	 */
	public function validateContent() {
		parent::validateContent();

		$validators = [ JCValidators::isList() ];
		$typeValidators = [];
		$fieldsPath = [ 'schema', 'fields' ];
		if ( $this->test( 'schema', JCValidators::isDictionary() ) &&
			$this->test( $fieldsPath, JCValidators::isList() ) &&
			$this->testEach( $fieldsPath, JCValidators::isDictionary() )
		) {
			$hasError = false;
			$allHeaders = [];
			$fieldCount = count( $this->getField( $fieldsPath )->getValue() );
			for ( $idx = 0; $idx < $fieldCount; $idx++ ) {
				$header = false;
				$hasError |= !$this->test( [ 'schema', 'fields', $idx, 'name' ],
					JCValidators::isHeaderString( $allHeaders ),
					function ( JCValue $jcv ) use ( &$header ) {
						$header = $jcv->getValue();
						return true;
					} );
				$hasError |= !$this->test( [ 'schema', 'fields', $idx, 'type' ],
					JCValidators::validateDataType( $typeValidators ) );
				if ( $header ) {
					$hasError |= !$this->testOptional( [ 'schema', 'fields', $idx, 'title' ],
						function () use ( $header ) {
							return (object)[ 'en' => $header ];
						}, JCValidators::isLocalizedString() );
				}
			}
			$countValidator = JCValidators::checkListSize( $fieldCount, 'schema/fields' );
			$validators[] = $countValidator;

			if ( !$hasError ) {
				$this->testEach( $fieldsPath, JCValidators::noExtraValues() );
			}
		}
		$this->test( 'schema', JCValidators::noExtraValues() );

		if ( !$this->thorough() ) {
			// We are not doing any modifications to the data, so no need to validate it
			return;
		}

		$this->test( 'data', JCValidators::isList() );
		$this->test( [], JCValidators::noExtraValues() );
		$this->testEach( 'data', $validators );
		if ( $typeValidators ) {
			/** @noinspection PhpUnusedParameterInspection */
			$this->testEach( 'data', function ( JCValue $v, array $path ) use ( $typeValidators ) {
				$isOk = true;
				$lastIdx = count( $path );
				foreach ( array_keys( $typeValidators ) as $k ) {
					$path[$lastIdx] = $k;
					$isOk &= $this->test( $path, $typeValidators[$k] );
				}
				return $isOk;
			} );
		}
	}

	/**
	 * Resolve any override-specific localizations, and add it to $result
	 * @param object $result
	 * @param Language $lang
	 */
	protected function localizeData( $result, Language $lang ) {
		parent::localizeData( $result, $lang );

		$data = $this->getData();
		$localize = function ( $value ) use ( $lang ) {
			return JCUtils::pickLocalizedString( $value, $lang );
		};

		$isLocalized = [];
		$result->schema = (object)[];
		$result->schema->fields = [];
		foreach ( $data->schema->fields as $ind => $fld ) {
			if ( $fld->type === 'localized' ) {
				$isLocalized[] = $ind;
			}
			$result->schema->fields[] = (object)[
				'name' => $fld->name,
				'type' => $fld->type,
				'title' => property_exists( $fld, 'title' ) ? $localize( $fld->title ) : $fld->name
			];
		}

		if ( !$isLocalized ) {
			// There are no localized strings in the data, optimize
			$result->data = $data->data;
		} else {
			$result->data = array_map( function ( $row ) use ( $localize, $isLocalized ) {
				foreach ( $isLocalized as $ind ) {
					if ( $row[$ind] !== null ) {
						$row[$ind] = $localize( $row[$ind] );
					}
				}
				return $row;
			}, $data->data );
		}
	}
}
