<?php

namespace MediaWiki\Extension\BatchIngestion;

use RuntimeException;
use Wikibase\Lib\Rdbms\RepoDomainDb;
use Wikibase\Repo\Store\IdGenerator;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Unique batch Id generator implemented using an SQL table.
 * The table needs to have the fields id_value and id_type.
 *
 * @license GPL-2.0-or-later
 * @author Quentin Januel < quentin.januel@the-qa-company.com >
 */
class BatchIdGenerator implements IdGenerator {
    /** @var RepoDomainDb */
    private $db;
    /** @var int[][] */
    private $reservedIds;
    /** @var bool */
    private $separateDbConnection;
    /**
     * @param RepoDomainDb $db
     * @param int[][] $reservedIds
     * @param bool $separateDbConnection
     */
    public function __construct(
        RepoDomainDb $db,
        array $reservedIds = [],
        $separateDbConnection = false
    ) {
        $this->db = $db;
        $this->reservedIds = $reservedIds;
        $this->separateDbConnection = $separateDbConnection;
    }
    /**
     * @param string $type normally is content model id (e.g. wikibase-item or wikibase-property)
     * @param int $count number of ids to generate
     *
     * @throws RuntimeException if getting an unique ID failed
     * @return int[]
     */
    public function getNewIds( $type, $count ) {
        $flags = ( $this->separateDbConnection === true ) ? ILoadBalancer::CONN_TRX_AUTOCOMMIT : 0;
        $database = $this->db->connections()->getWriteConnection( $flags );
        $ids = $this->generateNewIds( $database, $type, $count );
        $this->db->connections()->releaseConnection( $database );
        return $ids;
    }
    /**
     * @param string $type normally is content model id (e.g. wikibase-item or wikibase-property)
     * @throws RuntimeException if getting an unique ID failed
     * @return int
     */
    public function getNewId( $type ) {
        return $this->getNewIds( $type, 1 )[0];
    }
    /**
     * Generates and returns a bunch of IDs.
     *
     * @param IDatabase $database
     * @param string $type
     * @param int $count
     * @param bool $retry Retry once in case of e.g. race conditions. Defaults to true.
     *
     * @throws RuntimeException
     * @return int[]
     */
    private function generateNewIds( IDatabase $database, $type, $count, $retry = true ) {
        $database->startAtomic( __METHOD__ );
        $currentId = $database->selectRow(
            'wb_id_counters',
            'id_value',
            [ 'id_type' => $type ],
            __METHOD__,
            [ 'FOR UPDATE' ]
        );
        if ( is_object( $currentId ) ) {
            $id = $currentId->id_value + $count;
            $success = $database->update(
                'wb_id_counters',
                [ 'id_value' => $id ],
                [ 'id_type' => $type ],
                __METHOD__
            );
        } else {
            $id = $count;
            $success = $database->insert(
                'wb_id_counters',
                [
                    'id_value' => $id,
                    'id_type' => $type,
                ],
                __METHOD__
            );
            // Retry once, since a race condition on initial insert can cause one to fail.
            // Race condition is possible due to occurrence of phantom reads is possible
            // at non serializable transaction isolation level.
            if ( !$success && $retry ) {
                $id = $this->generateNewIds( $database, $type, $count, false );
                $success = true;
            }
        }
        $database->endAtomic( __METHOD__ );
        if ( !$success ) {
            throw new RuntimeException( 'Could not generate reliably unique IDs.' );
        }
        $ids = range( $id - $count + 1, $id );
        if ( array_key_exists( $type, $this->reservedIds ) ) {
            $failed = 0;
            $success = array();
            foreach ( $ids as $id ) {
                if ( in_array( $id, $this->reservedIds[$type] ) ) {
                    $failed++;
                } else {
                    $success[] = $id;
                }
            }
            $news = $this->generateNewIds( $database, $type, $failed );
            $ids = array_merge( $success, $news );
        }
        return $ids;
    }
}
