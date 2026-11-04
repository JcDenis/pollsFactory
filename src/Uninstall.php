<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Plugin\Uninstaller\Uninstaller;

/**
 * @brief       pollsFactory uninstall class.
 * @ingroup     pollsFactory
 *
 * Table post_option is not deleted as it is shared with other plugins.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Uninstall
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::UNINSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        Uninstaller::instance()
            ->addUserAction(
                'settings',
                'delete_all',
                My::id()
            )
            ->addUserAction(
                'plugins',
                'delete',
                My::id()
            )
            ->addUserAction(
                'versions',
                'delete',
                My::id()
            )
            ->addDirectAction(
                'settings',
                'delete_all',
                My::id()
            )
            ->addDirectAction(
                'plugins',
                'delete',
                My::id()
            )
            ->addDirectAction(
                'versions',
                'delete',
                My::id()
            )
        ;

        // no custom action
        return false;
    }
}
