<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\FormatChecker;

use DataValues\StringValue;
use HashConfig;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\DummySparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class FormatCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters;
	use ResultAssertions;

	/**
	 * @var FormatChecker
	 */
	private $formatChecker;

	protected function setUp() : void {
		parent::setUp();
		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
					  ->disableOriginalConstructor()
					  ->setMethods( [ 'matchesRegularExpression' ] )
					  ->getMock();
		$sparqlHelper->method( 'matchesRegularExpression' )
			->will( $this->returnCallback(
				function ( $text, $regex ) {
					$pattern = '/^' . str_replace( '/', '\/', $regex ) . '$/';
					return preg_match( $pattern, $text );
				}
			) );
		$this->formatChecker = new FormatChecker(
			$this->getConstraintParameterParser(),
			$this->getDefaultConfig(),
			$sparqlHelper
		);
	}

	public function testFormatConstraintImdb() {
		$pattern = '(tt|nm|ch|co|ev)\d{7}';

		$value1 = new StringValue( 'nm0001398' );
		$value2 = new StringValue( 'tt1234567' );
		$value3 = new StringValue( 'ch7654321' );
		$value4 = new StringValue( 'ev7777777' );
		$value5 = new StringValue( 'nm88888888' );
		$value6 = new StringValue( 'nmabcdefg' );
		$value7 = new StringValue( 'ab0001398' );
		$value8 = new StringValue( '123456789' );
		$value9 = new StringValue( 'nm000139' );
		$value10 = new StringValue( 'nmnm0001398' );

		$snak1 = new PropertyValueSnak( new PropertyId( 'P345' ), $value1 );
		$snak2 = new PropertyValueSnak( new PropertyId( 'P345' ), $value2 );
		$snak3 = new PropertyValueSnak( new PropertyId( 'P345' ), $value3 );
		$snak4 = new PropertyValueSnak( new PropertyId( 'P345' ), $value4 );
		$snak5 = new PropertyValueSnak( new PropertyId( 'P345' ), $value5 );
		$snak6 = new PropertyValueSnak( new PropertyId( 'P345' ), $value6 );
		$snak7 = new PropertyValueSnak( new PropertyId( 'P345' ), $value7 );
		$snak8 = new PropertyValueSnak( new PropertyId( 'P345' ), $value8 );
		$snak9 = new PropertyValueSnak( new PropertyId( 'P345' ), $value9 );
		$snak10 = new PropertyValueSnak( new PropertyId( 'P345' ), $value10 );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak1 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak2 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak3 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak4 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak5 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak6 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak7 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak8 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak9 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak10 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );
	}

	public function testFormatConstraintTaxonName() {
		$pattern = '(|somevalue|novalue|.*virus.*|.*viroid.*|.*phage.*|((×)?[A-Z]([a-z]+-)?[a-z]+('
			. '( [A-Z]?[a-z]+)|'
			. '( ([a-z]+-)?([a-z]+-)?[a-z]+)|'
			. '( ×([a-z]+-)?([a-z]+-)?([a-z]+-)?([a-z]+-)?[a-z]+)|'
			. '( \([A-Z][a-z]+\) [a-z]+)|'
			. "( (‘|')[A-Z][a-z]+(('|’)s)?( de)?( [A-Z][a-z]+(-([A-Z])?[a-z]+)*)*('|’)*)|"
			. '( ×| Group| (sub)?sp\.| (con)?(sub)?(notho)?var\.| (sub)?ser\.| (sub)?sect\.|'
			. ' subg\.| (sub)?f\.))*))';

		$value1 = new StringValue( 'Populus × canescens' );
		$value2 = new StringValue( 'Encephalartos friderici-guilielmi' );
		$value3 = new StringValue( 'Eruca vesicaria subsp. sativa' );
		$value4 = new StringValue( 'Euxoa (Chorizagrotis) lidia' );
		$value5 = new StringValue( 'Hepatitis A' );
		$value6 = new StringValue( 'Symphysodon (Cichlidae)' );
		$value7 = new StringValue( 'eukaryota' );
		$value8 = new StringValue( 'Plantago maritima agg.' );
		$value9 = new StringValue( 'Deinococcus-Thermus' );
		$value10 = new StringValue( 'Escherichia coli O157:H7' );

		$snak1 = new PropertyValueSnak( new PropertyId( 'P345' ), $value1 );
		$snak2 = new PropertyValueSnak( new PropertyId( 'P345' ), $value2 );
		$snak3 = new PropertyValueSnak( new PropertyId( 'P345' ), $value3 );
		$snak4 = new PropertyValueSnak( new PropertyId( 'P345' ), $value4 );
		$snak5 = new PropertyValueSnak( new PropertyId( 'P345' ), $value5 );
		$snak6 = new PropertyValueSnak( new PropertyId( 'P345' ), $value6 );
		$snak7 = new PropertyValueSnak( new PropertyId( 'P345' ), $value7 );
		$snak8 = new PropertyValueSnak( new PropertyId( 'P345' ), $value8 );
		$snak9 = new PropertyValueSnak( new PropertyId( 'P345' ), $value9 );
		$snak10 = new PropertyValueSnak( new PropertyId( 'P345' ), $value10 );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak1 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak2 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak3 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak4 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak5 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak6 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak7 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak8 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak9 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak10 ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );
	}

	public function testFormatConstraintWithSyntaxClarification() {
		$syntaxClarificationId = $this->getDefaultConfig()
			->get( 'WBQualityConstraintsSyntaxClarificationId' );
		$statement = NewStatement::forProperty( $syntaxClarificationId )
			->withValue( '' )
			->build();

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak() ),
			$this->getConstraintMock( array_merge(
				$this->formatParameter( '.+' ),
				$this->syntaxClarificationParameter( 'en', 'nonempty' )
			) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-format-clarification' );
	}

	public function testFormatConstraintNoStringValue() {
		$pattern = '(|somevalue|novalue|.*virus.*|.*viroid.*|.*phage.*|((×)?[A-Z]([a-z]+-)?[a-z]+('
			. '( [A-Z]?[a-z]+)|'
			. '( ([a-z]+-)?([a-z]+-)?[a-z]+)|'
			. '( ×([a-z]+-)?([a-z]+-)?([a-z]+-)?([a-z]+-)?[a-z]+)|'
			. '( \([A-Z][a-z]+\) [a-z]+)|'
			. "( (‘|')[A-Z][a-z]+(('|’)s)?( de)?( [A-Z][a-z]+(-([A-Z])?[a-z]+)*)*('|’)*)|"
			. '( ×| Group| (sub)?sp\.| (con)?(sub)?(notho)?var\.| (sub)?ser\.| (sub)?sect\.|'
			. ' subg\.| (sub)?f\.))*))';

		$value = new EntityIdValue( new ItemId( 'Q1' ) );
		$snak = new PropertyValueSnak( new PropertyId( 'P345' ), $value );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertViolation( $result );
	}

	public function testFormatConstraintNoValueSnak() {
		$pattern = ".";
		$snak = new PropertyNoValueSnak( new PropertyId( 'P1' ) );

		$result = $this->formatChecker->checkConstraint(
			new FakeSnakContext( $snak ),
			$this->getConstraintMock( $this->formatParameter( $pattern ) )
		);
		$this->assertCompliance( $result );
	}

	public function testFormatConstraintWithoutSparql() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( '' ) );
		$constraint = $this->getConstraintMock( $this->formatParameter( '.' ) );
		$checker = new FormatChecker(
			$this->getConstraintParameterParser(),
			$this->getDefaultConfig(),
			new DummySparqlHelper()
		);

		$result = $checker->checkConstraint(
			new FakeSnakContext( $snak ),
			$constraint
		);

		$this->assertTodoViolation( $result );
	}

	public function testFormatConstraintDisabled() {
		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( '' ) );
		$constraint = $this->getConstraintMock( $this->formatParameter( '.' ) );
		$sparqlHelper = $this->getMockBuilder( SparqlHelper::class )
					  ->disableOriginalConstructor()
					  ->setMethods( [ 'matchesRegularExpression' ] )
					  ->getMock();
		$sparqlHelper->expects( $this->never() )->method( 'matchesRegularExpression' );
		$checker = new FormatChecker(
			$this->getConstraintParameterParser(),
			new HashConfig( [ 'WBQualityConstraintsCheckFormatConstraint' => false ] ),
			$sparqlHelper
		);

		$result = $checker->checkConstraint(
			new FakeSnakContext( $snak ),
			$constraint
		);

		$this->assertTodo( $result );
	}

	public function testFormatConstraintDeprecatedStatement() {
		$statement = NewStatement::forProperty( 'P1' )
				   ->withValue( 'abc' )
				   ->withDeprecatedRank()
				   ->build();
		$constraint = $this->getConstraintMock( $this->formatParameter( 'a.b.' ) );
		$entity = NewItem::withId( 'Q1' )
				->build();

		$checkResult = $this->formatChecker->checkConstraint(
			new MainSnakContext( $entity, $statement ),
			$constraint
		);

		// this constraint is still checked on deprecated statements
		$this->assertViolation( $checkResult, 'wbqc-violation-message-format-clarification' );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->formatChecker->checkConstraintParameters( $constraint );

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
			 ->will( $this->returnValue( 'Q21502404' ) );

		return $mock;
	}

}
