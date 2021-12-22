<?php

namespace WikibaseQuality\ConstraintReport\Tests\Maintenance;

use HashConfig;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikibase\Lib\Tests\Store\MockPropertyInfoLookup;
use WikibaseQuality\ConstraintReport\Job\UpdateConstraintsTableJob;
use WikibaseQuality\ConstraintReport\Maintenance\ImportConstraintStatements;

/**
 * @covers \WikibaseQuality\ConstraintReport\Maintenance\ImportConstraintStatements
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class ImportConstraintStatementsTest extends MaintenanceBaseTestCase {

	public function getMaintenanceClass() {
		return ImportConstraintStatements::class;
	}

	public function testConstraintStatementsDisabled() {
		$this->maintenance->setConfig( new HashConfig( [
			'WBQualityConstraintsEnableConstraintsImportFromStatements' => false,
		] ) );

		$this->maintenance->execute();

		$this->expectOutputString( 'Constraint statements are not enabled. Aborting.' );
	}

	public function testNoProperties() {
		$this->maintenance->setupServices = function () {
			$this->maintenance->propertyInfoLookup = new MockPropertyInfoLookup( [] );
		};
		$this->maintenance->newUpdateConstraintsTableJob = function () {
			$this->fail( 'newUpdateConstraintsTableJob should not be called' );
		};

		$this->maintenance->execute();

		$this->expectOutputString( '' );
	}

	public function testTwoProperties() {
		$this->maintenance->setupServices = function () {
			$this->maintenance->propertyInfoLookup = new MockPropertyInfoLookup( [
				'P1' => [],
				'P3' => [],
			] );
		};

		$call = 0;
		$this->maintenance->newUpdateConstraintsTableJob = function ( $propertyIdSerialization ) use ( &$call ) {
			$mock = $this->getMockBuilder( UpdateConstraintsTableJob::class )
				->disableOriginalConstructor()
				->getMock();
			$mock->expects( $this->once() )
				->method( 'run' )
				->with();
			switch ( ++$call ) {
				case 1:
					$this->assertSame( 'P1', $propertyIdSerialization );
					return $mock;
				case 2:
					$this->assertSame( 'P3', $propertyIdSerialization );
					return $mock;
				default:
					$this->fail( 'newUpdateConstraintsTableJob should only be called twice' );
					return $mock; // unreachable but just in case
			}
		};

		$this->maintenance->execute();

		$this->expectOutputRegex( '/^' .
			'Importing constraint statements for +P1... done in +\d+\.\d+ ms.\n' .
			'Importing constraint statements for +P3... done in +\d+\.\d+ ms.\n' .
			'$/' );
		$this->assertSame( 2, $call, 'newUpdateConstraintsTableJob should have been called twice' );
	}

	public function testDefaultNewUpdateConstraintsTableJob() {
		$job = call_user_func( $this->maintenance->newUpdateConstraintsTableJob, 'P1234' );

		$this->assertInstanceOf( UpdateConstraintsTableJob::class, $job );
		$params = $job->getParams();
		$this->assertArrayHasKey( 'propertyId', $params );
		$this->assertSame( 'P1234', $params['propertyId'] );
	}

}
