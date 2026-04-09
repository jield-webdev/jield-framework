<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Adapter;

use OAuth2\Storage\Pdo as OAuth2Pdo;
use Override;
use function sprintf;

/**
 * Extension of OAuth2\Storage\PDO that provides Bcrypt client_secret/password
 * encryption
 */
class PdoAdapter extends OAuth2Pdo
{
    protected int $bcryptCost = 12;

    public function __construct($connection, $config = [])
    {
        parent::__construct(connection: $connection, config: $config);
        if (isset($config['bcrypt_cost'])) {
            $this->bcryptCost = $config['bcrypt_cost'];
        }
    }

    #[Override]
    public function checkClientCredentials($clientId, $clientSecret = null): bool
    {
        $stmt = $this->db->prepare(query: sprintf(
            'SELECT * from %s where client_id = :client_id',
            $this->config['client_table']
        ));
        $stmt->execute(params: ['client_id' => $clientId]);

        $result = $stmt->fetch();

        // Do not bother verifying if the secret is missing or empty.
        if (empty($result['client_secret'])) {
            return false;
        }

        // bcrypt verify
        return password_verify(password: (string)$clientSecret, hash: (string)$result['client_secret']);
    }

    #[Override]
    public function setClientDetails(
        $clientId,
        $clientSecret = null,
        $redirectUri = null,
        $grantTypes = null,
        $scopeOrUserId = null,
        $userId = null
    ): bool
    {
        if (func_num_args() > 5) {
            $scope = $scopeOrUserId;
        } else {
            $userId = $scopeOrUserId;
            $scope  = null;
        }

        if (!empty($clientSecret)) {
            $clientSecret = password_hash(password: $clientSecret, algo: PASSWORD_BCRYPT, options: ['cost' => $this->bcryptCost]);
        }

        return parent::setClientDetails(client_id: $clientId, client_secret: $clientSecret, redirect_uri: $redirectUri, grant_types: $grantTypes, scope: $scope, user_id: $userId);
    }

    protected function checkPassword($user, $password): bool
    {
        return password_verify(password: $password, hash: $user['password']);
    }

    public function getUser($username): array|bool
    {
        $userInfo = parent::getUser(username: $username);

        //Save the ID in the user_id field
        $userInfo['user_id'] = $userInfo['id'];

        return $userInfo;
    }
}
