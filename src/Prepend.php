<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Core\PostType;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       pollsFactory prepend class.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Prepend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::PREPEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Public URLs (url "poll" is used by plugin dotPoll)
        App::url()->register('pollsfactory_page', 'survey', '^survey/(.+)$', FrontendUrl::pollPage(...));
        App::url()->register('pollsfactory_preview', 'surveypreview', '^surveypreview/(.+)$', FrontendUrl::pollPreview(...));

        // Polls post type, backend URLs are set in Backend
        App::postTypes()->set(new PostType(
            My::POST_TYPE,
            '',
            App::url()->getURLFor('pollsfactory_page', '%s'),
            'Polls'
        ));


        return true;
    }
}
