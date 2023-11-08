<?php

namespace MediaWiki\Extension\BatchIngestion;

ini_set('max_execution_time', '0');

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\User\UserFactory;
use User;

/**
 * @license GPL-2.0-or-later
 * @author Quentin Januel < quentin.januel@the-qa-company.com >
 */
class BatchApi extends Handler {
    /** @var UserFactory */
    private $userFactory;
    public function __construct() {
        $services = MediaWikiServices::getInstance();
        $this->userFactory = $services->getUserFactory();
    }
    /**
     * This function is needed to filter bad parameters from the request.
     * I simply let it always work for now since I use the entityDeserializer
     * in a try catch block to filter out bad entities.
     * @param string $contentType
     * @return BodyValidator
     */
    public function getBodyValidator( $contentType ) {
        return new JsonBodyValidator( [] );
    }
    /**
     * Execute the request.
     * @return Response
     */
    public function execute() {
        $authority = $this->getAuthority();
        $user = $this->userFactory->newFromAuthority( $authority );
        $isConnected = $user->isRegistered();
        if (!$isConnected) {
            return $this
                ->getResponseFactory()
                ->createHttpError(400, [
                    'error' => 'You must be connected to use this API',
                ]);
        }
        $groups = $user->getGroups();
        $allowedGroup = $GLOBALS['wgBatchIngestionAllowedGroup'];
        $allowed = false;
        foreach ($groups as $group) {
            if ($group != $allowedGroup)
                continue;
            $allowed = true;
            break;
        }
        if (!$allowed) {
            return $this
                ->getResponseFactory()
                ->createHttpError(400, [
                    'error' => 'You have no permission to use this API, you must be in the group "' . $allowedGroup . '".'
                ]);
        }
        $body = $this->getValidatedBody();
        $ingester = new BatchIngestion($user, $body);
        try {
            $response = $ingester->run();
            // $logs = $ingester->getLogs();
            // $response['logs'] = $logs;
            return $this
                ->getResponseFactory()
                ->createJson($response);
        } catch (\Exception $e) {
            $line = $e->getLine();
            $file = $e->getFile();
            $message = $e->getMessage();
            $error = "Error in $file (line $line):\n$message";
            // $logs = $ingester->getLogs();
            return $this
                ->getResponseFactory()
                ->createHttpError(400, [
                    'error' => $error,
                    // 'logs' => $logs,
                ]);
        }
    }
}
