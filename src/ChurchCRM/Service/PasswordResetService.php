<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\User;
use Propel\Runtime\Propel;

class PasswordResetService
{
    public function reset(Token $token, User $user, string $password): bool
    {
        $user->validateNewPassword($password);
        $connection = Propel::getWriteConnection('default');
        $connection->beginTransaction();

        try {
            if (!$token->isPasswordResetToken() || !$token->isValid()
                || $user->getSignInBlockedReason() !== null || !$token->consume($connection)) {
                $connection->rollBack();

                return false;
            }

            $user->updatePassword($password);
            $user->setNeedPasswordChange(false);
            $user->setFailedLogins(0);
            $user->save($connection);
            $connection->commit();

            return true;
        } catch (\Throwable $error) {
            $connection->rollBack();
            throw $error;
        }
    }
}
