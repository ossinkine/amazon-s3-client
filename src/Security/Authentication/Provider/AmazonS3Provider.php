<?php

namespace Security\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AmazonS3Provider implements AuthenticationProviderInterface
{
    public function authenticate(TokenInterface $token)
    {
        if (strlen($token->getUsername()) !== 20) {
            throw new AuthenticationException('Access Key ID length is invalid');
        }
        if (strlen($token->getCredentials()) !== 40) {
            throw new AuthenticationException('Secret Access Key length is invalid');
        }

        return $token;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof UsernamePasswordToken;
    }
}
