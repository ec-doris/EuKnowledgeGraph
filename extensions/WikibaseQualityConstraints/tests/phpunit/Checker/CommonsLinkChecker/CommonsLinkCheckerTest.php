<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\CommonsLinkChecker;

use DataValues\StringValue;
use MediaWiki\Site\MediaWikiPageNameNormalizer;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker
 *
 * @group WikibaseQualityConstraints
 * @group Database
 * @group medium
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class CommonsLinkCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var CommonsLinkChecker
	 */
	private $commonsLinkChecker;

	protected function setUp() : void {
		parent::setUp();
		$pageNameNormalizer = $this
			->getMockBuilder( MediaWikiPageNameNormalizer::class )
			->disableOriginalConstructor()
			->getMock();

		$valueMap = [
			'File:File:test image.jpg' => false,
			'File:test image.jpg' => 'File:Test image.jpg',
			'Category:test category' => 'Category:Test category',
			'test gallery' => 'Test gallery',
			'test creator' => 'Creator:Test creator',
			'test data' => 'Data:Test data',
			'File:no image.jpg' => false,
		];

		$pageNameNormalizer
			->method( 'normalizePageName' )
			->willReturnCallback( function ( $pageName, $apiUrl ) use ( $valueMap ) {
				$this->assertSame( 'https://commons.wikimedia.org/w/api.php', $apiUrl );
				return $valueMap[$pageName];
			} );

		$this->commonsLinkChecker = new CommonsLinkChecker(
			$this->getConstraintParameterParser(),
			$pageNameNormalizer
		);
	}

	public function testCommonsLinkConstraintValid() {
		$value = new StringValue( 'test image.jpg' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintInvalid() {
		$value1 = new StringValue( 'test_image.jpg' );
		$value2 = new StringValue( 'test%20image.jpg' );
		$value3 = new StringValue( 'File:test image.jpg' );
		$snak1 = new PropertyValueSnak( new PropertyId( 'P1' ), $value1 );
		$snak2 = new PropertyValueSnak( new PropertyId( 'P1' ), $value2 );
		$snak3 = new PropertyValueSnak( new PropertyId( 'P1' ), $value3 );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak1 ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak2 ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak3 ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-not-well-formed' );
	}

	public function testCommonsLinkConstraintValidCategory() {
		$value = new StringValue( 'test category' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'Category' ) )
		);
		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintValidGallery() {
		$value = new StringValue( 'test gallery' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( '' ) )
		);

		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintValidCreator() {
		$value = new StringValue( 'test creator' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'Creator' ) )
		);

		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintValidData() {
		$value = new StringValue( 'test data' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'Data' ) )
		);

		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintNotExistent() {
		$value = new StringValue( 'no image.jpg' );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-commons-link-no-existent' );
	}

	public function testCommonsLinkConstraintNoStringValue() {
		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), $value );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-value-needed-of-type' );
	}

	public function testCommonsLinkConstraintNoValueSnak() {
		$snak = new PropertyNoValueSnak( new PropertyId( 'P1' ) );

		$result = $this->commonsLinkChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->namespaceParameter( 'File' ) )
		);

		$this->assertCompliance( $result );
	}

	public function testCommonsLinkConstraintDeprecatedStatement() {
		$statement = NewStatement::forProperty( 'P1' )
				   ->withValue( 'not_well formed' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->commonsLinkChecker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		// this constraint is still checked on deprecated statements
		$this->assertViolation( $checkResult, 'wbqc-violation-message-commons-link-not-well-formed' );
	}

	public function testCheckConstraintParameters() {
		$namespaceId = $this->getDefaultConfig()->get( 'WBQualityConstraintsNamespaceId' );
		$constraint = $this->getConstraintMock( [ $namespaceId => [] ] );

		$result = $this->commonsLinkChecker->checkConstraintParameters( $constraint );

		$this->assertCount( 1, $result );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'Q21510852' ) );

		return $mock;
	}

}
