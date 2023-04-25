<?php

declare( strict_types = 1 );

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Message;

use Config;
use Language;
use MessageLocalizer;
use ValueFormatters\FormatterOptions;
use Wikibase\Lib\Formatters\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\View\EntityIdFormatterFactory;

/**
 * @license GPL-2.0-or-later
 */
class ViolationMessageRendererFactory {

	/** @var Config */
	private $config;

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var EntityIdFormatterFactory */
	private $entityIdHtmlLinkFormatterFactory;

	/** @var OutputFormatValueFormatterFactory */
	private $valueFormatterFactory;

	public function __construct(
		Config $config,
		MessageLocalizer $messageLocalizer,
		EntityIdFormatterFactory $entityIdHtmlLinkFormatterFactory,
		OutputFormatValueFormatterFactory $valueFormatterFactory
	) {
		$this->config = $config;
		$this->messageLocalizer = $messageLocalizer;
		$this->entityIdHtmlLinkFormatterFactory = $entityIdHtmlLinkFormatterFactory;
		$this->valueFormatterFactory = $valueFormatterFactory;
	}

	public function getViolationMessageRenderer( Language $language ): ViolationMessageRenderer {
		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $language->getCode() );
		return new MultilingualTextViolationMessageRenderer(
			$this->entityIdHtmlLinkFormatterFactory
				->getEntityIdFormatter( $language ),
			$this->valueFormatterFactory
				->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions ),
			$this->messageLocalizer,
			$this->config
		);
	}

}
