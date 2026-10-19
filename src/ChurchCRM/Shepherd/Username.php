<?php

namespace ChurchCRM\Shepherd;

/**
 * The one definition of what a Shepherd login name may look like.
 *
 * Upstream ChurchCRM accepts anything at least three characters long, and the
 * admin user editor used to default the field to the person's email address —
 * which is why accounts named like `tony.wade@example.com` exist. Shepherd
 * cannot keep that: the bulletin console identifies people by username, and its
 * roles file admits only `[a-z0-9._-]{3,50}`. A login containing `@` can never
 * appear there, so such an account can authenticate to Shepherd and then be
 * refused by the console with nothing on screen to explain why.
 *
 * An email address is a delivery mechanism, not an authentication subject. It
 * also moves: someone changes their mail provider and their login silently
 * becomes a lie. The name is the subject; the address belongs on the person
 * record, which is where it already is.
 *
 * Three callers share this class, and that is the point — the rule existing in
 * three places is how the two halves drift apart and start denying people
 * access for reasons no single file explains:
 *
 *   - `UserService` enforces it when an account is created or renamed;
 *   - `SignupService` enforces it on the public request form;
 *   - the console session endpoint canonicalises with it before answering nginx.
 *
 * It is mirrored once more outside this repository, in the website repo's
 * `src/lib/roles.ts`. Change the pattern here and that file has to move with it.
 */
final class Username
{
    /**
     * The canonical form. Lowercase only — `canonical()` folds case before
     * testing, so a stored `Admin` is accepted and answers as `admin`.
     *
     * The `D` modifier is load-bearing. Without it PHP's `$` also matches
     * immediately before a trailing newline, where the JavaScript regex this
     * mirrors anchors to the absolute end of the string. Here that is the
     * difference between rejecting a name that could split an HTTP response
     * header and accepting it.
     */
    private const CANONICAL_PATTERN = '/^[a-z0-9._-]{3,50}$/D';

    /** Used to truncate a suggestion; the minimum is stated by the pattern. */
    public const MAX_LENGTH = 50;

    /**
     * Reduce a stored or submitted login name to the form the console matches
     * on, or null if it has none.
     */
    public static function canonical(?string $username): ?string
    {
        if ($username === null) {
            return null;
        }

        // ASCII-only in PHP 8.2+, so this cannot fold a non-ASCII character
        // into a member of the character class. Anything it leaves alone fails
        // the test below.
        $canonical = strtolower(trim($username));

        return preg_match(self::CANONICAL_PATTERN, $canonical) === 1 ? $canonical : null;
    }

    /**
     * Whether this login name is usable as an identity at all.
     *
     * Case is not part of the question. `Admin` is acceptable and resolves to
     * `admin`; `tony.wade@example.com` is not acceptable in any casing.
     */
    public static function isAcceptable(?string $username): bool
    {
        return self::canonical($username) !== null;
    }

    /**
     * The human-facing reason a name was rejected.
     *
     * Deliberately specific. "Invalid username" sends an administrator back to
     * the form to guess, and the most likely thing they typed is the address
     * the field used to be pre-filled with.
     */
    public static function rejectionReason(): string
    {
        return gettext('A login may contain only letters, numbers, and the characters . _ - and must be 3 to 50 characters long. Email addresses cannot be used as logins; enter a name such as first.last instead.');
    }

    /**
     * Propose a login name for a new account.
     *
     * Preference order is the local part of the person's email, then
     * `first.last`. The local part is preferred because it is what the field
     * used to be filled with minus the domain, so an administrator sees roughly
     * the name they are used to approving rather than something unfamiliar.
     *
     * Returns an empty string when neither source yields an acceptable name —
     * an empty field an administrator must fill in is honest, where a
     * pre-filled invalid one is a trap.
     */
    public static function suggest(?string $email, ?string $firstName, ?string $lastName): string
    {
        $localPart = null;
        if ($email !== null && $email !== '') {
            $atPosition = strrpos($email, '@');
            $localPart = $atPosition === false ? $email : substr($email, 0, $atPosition);
        }

        foreach ([$localPart, trim((string) $firstName) . '.' . trim((string) $lastName)] as $candidate) {
            $sanitised = self::sanitise($candidate);
            if ($sanitised !== null) {
                return $sanitised;
            }
        }

        return '';
    }

    /**
     * Best-effort repair of a candidate into canonical form, or null if there
     * is nothing usable left. Only ever used to seed a form field, never to
     * decide whether a submitted name is acceptable — a suggestion that has to
     * be repaired is still shown to a human before it is saved.
     */
    private static function sanitise(?string $candidate): ?string
    {
        if ($candidate === null) {
            return null;
        }

        // Fold whatever separators the source used into a dot, drop everything
        // outside the character class, then collapse the runs that leaves
        // behind. "Mary-Anne O'Brien" becomes "mary-anne.obrien".
        $sanitised = strtolower(trim($candidate));
        $sanitised = preg_replace('/\s+/', '.', $sanitised) ?? '';
        $sanitised = preg_replace('/[^a-z0-9._-]/', '', $sanitised) ?? '';
        $sanitised = preg_replace('/\.{2,}/', '.', $sanitised) ?? '';
        $sanitised = trim($sanitised, '.');
        $sanitised = substr($sanitised, 0, self::MAX_LENGTH);

        return self::canonical($sanitised);
    }
}
