<?php

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Utils\DateTimeUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

// Email History — what the church has emailed this member, newest first.
//
// The page is reached from the header's account menu, not from the main
// navigation: it is a record of the account rather than a place a member works,
// which is why no nav entry is marked active here (PortalNav simply marks
// nothing when it is handed an id it does not know).
//
// Both pages read straight through EmailLogService, the same service
// `GET /api/portal/me/emails` answers with, so a server-rendered page and the
// API can never disagree. Dates and status words are turned into strings here
// rather than in the template, so a church theme gets something it can print
// (design §3.6).
//
// Scope: only rows addressed to this person (`eml_per_ID`). Mail sent to the
// family's shared address is deliberately out of this first version — see the
// docblock of src/api/routes/portal/portal-emails.php.

/** The acting member's person record, or a 404 for an account with none. */
$portalEmailActor = static function (Request $request): Person {
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    if (!$person instanceof Person) {
        throw new HttpNotFoundException(
            $request,
            gettext('Your account is not linked to a person record. Please contact the church office.')
        );
    }

    return $person;
};

/** The word a member reads for an eml_Status value. */
$portalEmailStatusLabel = static function (string $status): string {
    return match ($status) {
        EmailLogService::STATUS_SENT => gettext('Sent'),
        EmailLogService::STATUS_FAILED => gettext('Failed'),
        EmailLogService::STATUS_SKIPPED => gettext('Skipped'),
        default => $status,
    };
};

/** One history row as the templates want it: strings, already formatted. */
$portalEmailRow = static function (array $row) use ($portalEmailStatusLabel): array {
    return [
        'id' => (int) $row['id'],
        'subject' => (string) $row['subject'],
        'kindLabel' => (string) $row['kindLabel'],
        'status' => (string) $row['status'],
        'statusLabel' => $portalEmailStatusLabel((string) $row['status']),
        'address' => (string) $row['address'],
        'dateSent' => DateTimeUtils::formatDate((string) $row['dateSent'], true),
        'hasBody' => (bool) $row['hasBody'],
    ];
};

// GET /portal/email-history — the paginated list.
$group->get('/email-history', function (Request $request, Response $response) use ($portalEmailActor, $portalEmailRow): Response {
    $query = $request->getQueryParams();

    // A page or limit that is not a whole number greater than zero is simply
    // the default: a member who edits the URL gets the first page, not an
    // error. `limit` exists mainly so a test can force more than one page;
    // nothing in the UI offers it.
    $toPositiveInt = static function ($raw, int $fallback): int {
        $value = (is_string($raw) || is_int($raw)) ? filter_var($raw, FILTER_VALIDATE_INT) : false;

        return ($value === false || $value < 1) ? $fallback : $value;
    };

    $page = $toPositiveInt($query['page'] ?? null, 1);
    $limit = min($toPositiveInt($query['limit'] ?? null, EmailLogService::DEFAULT_PAGE_SIZE), EmailLogService::MAX_PAGE_SIZE);

    $history = (new EmailLogService())->getForPerson((int) $portalEmailActor($request)->getId(), $page, $limit);

    // The pager keeps a non-default limit in its links so paging does not
    // silently resize the page under the member.
    $pageQuery = static function (int $target) use ($limit): string {
        $params = ['page' => $target];
        if ($limit !== EmailLogService::DEFAULT_PAGE_SIZE) {
            $params['limit'] = $limit;
        }

        return '?' . http_build_query($params);
    };

    return PortalTwig::render(
        $response,
        'email/index.html.twig',
        [
            'pageTitle' => gettext('Email History'),
            'emails' => array_map($portalEmailRow, $history['rows']),
            'page' => $history['page'],
            'pages' => $history['pages'],
            'total' => $history['total'],
            'previousUrl' => $history['page'] > 1 ? $pageQuery($history['page'] - 1) : '',
            'nextUrl' => $history['page'] < $history['pages'] ? $pageQuery($history['page'] + 1) : '',
        ]
    );
});

// GET /portal/email-history/{id} — one email, with its stored body.
//
// A row that is not this member's and an id that does not exist are the same
// 404, for the reason the API gives: a member must not be able to learn that an
// email exists by asking for it.
$group->get('/email-history/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($portalEmailActor, $portalEmailRow): Response {
    $service = new EmailLogService();
    $row = $service->find((int) $args['id']);

    if ($row === null || (int) $row->getPerId() !== (int) $portalEmailActor($request)->getId()) {
        throw new HttpNotFoundException($request, gettext('This email is not in your history.'));
    }

    $email = $service->toArray($row, true);

    return PortalTwig::render(
        $response,
        'email/show.html.twig',
        [
            'pageTitle' => $email['subject'] !== '' ? $email['subject'] : gettext('Email'),
            'email' => $portalEmailRow($email),
            // The stored HTML, handed to the template for one purpose only: to
            // be escaped into the `srcdoc` of a sandboxed iframe. It is never
            // printed into the portal's own document.
            'body' => (string) ($email['body'] ?? ''),
            'hasBody' => $email['hasBody'],
        ]
    );
});
