( function ( mw, wb, $ ) {
	'use strict';
	var MAX_LABELS_API_LIMIT = 50,
		jsonCache = {},
		config = require( './config.json' );

	function getJsonCached( url ) {
		var promise = null;

		if ( jsonCache[ url ] ) {
			return jsonCache[ url ];
		}

		promise = $.getJSON( url );
		jsonCache[ url ] = promise;
		return promise;
	}

	function getConstraintDefinition( repoApiUrl, propertyId, constraintsPropertyId, constraintId, constraintQualifierOfPropertyId ) {
		return getJsonCached( repoApiUrl + '?action=wbgetclaims&format=json&entity=' + propertyId + '&property=' + constraintsPropertyId ).then(
			function ( d ) {
				var oneOfIds = [];
				if ( !d.claims || !d.claims[ constraintsPropertyId ] ) {
					return oneOfIds;
				}

				d.claims[ constraintsPropertyId ].forEach( function ( c ) {
					if ( c.mainsnak.datavalue.value.id === constraintId && c.qualifiers && c.qualifiers[ constraintQualifierOfPropertyId ] ) {
						oneOfIds = oneOfIds.concat( c.qualifiers[ constraintQualifierOfPropertyId ]
							.filter( function ( filterD ) { return filterD.datavalue; } )
							.map( function ( mapD ) { return mapD.datavalue.value.id; } ) );
					}
				} );

				return oneOfIds;
			} );
	}

	function createItemsFromIdsFetchLabels( repoApiUrl, language, ids, filter, articlePathPattern ) {
		return getJsonCached( repoApiUrl + '?action=wbgetentities&props=labels|descriptions&format=json&languages=' + language + '&ids=' + ids.join( '|' ) ).then( function ( ld ) {
			var data = [],
				item = null;
			ids.forEach( function ( id ) {
				item = {
					id: id,
					label: ld.entities[ id ] && ld.entities[ id ].labels[ language ] && ld.entities[ id ].labels[ language ].value || '',
					description: ld.entities[ id ] && ld.entities[ id ].descriptions[ language ] && ld.entities[ id ].descriptions[ language ].value || '',
					rating: 1,
					url: articlePathPattern.replace( '$1', 'Special:EntityPage/' + id )
				};

				if ( !filter( item.label + item.description ) && ( item.label !== '' || item.description !== '' ) ) {
					data.push( item );
				}

			} );
			return data;
		} );
	}

	function createItemsFromIds( repoApiUrl, language, ids, filter, articlePathPattern ) {
		var $deferred = $.Deferred(),
			promises = [],
			itemList = [],
			addItems = function ( items ) {
				itemList = itemList.concat( items );
			};

		while ( ids.length > 0 ) {
			promises.push( createItemsFromIdsFetchLabels(
				repoApiUrl,
				language,
				ids.splice( 0, MAX_LABELS_API_LIMIT ),
				filter,
				articlePathPattern
			).then( addItems ) );
		}

		$.when.apply( $, promises ).then( function () {
			$deferred.resolve( itemList );
		} );

		return $deferred.promise();
	}

	function isQualifierContext( element ) {
		var $statementview, statementview;

		try {
			$statementview = element.closest( ':wikibase-statementview' );
			statementview = $statementview.data( 'statementview' );

			if ( !statementview ) {
				return false;
			}

			return element.closest( statementview.$qualifiers ).length > 0;

		} catch ( e ) {
			return false;
		}
	}

	function getMainSnakPropertyId( element ) {
		var snakview, snakPropertyId;

		try {
			snakview = element.closest( '.wikibase-statementlistview' )
				.find( '.wikibase-snakview.wb-edit' ).data( 'snakview' );
			snakPropertyId = snakview ? snakview.propertyId() : null;
		} catch ( e ) {
			return null;
		}

		return snakPropertyId;
	}

	mw.hook( 'wikibase.entityselector.search' ).add( function ( data, addPromise ) {
		var propertyConstraintId = config.WBQualityConstraintsPropertyConstraintId,
			oneOfConstraintId = config.WBQualityConstraintsOneOfConstraintId,
			allowedQualifierConstraintId = config.WBQualityConstraintsAllowedQualifierConstraintId,
			constraintsPropertyId = config.WBQualityConstraintsPropertyId,
			constraintQualifierOfPropertyId = config.WBQualityConstraintsQualifierOfPropertyConstraintId,
			mainSnakPropertyId = getMainSnakPropertyId( data.element ),
			wbRepo = mw.config.get( 'wbRepo' ),
			articlePathPattern = wbRepo.url + wbRepo.articlePath,
			constraintId = null,
			qualifierId = null,
			filterFunction = function ( term ) {
				var filter = false;
				data.term.split( ' ' ).forEach( function ( t ) {
					if ( term.toLowerCase().indexOf( t.toLowerCase() ) === -1 ) {
						filter = true;
					}
				} );
				return filter;
			};

		if ( isQualifierContext( data.element ) && data.options.type === 'property' ) {
			constraintId = allowedQualifierConstraintId;
			qualifierId = constraintsPropertyId;
		}

		if ( !isQualifierContext( data.element ) && data.options.type === 'item' ) {
			constraintId = oneOfConstraintId;
			qualifierId = constraintQualifierOfPropertyId;
		}

		if ( !constraintId || !qualifierId ) {
			return;
		}

		addPromise( getConstraintDefinition(
			data.options.url,
			mainSnakPropertyId,
			propertyConstraintId,
			constraintId,
			qualifierId
		).then( function ( oneOfIds ) {
			return createItemsFromIds( data.options.url, data.options.language, oneOfIds, filterFunction, articlePathPattern );
		} ) );

	} );

}( mediaWiki, wikibase, jQuery ) );
