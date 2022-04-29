<?php

namespace WikibaseQuality\ConstraintReport\Tests;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Tests\Store\Sql\Terms\Util\FakeLoadBalancer;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintRepositoryLookup;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintRepositoryLookup
 *
 * @group WikibaseQualityConstraints
 * @group database
 * @group medium
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class ConstraintRepositoryLookupTest extends \MediaWikiTestCase {

	private function newConstraintRepositoryLookup() {
		return new ConstraintRepositoryLookup(
			new FakeLoadBalancer( [ 'dbr' => $this->db ] ),
			false
		);
	}

	public function testQueryConstraintsForExistingProperty() {
		$this->insertTestData();

		$repo = $this->newConstraintRepositoryLookup();
		$constraints = $repo->queryConstraintsForProperty( new PropertyId( 'P1' ) );

		$this->assertIsArray( $constraints );
		$this->assertEquals( 2, count( $constraints ) );
		$this->assertInstanceOf( Constraint::class, $constraints[0] );
	}

	public function testQueryConstraintsForNonExistingProperty() {
		$this->insertTestData();

		$repo = $this->newConstraintRepositoryLookup();
		$constraints = $repo->queryConstraintsForProperty( new PropertyId( 'P2' ) );

		$this->assertIsArray( $constraints );
		$this->assertEmpty( $constraints );
	}

	public function testQueryConstraintsForPropertyBrokenParameters() {
		$this->db->delete( 'wbqc_constraints', '*' );
		$this->db->insert( 'wbqc_constraints', [
			[
				'constraint_guid' => 'P3$514751bb-1656-4d2d-a386-b0f0a69e02ed',
				'pid' => 3,
				'constraint_type_qid' => 'Multi value',
				'constraint_parameters' => 'this is not valid JSON'
			]
		] );

		$repo = $this->newConstraintRepositoryLookup();
		$constraints = $repo->queryConstraintsForProperty( new PropertyId( 'P3' ) );

		$this->assertSame( [ '@error' => [] ], $constraints[0]->getConstraintParameters() );
	}

	private function insertTestData() {
		$this->db->delete( 'wbqc_constraints', '*' );
		$this->db->insert( 'wbqc_constraints', [
			[
				'constraint_guid' => '1',
				'pid' => 1,
				'constraint_type_qid' => 'Multi value',
				'constraint_parameters' => '{}'
			],
			[
				'constraint_guid' => '3',
				'pid' => 1,
				'constraint_type_qid' => 'Single value',
				'constraint_parameters' => '{}'
			],
		] );
	}

}
