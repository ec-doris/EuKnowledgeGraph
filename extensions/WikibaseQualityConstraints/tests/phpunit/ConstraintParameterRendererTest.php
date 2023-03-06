<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use DataValues\StringValue;
use Language;
use MockMessageLocalizer;
use ValueFormatters\StringFormatter;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\EntityId\PlainEntityIdFormatter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ItemIdSnakValue;
use WikibaseQuality\ConstraintReport\ConstraintParameterRenderer;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintParameterRenderer
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class ConstraintParameterRendererTest extends \MediaWikiIntegrationTestCase {

	use DefaultConfig;

	public function testFormatDataValue() {
		$value = new StringValue( 'a test string' );
		$valueFormatter = $this->createMock( ValueFormatter::class );
		$valueFormatter->expects( $this->once() )
			->method( 'format' )
			->with( $value )
			->willReturn( '<a test string>' );
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			new PlainEntityIdFormatter(),
			$valueFormatter,
			new MockMessageLocalizer(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatDataValue( $value );

		$this->assertSame( '<a test string>', $formatted );
	}

	public function testFormatEntityId() {
		$value = new NumericPropertyId( 'P1234' );
		$entityIdFormatter = $this->createMock( EntityIdFormatter::class );
		$entityIdFormatter->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $value )
			->willReturn( '<some property>' );
		$constraintParameterRenderer = new ConstraintParameterRenderer(
			$entityIdFormatter,
			new StringFormatter(),
			new MockMessageLocalizer(),
			$this->getDefaultConfig()
		);

		$formatted = $constraintParameterRenderer->formatEntityId( $value );

		$this->assertSame( '<some property>', $formatted );
	}

	public function testFormatItemIdSnakValue_Value() {
		$itemId = new ItemId( 'Q1234' );
		$value = ItemIdSnakValue::fromItemId( $itemId );
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->setConstructorArgs( [
				new PlainEntityIdFormatter(),
				new StringFormatter(),
				new MockMessageLocalizer(),
				$this->getDefaultConfig()
			] )
			->onlyMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->once() )
			->method( 'formatEntityId' )
			->with( $itemId )
			->willReturn( 'some item' );

		$formatted = $constraintParameterRenderer->formatItemIdSnakValue( $value );

		$this->assertSame( 'some item', $formatted );
	}

	public function testFormatItemIdSnakValue_SomeValue() {
		$value = ItemIdSnakValue::someValue();
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->setConstructorArgs( [
				new PlainEntityIdFormatter(),
				new StringFormatter(),
				new MockMessageLocalizer(),
				$this->getDefaultConfig()
			] )
			->onlyMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->never() )->method( 'formatEntityId' );
		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'en' ) ] );

		$formatted = $constraintParameterRenderer->formatItemIdSnakValue( $value );

		$this->assertSame( '(wikibase-snakview-snaktypeselector-somevalue)', $formatted );
	}

	public function testFormatItemIdSnakValue_NoValue() {
		$value = ItemIdSnakValue::noValue();
		$constraintParameterRenderer = $this->getMockBuilder( ConstraintParameterRenderer::class )
			->setConstructorArgs( [
				new PlainEntityIdFormatter(),
				new StringFormatter(),
				new MockMessageLocalizer(),
				$this->getDefaultConfig()
			] )
			->onlyMethods( [ 'formatEntityId' ] )
			->getMock();
		$constraintParameterRenderer->expects( $this->never() )->method( 'formatEntityId' );
		$this->setMwGlobals( [ 'wgLang' => Language::factory( 'en' ) ] );

		$formatted = $constraintParameterRenderer->formatItemIdSnakValue( $value );

		$this->assertSame( '(wikibase-snakview-snaktypeselector-novalue)', $formatted );
	}

}
