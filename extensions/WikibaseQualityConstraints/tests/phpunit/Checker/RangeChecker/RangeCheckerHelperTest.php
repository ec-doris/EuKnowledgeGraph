<?php

namespace WikibaseQuality\ConstraintReport\Tests\Checker\RangeChecker;

use DataValues\DataValue;
use DataValues\DecimalValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use InvalidArgumentException;
use Wikibase\Lib\Units\CSVUnitStorage;
use Wikibase\Lib\Units\UnitConverter;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper;
use WikibaseQuality\ConstraintReport\Tests\DefaultConfig;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\RangeCheckerHelper
 *
 * @group WikibaseQualityConstraints
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class RangeCheckerHelperTest extends \PHPUnit\Framework\TestCase {

	use DefaultConfig;

	/**
	 * @return RangeCheckerHelper
	 */
	private function getRangeCheckerHelper() {
		return new RangeCheckerHelper(
			$this->getDefaultConfig(),
			new UnitConverter(
				new CSVUnitStorage( __DIR__ . '/units.csv' ),
				''
			)
		);
	}

	/**
	 * @dataProvider provideComparisons
	 */
	public function testGetComparison( $expected, DataValue $lhs, DataValue $rhs ) {
		$this->assertContains( $expected, [ -1, 0, 1 ], '$expected must be -1, 0, or 1' );
		$rangeCheckHelper = $this->getRangeCheckerHelper();
		$actual = $rangeCheckHelper->getComparison( $lhs, $rhs );
		switch ( $expected ) {
			case -1:
				$this->assertLessThan( 0, $actual );
				break;
			case 0:
				$this->assertSame( 0, $actual );
				break;
			case 1:
				$this->assertGreaterThan( 0, $actual );
				break;
		}
	}

	public function provideComparisons() {
		$cases = [
			[ -1, $this->getTimeValue( 1970 ), $this->getTimeValue( 1971 ) ],
			[ 0, $this->getTimeValue( 1970 ), $this->getTimeValue( 1970 ) ],
			[ 1, $this->getTimeValue( 1971 ), $this->getTimeValue( 1970 ) ],
			[ -1, $this->getQuantityValue( 42.0 ), $this->getQuantityValue( 1337.0 ) ],
			[ 0, $this->getQuantityValue( 42.0 ), $this->getQuantityValue( 42.0 ) ],
			[ 1, $this->getQuantityValue( 1337.0 ), $this->getQuantityValue( 42.0 ) ],
			[ -1, $this->getUnboundedQuantityValue( 42.0 ), $this->getUnboundedQuantityValue( 1337.0 ) ],
			[ 0, $this->getUnboundedQuantityValue( 42.0 ), $this->getUnboundedQuantityValue( 42.0 ) ],
			[ 1, $this->getUnboundedQuantityValue( 1337.0 ), $this->getUnboundedQuantityValue( 42.0 ) ],
			[ -1, $this->getQuantityValue( 500.0, 'g' ), $this->getQuantityValue( 1.0, 'kg' ) ],
			[ 0, $this->getQuantityValue( 1000.0, 'g' ), $this->getQuantityValue( 1.0, 'kg' ) ],
			[ 1, $this->getQuantityValue( 1.5, 'kg' ), $this->getQuantityValue( 1000, 'g' ) ],
		];

		return $cases;
	}

	/**
	 * @dataProvider provideDifferences
	 */
	public function testGetDifference( $expected, DataValue $minuend, DataValue $subtrahend ) {
		$rangeCheckerHelper = $this->getRangeCheckerHelper();
		$diff = $rangeCheckerHelper->getDifference( $minuend, $subtrahend );
		$actual = $diff->getAmount()->getValueFloat();
		$this->assertSame( (float)$expected, $actual );
	}

	public function provideDifferences() {
		$secondsPerYear = 60 * 60 * 24 * 365;
		$secondsPerLeapYear = 60 * 60 * 24 * 366;
		$cases = [
			'negative year difference, no leap year' => [
				-$secondsPerYear, $this->getTimeValue( 1970 ), $this->getTimeValue( 1971 )
			],
			'positive year difference, no leap year' => [
				$secondsPerYear, $this->getTimeValue( 1971 ), $this->getTimeValue( 1970 )
			],
			'negative year difference, leap year' => [
				-$secondsPerLeapYear, $this->getTimeValue( 1972 ), $this->getTimeValue( 1973 )
			],
			'positive year difference, leap year' => [
				$secondsPerLeapYear, $this->getTimeValue( 1973 ), $this->getTimeValue( 1972 )
			],
			'positive year difference, leap year, excluding leap day' => [
				$secondsPerYear, $this->getTimeValue( 1973, 6 ), $this->getTimeValue( 1972, 6 )
			],
			'negative year difference, across epoch (1 BCE is leap year)' => [
				-$secondsPerLeapYear, $this->getTimeValue( -1 ), $this->getTimeValue( 1 )
			],
			'positive year difference, across epoch (1 BCE is leap year)' => [
				$secondsPerLeapYear, $this->getTimeValue( 1 ), $this->getTimeValue( -1 )
			],
			'negative year difference, before Common Era' => [
				-$secondsPerYear, $this->getTimeValue( -1971 ), $this->getTimeValue( -1970 )
			],
			'positive year difference, before Common Era' => [
				$secondsPerYear, $this->getTimeValue( -1970 ), $this->getTimeValue( -1971 )
			],
			'negative quantity difference' => [
				-1295, $this->getQuantityValue( 42.0 ), $this->getQuantityValue( 1337.0 )
			],
			'positive quantity difference' => [
				1295, $this->getQuantityValue( 1337.0 ), $this->getQuantityValue( 42.0 )
			],
			'negative quantity difference, bounded/unbounded' => [
				-1295, $this->getQuantityValue( 42.0 ), $this->getUnboundedQuantityValue( 1337.0 )
			],
			'negative quantity difference, unbounded/bounded' => [
				-1295, $this->getUnboundedQuantityValue( 42.0 ), $this->getQuantityValue( 1337.0 )
			],
			'negative quantity difference, gram/kilogram' => [
				-0.5, $this->getQuantityValue( 500.0, 'g' ), $this->getQuantityValue( 1.0, 'kg' )
			],
			'positive quantity difference, gram/gram' => [
				1.0, $this->getQuantityValue( 2000.0, 'g' ), $this->getQuantityValue( 1000, 'g' )
			],
		];

		return $cases;
	}

	/**
	 * @dataProvider provideDifferencesInYears
	 */
	public function testGetDifferenceInYears(
		$minExpected,
		$maxExpected,
		TimeValue $subtrahend,
		TimeValue $minuend
	) {
		$rangeCheckerHelper = $this->getRangeCheckerHelper();

		$diff = $rangeCheckerHelper->getDifferenceInYears( $minuend, $subtrahend );
		$actual = $diff->getAmount()->getValueFloat();

		// $minExpected ≤ $actual ≤ $maxExpected
		$this->assertGreaterThanOrEqual( $minExpected, $actual );
		$this->assertLessThanOrEqual( $maxExpected, $actual );
	}

	public function provideDifferencesInYears() {
		$spring74 = $this->getTimeValue( 1974, 04, 01 );
		$fall74 = $this->getTimeValue( 1974, 10, 01 );
		$spring75 = $this->getTimeValue( 1975, 04, 01 );
		$fall75 = $this->getTimeValue( 1975, 10, 01 );

		$cases = [
			'spring ’74 to spring ’74' => [ 0, 0, $spring74, $spring74 ],
			'spring ’74 to fall ’74' => [ 0.1, 0.9, $spring74, $fall74 ],
			'spring ’74 to spring ’75' => [ 1, 1, $spring74, $spring75 ],
			'spring ’74 to fall ’75' => [ 1.1, 1.9, $spring74, $fall75 ],
			'fall ’74 to spring ’74' => [ -0.9, -0.1, $fall74, $spring74 ],
			'fall ’74 to fall ’74' => [ 0, 0, $fall74, $fall74 ],
			'fall ’74 to spring ’75' => [ 0.1, 0.9, $fall74, $spring75 ],
			'fall ’74 to fall ’75' => [ 1, 1, $fall74, $fall75 ],
			'spring ’75 to spring ’74' => [ -1, -1, $spring75, $spring74 ],
			'spring ’75 to fall ’74' => [ -0.9, -0.1, $spring75, $fall74 ],
			'spring ’75 to spring ’75' => [ 0, 0, $spring75, $spring75 ],
			'spring ’75 to fall ’75' => [ 0.1, 0.9, $spring75, $fall75 ],
			'fall ’75 to spring ’74' => [ -1.9, -1.1, $fall75, $spring74 ],
			'fall ’75 to fall ’74' => [ -1, -1, $fall75, $fall74 ],
			'fall ’75 to spring ’75' => [ -0.9, -0.1, $fall75, $spring75 ],
			'fall ’75 to fall ’75' => [ 0, 0, $fall75, $fall75 ],
			'spring 1 BCE to fall 1 CE' => [
				1.1, 1.9, $this->getTimeValue( -1, 04, 01 ), $this->getTimeValue( 1, 10, 01 )
			],
			'spring 10 CE to fall 10 BCE' => [
				-18.9, -18.1, $this->getTimeValue( 10, 04, 01 ), $this->getTimeValue( -10, 10, 01 )
			],
		];

		return $cases;
	}

	public function testGetComparison_unsupportedDataValueTypeThrowsException() {
		$rangeCheckerHelper = $this->getRangeCheckerHelper();
		$this->expectException( InvalidArgumentException::class );
		$rangeCheckerHelper->getComparison( new StringValue( 'kittens' ), new StringValue( 'puppies' ) );
	}

	public function testGetComparison_differingDataValueTypeThrowsException() {
		$rangeCheckerHelper = $this->getRangeCheckerHelper();
		$this->expectException( InvalidArgumentException::class );
		$rangeCheckerHelper->getComparison( $this->getQuantityValue( 42.0 ), $this->getTimeValue( 1970 ) );
	}

	public function testIsFutureTime() {
		$rangeCheckerHelper = $this->getRangeCheckerHelper();
		$past = $this->getTimeValue( 2012, 10, 29 );

		$this->assertFalse( $rangeCheckerHelper->isFutureTime( $past ) );
	}

	/**
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 *
	 * @return TimeValue
	 */
	private function getTimeValue( $year, $month = 1, $day = 1 ) {
		$yearString = $year > 0 ? "+$year" : "$year";
		$monthString = $month < 10 ? "0$month" : "$month";
		$dayString = $day < 10 ? "0$day" : "$day";
		return new TimeValue(
			"{$yearString}-{$monthString}-{$dayString}T00:00:00Z",
			0,
			0,
			0,
			TimeValue::PRECISION_DAY,
			TimeValue::CALENDAR_GREGORIAN
		);
	}

	/**
	 * @param float $amount
	 * @param string $unit
	 *
	 * @return QuantityValue
	 */
	private function getQuantityValue( $amount, $unit = '1' ) {
		$decimalValue = new DecimalValue( $amount );

		return new QuantityValue( $decimalValue, $unit, $decimalValue, $decimalValue );
	}

	/**
	 * @param float $amount
	 * @param string $unit
	 *
	 * @return UnboundedQuantityValue
	 */
	private function getUnboundedQuantityValue( $amount, $unit = '1' ) {
		return UnboundedQuantityValue::newFromNumber( $amount, $unit );
	}

}
