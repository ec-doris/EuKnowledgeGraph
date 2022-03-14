<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\TypeChecker;

use NullStatsdDataFactory;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\DummySparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\Helper\JsonFileEntityLookup;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class TypeCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters;
	use ResultAssertions;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	/**
	 * @var TypeChecker
	 */
	private $checker;

	/**
	 * @var Snak
	 */
	private $typeSnak;

	protected function setUp() : void {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->checker = new TypeChecker(
			$this->getConstraintParameterParser(),
			new TypeCheckerHelper(
				$this->lookup,
				$this->getDefaultConfig(),
				new DummySparqlHelper(),
				new NullStatsdDataFactory()
			),
			$this->getDefaultConfig()
		);
		$this->typeSnak = new PropertyValueSnak( new PropertyId( 'P1' ), new EntityIdValue( new ItemId( 'Q42' ) ) );
	}

	public function testTypeConstraintInstanceValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instance' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instance' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instance' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintSubclassValid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'subclass' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintSubclassValidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'subclass' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintSubclassValidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'subclass' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceOrSubclassValidViaInstance() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instanceOrSubclass' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceOrSubclassValidViaSubclass() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instanceOrSubclass' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertCompliance( $checkResult );
	}

	public function testTypeConstraintInstanceInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instance' ),
			$this->classParameter( [ 'Q200', 'Q201' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-instance' );
	}

	public function testTypeConstraintInstanceInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q2' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instance' ),
			$this->classParameter( [ 'Q200', 'Q201' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-instance' );
	}

	public function testTypeConstraintInstanceInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q3' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instance' ),
			$this->classParameter( [ 'Q200', 'Q201' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-instance' );
	}

	public function testTypeConstraintSubclassInvalid() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q4' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'subclass' ),
			$this->classParameter( [ 'Q200', 'Q201' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-subclass' );
	}

	public function testTypeConstraintSubclassInvalidWithIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q5' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'subclass' ),
			$this->classParameter( [ 'Q200', 'Q201' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-subclass' );
	}

	public function testTypeConstraintSubclassInvalidWithMoreIndirection() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'subclass' ),
			$this->classParameter( [ 'Q200', 'Q201' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-subclass' );
	}

	public function testTypeConstraintSubclassCycle() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q7' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instance' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );

		$checkResult = $this->checker->checkConstraint( new FakeSnakContext( $this->typeSnak, $entity ), $constraint );
		$this->assertViolation( $checkResult, 'wbqc-violation-message-type-instance' );
	}

	public function testTypeConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testTypeConstraintInstanceValidQualifier() {
		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
		$constraintParameters = array_merge(
			$this->relationParameter( 'instance' ),
			$this->classParameter( [ 'Q100', 'Q101' ] )
		);
		$constraint = $this->getConstraintMock( $constraintParameters );
		$context = new QualifierContext(
			$entity,
			new Statement( $this->typeSnak ),
			new PropertyNoValueSnak( new PropertyId( 'P2000' ) )
		);

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertCount( 2, $result );
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
			 ->will( $this->returnValue( 'Q21503250' ) );

		return $mock;
	}

}
