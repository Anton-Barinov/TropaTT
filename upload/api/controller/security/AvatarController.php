<?php
declare(strict_types=1);

namespace Api\Controller\Security;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\UserProfileService;

/**
 * Serves a user's avatar image.
 *
 * Avatars are stored outside the web root and can only be read through this
 * authenticated endpoint. Any signed-in user may view another user's avatar
 * (they are not sensitive); the endpoint never exposes the storage path.
 */
final class AvatarController extends BaseController
{
    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function show(array $params): array
    {
        if (!$this->user()) {
            return ['error' => 'UNAUTHORIZED'];
        }

        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        $avatar = $service->avatarForPublicId((string)($params['public_id'] ?? ''));
        if ($avatar === null) {
            return ['error' => 'AVATAR_NOT_FOUND'];
        }

        $path = $avatar['path'];

        return [
            'path' => $path,
            'name' => 'avatar',
            'mime' => $avatar['mime'],
            'size' => (int)@filesize($path),
            'inline' => true,
        ];
    }
}
