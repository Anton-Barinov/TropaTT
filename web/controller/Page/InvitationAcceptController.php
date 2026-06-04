<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class InvitationAcceptController extends Controller
{
    public function index(): void
    {
        $this->render('page/invitation_accept', [
            'title' => 'Принятие приглашения',
            'route' => 'invitation-accept',
        ]);
    }
}
