<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\NoteQuery;

class NoteMiddleware extends AbstractEntityMiddleware
{
    protected function getRouteParamName(): string
    {
        return 'noteId';
    }

    protected function getAttributeName(): string
    {
        return 'note';
    }

    protected function loadEntity(string $id): mixed
    {
        return NoteQuery::create()->findPk((int) $id);
    }

    protected function getNotFoundMessage(): string
    {
        return gettext('Note not found');
    }
}
