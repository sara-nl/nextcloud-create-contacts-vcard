<?php

declare(strict_types=1);

namespace OCA\SurfVcard\Service;

use OCA\DAV\CardDAV\CardDavBackend;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

class VCardService
{
    public function __construct(
        private CardDavBackend $cardDavBackend,
        private IUserManager $userManager,
        private ISecureRandom $secureRandom,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a VCard in a user's address book
     *
     * @param string $userId Target user ID
     * @param string $displayname Contact display name
     * @param string $email Contact email address
     * @param string $cloudId Federated Cloud ID
     * @param string|null $organization Organization (optional)
     * @return array Created VCard info
     * @throws \InvalidArgumentException If user not found
     * @throws \Exception On CardDAV errors
     */
    public function createVCard(
        string $userId,
        string $displayname,
        string $email,
        string $cloudId,
        ?string $organization = null
    ): array {
        // Verify user exists
        $user = $this->userManager->get($userId);
        if ($user === null) {
            throw new \InvalidArgumentException("User not found: $userId");
        }

        // Get or create user's default address book
        $addressBookId = $this->getOrCreateDefaultAddressBook($userId);

        // Generate unique ID
        $uid = $this->generateUuid();

        // Generate VCard content
        $vcardContent = $this->generateVCard($uid, $displayname, $email, $cloudId, $organization);

        // Create card in CardDAV
        $this->cardDavBackend->createCard($addressBookId, "$uid.vcf", $vcardContent);

        $this->logger->info('VCard created', [
            'uid' => $uid,
            'user_id' => $userId,
            'displayname' => $displayname,
        ]);

        return [
            'uid' => $uid,
            'user_id' => $userId,
            'displayname' => $displayname,
            'email' => $email,
            'cloud_id' => $cloudId,
            'organization' => $organization,
        ];
    }

    /**
     * Get all contacts, optionally filtered by user
     *
     * @param string|null $userId Filter by user ID (null for all users)
     * @param int $limit Maximum number of results
     * @param int $offset Pagination offset
     * @return array List of contacts
     */
    public function getAllContacts(?string $userId = null, int $limit = 100, int $offset = 0): array
    {
        $contacts = [];

        if ($userId !== null) {
            // Get contacts for specific user
            $contacts = $this->getUserContacts($userId);
        } else {
            // Get contacts for all users (admin operation)
            $this->userManager->callForAllUsers(function ($user) use (&$contacts) {
                $userContacts = $this->getUserContacts($user->getUID());
                $contacts = array_merge($contacts, $userContacts);
            });
        }

        // Apply pagination
        $total = count($contacts);
        $contacts = array_slice($contacts, $offset, $limit);

        return [
            'vcards' => $contacts,
            'total' => $total,
        ];
    }

    /**
     * Get contacts for a specific user
     *
     * @param string $userId User ID
     * @return array List of contacts
     */
    public function getUserContacts(string $userId): array
    {
        $contacts = [];
        $principalUri = "principals/users/$userId";

        try {
            $addressBooks = $this->cardDavBackend->getAddressBooksForUser($principalUri);

            foreach ($addressBooks as $addressBook) {
                $cards = $this->cardDavBackend->getCards($addressBook['id']);

                foreach ($cards as $card) {
                    $parsed = $this->parseVCard($card['carddata']);
                    if ($parsed !== null) {
                        $parsed['user_id'] = $userId;
                        $parsed['address_book_id'] = $addressBook['id'];
                        $contacts[] = $parsed;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get contacts for user: ' . $userId, [
                'exception' => $e->getMessage(),
            ]);
        }

        return $contacts;
    }

    /**
     * Get a specific contact by UID
     *
     * @param string $userId User ID
     * @param string $uid Contact UID
     * @return array|null Contact data or null if not found
     */
    public function getContact(string $userId, string $uid): ?array
    {
        $principalUri = "principals/users/$userId";
        $addressBooks = $this->cardDavBackend->getAddressBooksForUser($principalUri);

        foreach ($addressBooks as $addressBook) {
            $card = $this->cardDavBackend->getCard($addressBook['id'], "$uid.vcf");
            if ($card !== false) {
                $parsed = $this->parseVCard($card['carddata']);
                if ($parsed !== null) {
                    $parsed['user_id'] = $userId;
                    $parsed['address_book_id'] = $addressBook['id'];
                    return $parsed;
                }
            }
        }

        return null;
    }

    /**
     * Delete a contact
     *
     * @param string $userId User ID
     * @param string $uid Contact UID
     * @return bool True if deleted, false if not found
     */
    public function deleteContact(string $userId, string $uid): bool
    {
        $principalUri = "principals/users/$userId";
        $addressBooks = $this->cardDavBackend->getAddressBooksForUser($principalUri);

        foreach ($addressBooks as $addressBook) {
            $card = $this->cardDavBackend->getCard($addressBook['id'], "$uid.vcf");
            if ($card !== false) {
                $this->cardDavBackend->deleteCard($addressBook['id'], "$uid.vcf");
                $this->logger->info('VCard deleted', [
                    'uid' => $uid,
                    'user_id' => $userId,
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Update a contact
     *
     * @param string $userId User ID
     * @param string $uid Contact UID
     * @param string $displayname New display name
     * @param string $email New email
     * @param string $cloudId New cloud ID
     * @param string|null $organization New organization
     * @return array|null Updated contact or null if not found
     */
    public function updateContact(
        string $userId,
        string $uid,
        string $displayname,
        string $email,
        string $cloudId,
        ?string $organization = null
    ): ?array {
        $principalUri = "principals/users/$userId";
        $addressBooks = $this->cardDavBackend->getAddressBooksForUser($principalUri);

        foreach ($addressBooks as $addressBook) {
            $card = $this->cardDavBackend->getCard($addressBook['id'], "$uid.vcf");
            if ($card !== false) {
                $vcardContent = $this->generateVCard($uid, $displayname, $email, $cloudId, $organization);
                $this->cardDavBackend->updateCard($addressBook['id'], "$uid.vcf", $vcardContent);

                $this->logger->info('VCard updated', [
                    'uid' => $uid,
                    'user_id' => $userId,
                ]);

                return [
                    'uid' => $uid,
                    'user_id' => $userId,
                    'displayname' => $displayname,
                    'email' => $email,
                    'cloud_id' => $cloudId,
                    'organization' => $organization,
                ];
            }
        }

        return null;
    }

    /**
     * Generate VCard content
     */
    private function generateVCard(
        string $uid,
        string $displayname,
        string $email,
        string $cloudId,
        ?string $organization
    ): string {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'PRODID:-//Nextcloud Surf-VCard//EN',
            "UID:$uid",
            'FN:' . $this->escapeVCardValue($displayname),
            'EMAIL;TYPE=INTERNET:' . $this->escapeVCardValue($email),
            'X-NEXTCLOUD-CLOUD-ID:' . $this->escapeVCardValue($cloudId),
            'CLOUD:' . $this->escapeVCardValue($cloudId),
        ];

        if ($organization !== null && $organization !== '') {
            $lines[] = 'ORG:' . $this->escapeVCardValue($organization);
        }

        $lines[] = 'REV:' . gmdate('Ymd\THis\Z');
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }

    /**
     * Escape special characters in VCard values
     */
    private function escapeVCardValue(string $value): string
    {
        // Escape backslashes, semicolons, commas, and newlines
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace("\n", '\\n', $value);
        return $value;
    }

    /**
     * Parse VCard content into array
     */
    private function parseVCard(string $vcardData): ?array
    {
        try {
            $vcard = Reader::read($vcardData);

            $uid = (string)($vcard->UID ?? '');
            $displayname = (string)($vcard->FN ?? '');
            $email = '';
            $cloudId = '';
            $organization = '';

            if (isset($vcard->EMAIL)) {
                $email = (string)$vcard->EMAIL;
            }

            if (isset($vcard->CLOUD)) {
                $cloudId = (string)$vcard->CLOUD;
            } elseif (isset($vcard->{'X-NEXTCLOUD-CLOUD-ID'})) {
                $cloudId = (string)$vcard->{'X-NEXTCLOUD-CLOUD-ID'};
            }

            if (isset($vcard->ORG)) {
                $organization = (string)$vcard->ORG;
            }

            return [
                'uid' => $uid,
                'displayname' => $displayname,
                'email' => $email,
                'cloud_id' => $cloudId,
                'organization' => $organization ?: null,
            ];
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse VCard', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get or create the default address book for a user
     */
    private function getOrCreateDefaultAddressBook(string $userId): int
    {
        $principalUri = "principals/users/$userId";
        $addressBooks = $this->cardDavBackend->getAddressBooksForUser($principalUri);

        // Find "contacts" address book (default)
        foreach ($addressBooks as $book) {
            if ($book['uri'] === 'contacts') {
                return $book['id'];
            }
        }

        // Return first address book if exists
        if (!empty($addressBooks)) {
            return $addressBooks[0]['id'];
        }

        // Create default address book
        return $this->cardDavBackend->createAddressBook(
            $principalUri,
            'contacts',
            ['{DAV:}displayname' => 'Contacts']
        );
    }

    /**
     * Generate a UUID v4
     */
    private function generateUuid(): string
    {
        $data = $this->secureRandom->generate(16);

        // Set version to 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
