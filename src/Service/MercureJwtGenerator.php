<?php
namespace App\Service;

use App\Repository\UserRepository;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Bundle\SecurityBundle\Security;
use Lcobucci\JWT\Configuration; // <--- Add this
use App\Entity\User; // Your User entity

class MercureJwtGenerator
{
private string $mercureSecret;
private Security $security;
private UserRepository $userRepository;

public function __construct(string $mercureSecret, Security $security, UserRepository $userRepository)
{
$this->mercureSecret = $mercureSecret;
$this->security = $security;
$this->userRepository = $userRepository;
}

public function generateSubscriberJwt(): string
{
/** @var User|null $user */
//$user = $this->security->getUser();
$user = $this->userRepository->find(1);
if (!$user) {
throw new \RuntimeException('No authenticated user found for Mercure JWT generation.');
}

$allowedTopics = [];

// Add all conversation topics the user is a participant of
// Assuming your User entity has a getConversations() method that returns a traversable collection
foreach ($user->getConversations() as $conversation) {
//$allowedTopics[] = 'http://127.0.0.1:300/.well-known/mercure?topic=conversations/' . $conversation->getId();
$allowedTopics[] = 'conversations/' . $conversation->getId();
}

// The '/users/' topic is removed as requested.
// If you decide to add it later, you'd put it back here:
// $allowedTopics[] = '/users/' . $user->getId();

    $config = Configuration::forSymmetricSigner(
        new Sha256(),
        InMemory::plainText($this->mercureSecret)
    );

    // Use the builder from the configuration
    $tokenBuilder = $config->builder();

    $token = $tokenBuilder
->withClaim('mercure', [
'subscribe' => $allowedTopics,
'publish' => $allowedTopics,
])
->issuedAt(new \DateTimeImmutable())
->expiresAt((new \DateTimeImmutable())->modify('+1 hour')) // Set a reasonable expiration
->getToken(new Sha256(), InMemory::plainText($this->mercureSecret));

return $token->toString();
}
}