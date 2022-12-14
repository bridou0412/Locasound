<?php
declare(strict_types=1);

namespace Robert2\API\Models\Enums;

/**
 * Groupes des utilisateurs de l'application.
 *
 * TODO: En PHP 8.1, migrer vers une énumération.
 *       Voir https://www.php.net/manual/fr/language.enumerations.php
 */
class Group
{
    /** Représente le groupe des administrateurs. */
    public const ADMIN = 'admin';

    /** Représente le groupe des membres de l'équipe. */
    public const MEMBER = 'member';

    /** Représente le groupe des visiteurs. */
    public const VISITOR = 'visitor';
}
