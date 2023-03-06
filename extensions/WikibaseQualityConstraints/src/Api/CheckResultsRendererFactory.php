<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\Api;

use Language;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRendererFactory;

/**
 * @license GPL-2.0-or-later
 */
class CheckResultsRendererFactory {

	/** @var EntityTitleLookup */
	private $entityTitleLookup;

	/** @var EntityIdLabelFormatterFactory */
	private $entityIdLabelFormatterFactory;

	/** @var ViolationMessageRendererFactory */
	private $violationMessageRendererFactory;

	public function __construct(
		EntityTitleLookup $entityTitleLookup,
		EntityIdLabelFormatterFactory $entityIdLabelFormatterFactory,
		ViolationMessageRendererFactory $violationMessageRendererFactory
	) {
		$this->entityTitleLookup = $entityTitleLookup;
		$this->entityIdLabelFormatterFactory = $entityIdLabelFormatterFactory;
		$this->violationMessageRendererFactory = $violationMessageRendererFactory;
	}

	public function getCheckResultsRenderer( Language $language ): CheckResultsRenderer {
		return new CheckResultsRenderer(
			$this->entityTitleLookup,
			$this->entityIdLabelFormatterFactory
				->getEntityIdFormatter( $language ),
			$this->violationMessageRendererFactory
				->getViolationMessageRenderer( $language )
		);
	}

}
