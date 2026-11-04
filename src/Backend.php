<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Core\Backend\Menus;
use Dotclear\Core\PostType;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       pollsFactory backend class.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Backend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Polls post type with backend URLs
        $icons = My::icons();
        App::postTypes()->set(new PostType(
            My::POST_TYPE,
            urldecode(My::manageUrl(['part' => 'poll', 'id' => '%d'], '&')),
            App::url()->getURLFor('pollsfactory_page', '%s'),
            'Polls',
            urldecode(My::manageUrl([], '&')),
            $icons[0] ?? '',
            $icons[1] ?? $icons[0] ?? ''
        ));

        // backend sidebar menu icon
        My::addBackendMenuItem(Menus::MENU_BLOG);

        App::behavior()->addBehaviors([
            'initWidgets'               => Widgets::init(...),
            'adminDashboardFavoritesV2' => BackendBehaviors::adminDashboardFavoritesV2(...),
            'adminColumnsListsV2'       => BackendBehaviors::adminColumnsListsV2(...),
            'adminFiltersListsV2'       => BackendBehaviors::adminFiltersListsV2(...),
            'adminBeforePostDelete'     => BackendBehaviors::adminBeforePostDelete(...),
            'adminBeforePageDelete'     => BackendBehaviors::adminBeforePostDelete(...),
        ]);

        if (My::isActive()) {
            App::behavior()->addBehaviors([
                'adminPostFormItems'   => BackendBehaviors::adminPostFormItems(...),
                'adminPageFormItems'   => BackendBehaviors::adminPostFormItems(...),
                'adminAfterPostCreate' => BackendBehaviors::adminAfterPostSave(...),
                'adminAfterPostUpdate' => BackendBehaviors::adminAfterPostSave(...),
                'adminAfterPageCreate' => BackendBehaviors::adminAfterPostSave(...),
                'adminAfterPageUpdate' => BackendBehaviors::adminAfterPostSave(...),
            ]);
        }

        return true;
    }
}
