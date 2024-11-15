<?php
namespace JsonConfig;

use Content;
use IContextSource;
use MediaWiki\Title\Title;
use OutputPage;
use SlotDiffRenderer;
use TextSlotDiffRenderer;

class JCSlotDiffRenderer extends SlotDiffRenderer {
	/** @var TextSlotDiffRenderer */
	private $textSlotDiffRenderer;

	public function __construct(
		TextSlotDiffRenderer $textSlotDiffRenderer
	) {
		$this->textSlotDiffRenderer = $textSlotDiffRenderer;
	}

	public function getTablePrefix( IContextSource $context, Title $newTitle ): array {
		return $this->textSlotDiffRenderer->getTablePrefix( $context, $newTitle );
	}

	public function addModules( OutputPage $output ) {
		$this->textSlotDiffRenderer->addModules( $output );
	}

	public function getExtraCacheKeys() {
		return $this->textSlotDiffRenderer->getExtraCacheKeys();
	}

	public function getDiff( Content $oldContent = null, Content $newContent = null ) {
		$this->normalizeContents( $oldContent, $newContent, [ JCContent::class ] );
		$format = JCContentHandler::CONTENT_FORMAT_JSON_PRETTY;

		$oldText = $oldContent->serialize( $format );
		$newText = $newContent->serialize( $format );

		return $this->textSlotDiffRenderer->getTextDiff( $oldText, $newText );
	}
}
