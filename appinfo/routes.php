<?php

declare(strict_types=1);

return [
    'ocs' => [
        // GET all contacts (optionally filtered by user_id)
        ['name' => 'OCS#getAllContacts', 'url' => '/api/v1/contacts', 'verb' => 'GET'],

        // POST create a new contact in a user's address book
        ['name' => 'OCS#createContact', 'url' => '/api/v1/contacts', 'verb' => 'POST'],

        // GET contacts for a specific user
        ['name' => 'OCS#listUserContacts', 'url' => '/api/v1/contacts/{userId}', 'verb' => 'GET'],

        // GET a specific contact
        ['name' => 'OCS#getContact', 'url' => '/api/v1/contacts/{userId}/{uid}', 'verb' => 'GET'],

        // DELETE a specific contact
        ['name' => 'OCS#deleteContact', 'url' => '/api/v1/contacts/{userId}/{uid}', 'verb' => 'DELETE'],

        // PUT update a specific contact
        ['name' => 'OCS#updateContact', 'url' => '/api/v1/contacts/{userId}/{uid}', 'verb' => 'PUT'],
    ],
];
