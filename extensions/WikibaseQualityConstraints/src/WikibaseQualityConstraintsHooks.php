<?php

namespace WikibaseQuality\ConstraintReport;

use Config;
use DatabaseUpdater;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Skin;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Changes\Change;
use Wikibase\Lib\Changes\EntityChange;
use Wikibase\Lib\Changes\EntityDiffChangedAspects;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Api\ResultsCache;
use WikibaseQuality\ConstraintReport\Job\CheckConstraintsJob;
use WikiPage;

/**
 * Container for hook callbacks registered in extension.json.
 *
 * @license GPL-2.0-or-later
 */
final class WikibaseQualityConstraintsHooks {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onCreateSchema( DatabaseUpdater $updater ) {
		$dir = dirname( __DIR__ ) . '/sql/';

		$updater->addExtensionTable(
			'wbqc_constraints',
			$dir . "/{$updater->getDB()->getType()}/tables-generated.sql"
		);
		$updater->addExtensionField(
			'wbqc_constraints',
			'constraint_id',
			$dir . '/patch-wbqc_constraints-constraint_id.sql'
		);
		$updater->addExtensionIndex(
			'wbqc_constraints',
			'wbqc_constraints_guid_uniq',
			$dir . '/patch-wbqc_constraints-wbqc_constraints_guid_uniq.sql'
		);
	}

	public static function onWikibaseChange( Change $change ) {
		if ( !( $change instanceof EntityChange ) ) {
			return;
		}

		/** @var EntityChange $change */
		$config = MediaWikiServices::getInstance()->getMainConfig();

		// If jobs are enabled and the results would be stored in some way run a job.
		if (
			$config->get( 'WBQualityConstraintsEnableConstraintsCheckJobs' ) &&
			$config->get( 'WBQualityConstraintsCacheCheckConstraintsResults' ) &&
			self::isSelectedForJobRunBasedOnPercentage()
		) {
			$params = [ 'entityId' => $change->getEntityId()->getSerialization() ];
			JobQueueGroup::singleton()->lazyPush(
				new JobSpecification( CheckConstraintsJob::COMMAND, $params )
			);
		}

		if ( $config->get( 'WBQualityConstraintsEnableConstraintsImportFromStatements' ) &&
			self::isConstraintStatementsChange( $config, $change )
		) {
			$params = [ 'propertyId' => $change->getEntityId()->getSerialization() ];
			$metadata = $change->getMetadata();
			if ( array_key_exists( 'rev_id', $metadata ) ) {
				$params['revisionId'] = $metadata['rev_id'];
			}
			JobQueueGroup::singleton()->push(
				new JobSpecification( 'constraintsTableUpdate', $params )
			);
		}
	}

	private static function isSelectedForJobRunBasedOnPercentage() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$percentage = $config->get( 'WBQualityConstraintsEnableConstraintsCheckJobsRatio' );

		return mt_rand( 1, 100 ) <= $percentage;
	}

	public static function isConstraintStatementsChange( Config $config, Change $change ) {
		if ( !( $change instanceof EntityChange ) ||
			 $change->getAction() !== EntityChange::UPDATE ||
			 !( $change->getEntityId() instanceof PropertyId )
		) {
			return false;
		}

		$info = $change->getInfo();

		if ( !array_key_exists( 'compactDiff', $info ) ) {
			// the non-compact diff ($info['diff']) does not contain statement diffs (T110996),
			// so we only know that the change *might* affect the constraint statements
			return true;
		}

		/** @var EntityDiffChangedAspects $aspects */
		$aspects = $info['compactDiff'];

		$propertyConstraintId = $config->get( 'WBQualityConstraintsPropertyConstraintId' );
		return in_array( $propertyConstraintId, $aspects->getStatementChanges() );
	}

	public static function onArticlePurge( WikiPage $wikiPage ) {
		$entityContentFactory = WikibaseRepo::getEntityContentFactory();
		if ( $entityContentFactory->isEntityContentModel( $wikiPage->getContentModel() ) ) {
			$entityIdLookup = WikibaseRepo::getEntityIdLookup();
			$entityId = $entityIdLookup->getEntityIdForTitle( $wikiPage->getTitle() );
			if ( $entityId !== null ) {
				$resultsCache = ResultsCache::getDefaultInstance();
				$resultsCache->delete( $entityId );
			}
		}
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$lookup = WikibaseRepo::getEntityNamespaceLookup();
		$title = $out->getTitle();
		if ( $title === null ) {
			return;
		}

		if ( !$lookup->isNamespaceWithEntities( $title->getNamespace() ) ) {
			return;
		}
		if ( empty( $out->getJsConfigVars()['wbIsEditView'] ) ) {
			return;
		}

		$out->addModules( 'wikibase.quality.constraints.suggestions' );

		if ( !$out->getUser()->isRegistered() ) {
			return;
		}

		$out->addModules( 'wikibase.quality.constraints.gadget' );
	}

}
