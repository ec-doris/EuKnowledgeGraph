<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\ValueCountChecker;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ReferenceContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\DummySparqlHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Helper\JsonFileEntityLookup;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;
use WikibaseQuality\ConstraintReport\Tests\SparqlHelperMock;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author Olga Bode
 * @license GPL-2.0-or-later
 */
class UniqueValueCheckerTest extends \PHPUnit\Framework\TestCase {

	use ConstraintParameters, SparqlHelperMock;

	/**
	 * @var JsonFileEntityLookup
	 */
	private $lookup;

	use ResultAssertions;

	/**
	 * @var PropertyId
	 */
	private $uniquePropertyId;

	/**
	 * @var UniqueValueChecker
	 */
	private $checker;

	protected function setUp() : void {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->uniquePropertyId = new PropertyId( 'P31' );
	}

	public function testCheckUniqueValueConstraintInvalid() {
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) );
		$statement->setGuid( 'Q6$e35707be-4a84-61fe-9b52-623784a316a7' );

		$mock = $this->getSparqlHelperMockFindEntities( $statement, [ new ItemId( 'Q42' ) ] );

		$this->checker = new UniqueValueChecker( $mock );

		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );

		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-unique-value' );
	}

	public function testCheckUniqueValueConstraintInvalidWithPropertyId() {
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q6' ) ) ) );
		$statement->setGuid( 'Q6$e35707be-4a84-61fe-9b52-623784a316a7' );

		$mock = $this->getSparqlHelperMockFindEntities( $statement, [ new PropertyId( 'P42' ) ] );

		$this->checker = new UniqueValueChecker( $mock );

		$entity = $this->lookup->getEntity( new ItemId( 'Q6' ) );

		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-unique-value' );
	}

	public function testCheckUniqueValueConstraintValid() {
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statement->setGuid( "Q1$56e6a474-4431-fb24-cc15-1d580e467559" );

		$mock = $this->getSparqlHelperMockFindEntities( $statement, [] );

		$this->checker = new UniqueValueChecker( $mock );

		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testCheckUniqueValueConstraintInvalidOnQualifier() {
		$entity = NewItem::withId( 'Q6' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$entity->getStatements()->addStatement( $statement );
		$snak = new PropertyValueSnak( new PropertyId( 'P7' ), new StringValue( 'O8' ) );
		$sparqlHelper = $this->getSparqlHelperMockFindEntitiesQualifierReference(
			new ItemId( 'Q6' ),
			$snak,
			'qualifier',
			[ new ItemId( 'Q42' ) ]
		);
		$this->checker = new UniqueValueChecker( $sparqlHelper );
		$context = new QualifierContext( $entity, $statement, $snak );
		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-unique-value' );
	}

	public function testCheckUniqueValueConstraintValidOnReference() {
		$entity = NewItem::withId( 'Q6' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$entity->getStatements()->addStatement( $statement );
		$reference = new Reference();
		$statement->getReferences()->addReference( $reference );
		$snak = new PropertyValueSnak( new PropertyId( 'P7' ), new StringValue( 'O8' ) );
		$reference->getSnaks()->addSnak( $snak );
		$sparqlHelper = $this->getSparqlHelperMockFindEntitiesQualifierReference(
			new ItemId( 'Q6' ),
			$snak,
			'reference',
			[]
		);
		$this->checker = new UniqueValueChecker( $sparqlHelper );
		$context = new ReferenceContext( $entity, $statement, $reference, $snak );
		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testCheckUniqueValueWithoutSparql() {
		$statement = new Statement( new PropertyValueSnak( $this->uniquePropertyId, new EntityIdValue( new ItemId( 'Q1' ) ) ) );
		$statement->setGuid( "Q1$56e6a474-4431-fb24-cc15-1d580e467559" );

		$this->checker = new UniqueValueChecker( new DummySparqlHelper() );

		$entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );

		$constraint = $this->getConstraintMock( [] );

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertTodo( $checkResult );
	}

	public function testUniqueValueConstraintDeprecatedStatement() {
		$this->checker = new UniqueValueChecker( new DummySparqlHelper() );
		$statement = NewStatement::noValueFor( 'P1' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->checker->checkConstraint( new MainSnakContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$this->checker = new UniqueValueChecker( new DummySparqlHelper() );
		$constraint = $this->getConstraintMock( [] );

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertEmpty( $result );
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
			 ->will( $this->returnValue( 'Q21502410' ) );

		return $mock;
	}

}
