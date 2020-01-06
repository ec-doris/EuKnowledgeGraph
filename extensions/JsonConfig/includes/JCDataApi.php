<?php
namespace JsonConfig;

use ApiBase;
use ApiResult;
use Title;

/**
 * Get localized json data, similar to Lua's mw.data.get() function
 */
class JCDataApi extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$jct = JCSingleton::parseTitle( $params['title'], NS_DATA );
		if ( !$jct ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['title'] ) ] );
		}

		$data = JCSingleton::getContent( $jct );
		if ( !$data ) {
			$this->dieWithError(
				[
					'apierror-invalidtitle',
					wfEscapeWikiText( Title::newFromTitleValue( $jct )->getPrefixedText() )
				]
			);
		} elseif ( !method_exists( $data, 'getLocalizedData' ) ) {
			$data = $data->getData();
		} else {
			/** @var JCDataContent $data */
			$data = $data->getSafeData( $data->getLocalizedData( $this->getLanguage() ) );
		}

		// Armor any API metadata in $data
		$data = ApiResult::addMetadataToResultVars( (array)$data, is_object( $data ) );

		$this->getResult()->addValue( null, $this->getModuleName(), $data );

		$this->getMain()->setCacheMaxAge( 24 * 60 * 60 ); // seconds
		$this->getMain()->setCacheMode( 'public' );
	}

	public function getAllowedParams() {
		return [
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=jsondata&formatversion=2&format=jsonfm&title=Sample.tab'
				=> 'apihelp-jsondata-example-1',
			'action=jsondata&formatversion=2&format=jsonfm&title=Sample.tab&uselang=fr'
				=> 'apihelp-jsondata-example-2',
		];
	}

	public function isInternal() {
		return true;
	}
}
