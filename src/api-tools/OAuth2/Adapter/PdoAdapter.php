<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Adapter;

use Jield\Stdlib\Value;
use OAuth2\Storage\Pdo as OAuth2Pdo;
use Override;

use RuntimeException;
use SensitiveParameter;
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
        parent::__construct(connection: $connection, config: Value::array($config));
        if (isset($config['bcrypt_cost'])) {
            $this->bcryptCost = Value::int($config['bcrypt_cost']);
        }
    }

    #[Override]
    public function checkClientCredentials($client_id, #[SensitiveParameter] $client_secret = null): bool
    {
        $stmt = $this->db->prepare(query: sprintf(
            'SELECT * from %s where client_id = :client_id',
            Value::scalar($this->config['client_table']),
        ));
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare the OAuth client query.');
        }
        $stmt->execute(params: ['client_id' => $client_id]);

        /** @var mixed $result */
        $result = $stmt->fetch();

        // Do not bother verifying if the secret is missing or empty.
        if (empty($result['client_secret'])) {
            return false;
        }

        // bcrypt verify
        return password_verify(password: (string) $client_secret, hash: (string) $result['client_secret']);
    }

    #[Override]
    public function setClientDetails(
        $client_id,
        #[SensitiveParameter]
        $client_secret = null,
        $redirect_uri = null,
        $grant_types = null,
        $scope = null,
        $user_id = null,
    ): bool {
        if (func_num_args() > 5) {
            $scope = $scope;
        } else {
            $user_id = $scope;
            $scope = null;
        }

        if (!empty($client_secret)) {
            $client_secret = password_hash(password: $client_secret, algo: PASSWORD_BCRYPT, options: [
                'cost' => $this->bcryptCost,
            ]);
        }

        return parent::setClientDetails(
            client_id: $client_id,
            client_secret: $client_secret,
            redirect_uri: $redirect_uri,
            grant_types: $grant_types,
            scope: $scope,
            user_id: $user_id,
        );
    }

    protected function checkPassword($user, #[SensitiveParameter] $password): bool
    {
        return password_verify($password, Value::string($user['password']));
    }

    public function getUser($username): array|bool
    {
        $userInfo = parent::getUser($username);

        if (!$userInfo) {
            return false;
        }

        $userInfo = Value::array($userInfo);

        //Save the ID in the user_id field
        $userInfo['user_id'] = $userInfo['id'];

        return $userInfo;
    }
}
