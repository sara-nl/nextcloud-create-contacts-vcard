<?php

declare(strict_types=1);

namespace OCA\VCardAPI\Controller;

use OCA\VCardAPI\AppInfo\Application;
use OCA\VCardAPI\Service\VCardService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController as BaseOCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class OCSController extends BaseOCSController
{
    public function __construct(
        IRequest $request,
        private VCardService $vcardService,
        private LoggerInterface $logger
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * GET /api/v1/contacts
     * Get all contacts, optionally filtered by user_id
     *
     * @AdminRequired
     * @NoCSRFRequired
     *
     * @param string|null $user_id Filter by user ID
     * @param int $limit Maximum results (default 100)
     * @param int $offset Pagination offset
     * @return DataResponse
     */
    public function getAllContacts(?string $user_id = null, int $limit = 100, int $offset = 0): DataResponse
    {
        try {
            $result = $this->vcardService->getAllContacts($user_id, $limit, $offset);
            return new DataResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get contacts', [
                'exception' => $e->getMessage(),
            ]);
            return new DataResponse(
                ['message' => 'Failed to get contacts: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * POST /api/v1/contacts
     * Create a new contact in a user's address book
     *
     * @AdminRequired
     * @NoCSRFRequired
     *
     * @param string $user_id Target user ID
     * @param string $displayname Contact display name
     * @param string $email Contact email
     * @param string $cloud_id Federated Cloud ID
     * @param string|null $organization Organization (optional)
     * @return DataResponse
     */
    public function createContact(
        string $user_id,
        string $displayname,
        string $email,
        string $cloud_id,
        ?string $organization = null
    ): DataResponse {
        // Validate required fields
        if (empty($user_id) || empty($displayname) || empty($email) || empty($cloud_id)) {
            return new DataResponse(
                ['message' => 'Missing required fields: user_id, displayname, email, cloud_id'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $result = $this->vcardService->createVCard(
                $user_id,
                $displayname,
                $email,
                $cloud_id,
                $organization
            );

            return new DataResponse($result, Http::STATUS_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_NOT_FOUND
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to create contact', [
                'exception' => $e->getMessage(),
                'user_id' => $user_id,
            ]);
            return new DataResponse(
                ['message' => 'Failed to create contact: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * GET /api/v1/contacts/{userId}
     * Get contacts for a specific user
     *
     * @AdminRequired
     * @NoCSRFRequired
     *
     * @param string $userId User ID
     * @return DataResponse
     */
    public function listUserContacts(string $userId): DataResponse
    {
        try {
            $contacts = $this->vcardService->getUserContacts($userId);
            return new DataResponse([
                'vcards' => $contacts,
                'total' => count($contacts),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user contacts', [
                'exception' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return new DataResponse(
                ['message' => 'Failed to get contacts: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * GET /api/v1/contacts/{userId}/{uid}
     * Get a specific contact
     *
     * @AdminRequired
     * @NoCSRFRequired
     *
     * @param string $userId User ID
     * @param string $uid Contact UID
     * @return DataResponse
     */
    public function getContact(string $userId, string $uid): DataResponse
    {
        try {
            $contact = $this->vcardService->getContact($userId, $uid);

            if ($contact === null) {
                return new DataResponse(
                    ['message' => 'Contact not found'],
                    Http::STATUS_NOT_FOUND
                );
            }

            return new DataResponse($contact);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get contact', [
                'exception' => $e->getMessage(),
                'user_id' => $userId,
                'uid' => $uid,
            ]);
            return new DataResponse(
                ['message' => 'Failed to get contact: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * DELETE /api/v1/contacts/{userId}/{uid}
     * Delete a contact
     *
     * @AdminRequired
     * @NoCSRFRequired
     *
     * @param string $userId User ID
     * @param string $uid Contact UID
     * @return DataResponse
     */
    public function deleteContact(string $userId, string $uid): DataResponse
    {
        try {
            $deleted = $this->vcardService->deleteContact($userId, $uid);

            if (!$deleted) {
                return new DataResponse(
                    ['message' => 'Contact not found'],
                    Http::STATUS_NOT_FOUND
                );
            }

            return new DataResponse(['message' => 'Contact deleted successfully']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete contact', [
                'exception' => $e->getMessage(),
                'user_id' => $userId,
                'uid' => $uid,
            ]);
            return new DataResponse(
                ['message' => 'Failed to delete contact: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * PUT /api/v1/contacts/{userId}/{uid}
     * Update a contact
     *
     * @AdminRequired
     * @NoCSRFRequired
     *
     * @param string $userId User ID
     * @param string $uid Contact UID
     * @param string $displayname New display name
     * @param string $email New email
     * @param string $cloud_id New cloud ID
     * @param string|null $organization New organization
     * @return DataResponse
     */
    public function updateContact(
        string $userId,
        string $uid,
        string $displayname,
        string $email,
        string $cloud_id,
        ?string $organization = null
    ): DataResponse {
        // Validate required fields
        if (empty($displayname) || empty($email) || empty($cloud_id)) {
            return new DataResponse(
                ['message' => 'Missing required fields: displayname, email, cloud_id'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $result = $this->vcardService->updateContact(
                $userId,
                $uid,
                $displayname,
                $email,
                $cloud_id,
                $organization
            );

            if ($result === null) {
                return new DataResponse(
                    ['message' => 'Contact not found'],
                    Http::STATUS_NOT_FOUND
                );
            }

            return new DataResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update contact', [
                'exception' => $e->getMessage(),
                'user_id' => $userId,
                'uid' => $uid,
            ]);
            return new DataResponse(
                ['message' => 'Failed to update contact: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
