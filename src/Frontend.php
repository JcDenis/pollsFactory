<?php

declare(strict_types=1);

namespace Dotclear\Plugin\pollsFactory;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       pollsFactory frontend class.
 * @ingroup     pollsFactory
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Frontend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Widgets are registered even if plugin is not active (they check it)
        App::behavior()->addBehavior('initWidgets', Widgets::init(...));

        // Template tags
        App::frontend()->template()->addValue('PollForm', FrontendTemplate::PollForm(...));
        App::frontend()->template()->addValue('PollsOfEntry', FrontendTemplate::PollsOfEntry(...));

        if (!My::isActive()) {
            return true;
        }

        App::behavior()->addBehaviors([
            'publicHeadContent'        => FrontendBehaviors::publicHeadContent(...),
            'publicBeforeDocumentV2'   => FrontendBehaviors::publicBeforeDocumentV2(...),
            'publicEntryBeforeContent' => FrontendBehaviors::publicEntryBeforeContent(...),
            'publicEntryAfterContent'  => FrontendBehaviors::publicEntryAfterContent(...),
        ]);

        return true;
    }
}
